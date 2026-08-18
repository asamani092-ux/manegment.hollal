<?php

namespace App\Services;

use App\Models\DiagnosisQuestion;
use App\Models\PartnerLink;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\Program;
use App\Models\Quote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Partner self-serve: catalog, priced quote, diagnosis, accept.
 * Time: O(p + q) | Space: O(p + q)
 */
class PartnerPortalSelfServeService
{
    /** @return Collection<int, Program> Time: O(p) | Space: O(p) */
    public function catalog(Partnership $partnership): Collection
    {
        $query = Program::query()
            ->where('stage', Program::STAGE_ACTIVE)
            ->with(['prices' => fn ($q) => $q->where('is_active', true)->orderBy('id')])
            ->orderBy('name');

        $allowedIds = $partnership->allowedPrograms()->pluck('programs.id')->map(fn ($id) => (int) $id)->all();
        if ($allowedIds !== []) {
            $query->whereIn('id', $allowedIds);
        }

        return $query
            ->get(['id', 'name', 'description', 'target_audience', 'sessions_count', 'hours_count'])
            ->filter(fn (Program $program) => $program->prices->isNotEmpty())
            ->values();
    }

    /**
     * @param  list<int|string>  $selectedIds
     * @param  array<int|string, string>  $quantities
     * @param  array<int|string, string>  $services
     */
    public function confirmPrograms(PartnerLink $link, array $selectedIds, array $quantities, array $services): Quote
    {
        $items = $this->itemsFromSelection($link, $selectedIds, $quantities, $services);
        $partnership = $link->partnership()->firstOrFail();
        $partnership->allowedPrograms()->syncWithoutDetaching(
            collect($items)->pluck('program_id')->all()
        );

        $open = $partnership->quotes()
            ->whereNotIn('status', [Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED])
            ->orderByDesc('version')
            ->first();

        $quote = $open
            ? $this->applyItems($open, $items)
            : app(QuoteService::class)->create($partnership, $items, advanceStage: false);

        app(PartnerPortalService::class)->log($link, 'portal.programs_selected', [
            'quote_id' => $quote->id,
            'program_ids' => array_column($items, 'program_id'),
        ], request()->ip());

        return $quote;
    }

    /**
     * @param  array<int|string, string>  $extraAnswers
     */
    public function submitDiagnosis(
        PartnerLink $link,
        string $audience,
        string $count,
        string $environment,
        array $extraAnswers = [],
    ): void {
        $questions = app(DiagnosisQuestionService::class)->activeQuestions();
        $answers = [];

        if ($questions->isEmpty()) {
            if (trim($audience) === '' || ! is_numeric($count) || (int) $count < 1) {
                throw new \RuntimeException('أكمل الفئة والأعداد');
            }
        } else {
            foreach ($questions as $question) {
                $value = $this->diagnosisValue($question, $audience, $count, $environment, $extraAnswers);
                if ($question->required && trim($value) === '') {
                    throw new \RuntimeException($question->label.' مطلوب');
                }
                if ($question->type === 'number' && $value !== '' && ! is_numeric($value)) {
                    throw new \RuntimeException($question->label.' يجب أن يكون رقمًا');
                }
                $answers[$question->id] = $value;
                if ($question->key === 'audience') {
                    $audience = $value;
                }
                if ($question->key === 'count') {
                    $count = $value;
                }
                if ($question->key === 'environment') {
                    $environment = $value;
                }
            }
            app(DiagnosisQuestionService::class)->recordAnswers($link->partnership, $answers);
        }

        app(PartnerPortalService::class)->log($link, 'portal.diagnosis_submitted', [
            'audience' => $audience,
            'count' => (int) $count,
            'environment' => $environment,
            'answers' => $answers,
        ], request()->ip());

        app(PartnershipPipelineService::class)->advanceIfBefore(
            $link->partnership,
            Partnership::STAGE_DIAGNOSIS,
            null,
            'إرسال استبانة التشخيص من بوابة الشريك',
        );
        app(PartnershipPipelineService::class)->advanceIfBefore(
            $link->partnership->fresh(),
            Partnership::STAGE_QUOTE,
            null,
            'اكتمال التشخيص — الانتقال لعرض السعر',
        );
    }

    /**
     * @param  list<int|string>|null  $selectedIds
     * @param  array<int|string, string>  $quantities
     * @param  array<int|string, string>  $services
     */
    public function acceptQuote(
        PartnerLink $link,
        int $quoteId,
        ?string $notes = null,
        ?array $selectedIds = null,
        array $quantities = [],
        array $services = [],
    ): Quote {
        $quote = Quote::query()
            ->where('partnership_id', $link->partnership_id)
            ->findOrFail($quoteId);

        return DB::transaction(function () use ($link, $quote, $notes, $selectedIds, $quantities, $services) {
            if ($selectedIds && ! $quote->isReadyForPartner()) {
                $quote = $this->applyItems(
                    $quote,
                    $this->itemsFromSelection($link, $selectedIds, $quantities, $services)
                );
            }

            $accepted = app(QuoteService::class)->accept($quote, $notes !== null && $notes !== '' ? $notes : null);
            $this->ensureSignableContract($accepted);
            $accepted->partnership->enablePortalFeatures(['payments', 'contract']);
            app(PartnerPortalService::class)->log($link, 'portal.quote_accepted', [
                'quote_id' => $accepted->id,
            ], request()->ip());
            app(PartnershipPipelineService::class)->advanceIfBefore(
                $link->partnership,
                Partnership::STAGE_QUOTE,
                null,
                'قبول العرض من بوابة الشريك',
            );

            return $accepted;
        });
    }

    /** Time: O(s) schedule rows | Space: O(s) */
    private function ensureSignableContract(Quote $quote): void
    {
        $exists = PartnershipContract::query()
            ->where('partnership_id', $quote->partnership_id)
            ->where('status', '!=', PartnershipContract::STATUS_CANCELLED)
            ->exists();

        if ($exists) {
            return;
        }

        app(PartnershipContractService::class)->createFromQuote(
            $quote,
            [[
                'label' => 'الدفعة الأولى',
                'amount' => (float) $quote->total,
                'due_on' => now()->addDays(7)->toDateString(),
            ]],
            false,
        );
    }

    /**
     * @param  list<int|string>  $selectedIds
     * @param  array<int|string, string>  $quantities
     * @param  array<int|string, string>  $services
     * @return list<array{program_id: int, service_type: string, quantity: float, unit_price: float}>
     */
    public function itemsFromSelection(PartnerLink $link, array $selectedIds, array $quantities, array $services): array
    {
        $catalog = $this->catalog($link->partnership()->firstOrFail());
        $allowedIds = $catalog->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids = collect($selectedIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($ids === []) {
            throw new \RuntimeException('اختر برنامجًا واحدًا على الأقل');
        }
        if (array_diff($ids, $allowedIds) !== []) {
            throw new \RuntimeException('لا يمكن اختيار برنامج خارج الكتالوج');
        }

        $items = [];
        foreach ($ids as $programId) {
            $program = $catalog->firstWhere('id', $programId);
            $service = (string) ($services[$programId] ?? $services[(string) $programId] ?? $program?->prices->first()?->service_type);
            $price = $program?->prices->firstWhere('service_type', $service);
            if (! $price) {
                throw new \RuntimeException('لا يوجد سعر نشط للخدمة المختارة');
            }
            $quantity = app(QuoteService::class)->diagnosisQuantity($link->partnership()->firstOrFail());
            $items[] = [
                'program_id' => $programId,
                'service_type' => $price->service_type,
                'quantity' => $quantity,
                'unit_price' => (float) $price->unit_price,
            ];
        }

        return $items;
    }

    /** @param list<array{program_id: int, service_type: string, quantity: float, unit_price: float}> $items */
    private function applyItems(Quote $quote, array $items): Quote
    {
        $service = app(QuoteService::class);

        return $quote->status === Quote::STATUS_DRAFT
            ? $service->updateDraft($quote, $items)
            : $service->revise($quote, $items);
    }

    /** @param  array<int|string, string>  $extraAnswers */
    private function diagnosisValue(
        DiagnosisQuestion $question,
        string $audience,
        string $count,
        string $environment,
        array $extraAnswers,
    ): string {
        if ($question->key === 'audience' && $audience !== '') {
            return $audience;
        }
        if ($question->key === 'count' && $count !== '') {
            return $count;
        }
        if ($question->key === 'environment' && $environment !== '') {
            return $environment;
        }

        return trim((string) ($extraAnswers[$question->id] ?? $extraAnswers[(string) $question->id] ?? ''));
    }
}
