<?php

namespace App\Livewire\Partnerships;

use App\Models\ContractPaymentSchedule;
use App\Models\DiagnosisQuestion;
use App\Models\PartnerLink;
use App\Models\Partnership;
use App\Models\PartnershipContract;
use App\Models\PartnershipPayment;
use App\Models\Program;
use App\Models\Quote;
use App\Services\DiagnosisQuestionService;
use App\Services\PartnerPortalService;
use App\Services\PartnershipContractService;
use App\Services\PartnershipPaymentService;
use App\Services\PartnershipPipelineService;
use App\Services\QuoteService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 05-B5 — the partner portal, reached only through the unique link.
 *
 * Isolation rule: every read and every write is scoped to `$link->partnership`.
 * The token is the only identity; no id ever arrives from the request. Each
 * action is written to the portal activity log.
 */
class PartnerPortal extends Component
{
    use WithFileUploads;

    public PartnerLink $link;

    public string $interestedPrograms = '';

    /** @var list<int|string> */
    public array $selectedProgramIds = [];

    /** @var array<int|string, string> */
    public array $programQuantities = [];

    /** @var array<int|string, string> */
    public array $programServices = [];

    public string $diagnosisAudience = '';

    public string $diagnosisCount = '';

    public string $diagnosisEnvironment = '';

    /** @var array<int|string, string> */
    public array $diagnosisAnswers = [];

    public string $quoteNotes = '';

    public string $paymentAmount = '';

    public $paymentProof;

    public $signedContract;

    public string $signatureName = '';

    public string $signaturePosition = '';

    /** data:image/png;base64,... من لوحة التوقيع */
    public string $signaturePadData = '';

    public int $focusStep = 0;

    /** @var array<int, string> */
    public const PAGE_KEYS = [
        1 => 'programs',
        2 => 'diagnosis',
        3 => 'quotes',
        4 => 'payments',
        5 => 'contract',
    ];

    public function mount(string $token, ?string $page = null): void
    {
        $link = app(PartnerPortalService::class)->resolve($token);

        abort_if($link === null, 404);

        $this->link = $link;
        $this->initializeCatalogSelection();
        $this->loadSavedDiagnosisAnswers();
        if (is_string($page) && $page !== '') {
            $stepId = array_search($page, self::PAGE_KEYS, true);
            abort_if($stepId === false, 404);
            $this->focusStep = (int) $stepId;
        }
        if ($page === null) {
            $this->log('portal.opened');
        }
    }

    public function submitInterest(): void
    {
        $this->validate(['interestedPrograms' => 'required|string|max:1000']);

        $this->log('portal.programs_selected', ['programs' => $this->interestedPrograms]);
        $this->dispatch('ds-toast', message: 'تم تسجيل اهتمامكم');
    }

    public function submitDiagnosis(): void
    {
        $questions = app(DiagnosisQuestionService::class)->activeQuestions();
        $answers = [];

        if ($questions->isEmpty()) {
            $this->validate([
                'diagnosisAudience' => 'required|string|max:255',
                'diagnosisCount' => 'required|integer|min:1',
                'diagnosisEnvironment' => 'nullable|string|max:1000',
            ]);
        } else {
            foreach ($questions as $question) {
                $value = $this->diagnosisValue($question);
                if ($question->required && trim($value) === '') {
                    $this->addError('diagnosisAnswers.'.$question->id, 'هذا السؤال مطلوب');
                } elseif ($question->type === 'number' && $value !== '' && ! is_numeric($value)) {
                    $this->addError('diagnosisAnswers.'.$question->id, 'أدخل رقمًا صحيحًا');
                }
                $answers[$question->id] = $value;
                if ($question->key === 'audience') {
                    $this->diagnosisAudience = $value;
                }
                if ($question->key === 'count') {
                    $this->diagnosisCount = $value;
                }
                if ($question->key === 'environment') {
                    $this->diagnosisEnvironment = $value;
                }
            }

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            app(DiagnosisQuestionService::class)->recordAnswers($this->link->partnership, $answers);
        }

        $this->log('portal.diagnosis_submitted', [
            'audience' => $this->diagnosisAudience,
            'count' => (int) $this->diagnosisCount,
            'environment' => $this->diagnosisEnvironment,
            'answers' => $answers,
        ]);

        app(PartnershipPipelineService::class)->advanceIfBefore(
            $this->link->partnership,
            Partnership::STAGE_DIAGNOSIS,
            null,
            'إرسال استبانة التشخيص من بوابة الشريك',
        );

        $this->dispatch('ds-toast', message: 'تم استلام استبانة التشخيص');
    }

    public function confirmPrograms(): void
    {
        try {
            $this->syncQuoteFromSelection();
            $this->dispatch('ds-toast', message: 'حُفظ الاختيار وبُني العرض من أسعار البرامج');
        } catch (\RuntimeException $exception) {
            $this->addError('selectedProgramIds', $exception->getMessage());
            $this->dispatch('ds-toast', message: $exception->getMessage());
        }
    }

    public function acceptQuote(int $quoteId): void
    {
        $quote = $this->scopedQuote($quoteId);

        try {
            $quote = $this->applyItemsToQuote($quote, $this->selectionItems());
        } catch (\RuntimeException $exception) {
            $this->addError('selectedProgramIds', $exception->getMessage());
            $this->dispatch('ds-toast', message: $exception->getMessage());

            return;
        }

        app(QuoteService::class)->accept($quote, $this->quoteNotes !== '' ? $this->quoteNotes : null);
        $this->quoteNotes = '';
        $this->log('portal.quote_accepted', ['quote_id' => $quote->id]);
        app(PartnershipPipelineService::class)->advanceIfBefore(
            $this->link->partnership,
            Partnership::STAGE_QUOTE,
            null,
            'قبول العرض من بوابة الشريك',
        );

        $this->dispatch('ds-toast', message: 'تم قبول العرض');
    }

    /**
     * Rebuild the quote from the allowed catalog. Drafts are updated in place;
     * any already-issued quote becomes a new version before acceptance.
     */
    public function saveProgramSelection(int $quoteId): Quote
    {
        $quote = $this->scopedQuote($quoteId);

        return $this->applyItemsToQuote($quote, $this->selectionItems());
    }

    public function recordPayment(int $scheduleId): void
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentProof' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ], [], ['paymentProof' => 'إثبات التحويل', 'paymentAmount' => 'المبلغ']);

        $scheduleItem = $this->scopedScheduleItem($scheduleId);
        $proofPath = $this->paymentProof?->store('partnership-payments/'.$this->link->partnership_id, 'local');

        $payment = app(PartnershipPaymentService::class)->record(
            $scheduleItem,
            (float) $this->paymentAmount,
            null,
            $proofPath,
            PartnershipPayment::VIA_PORTAL,
        );

        $this->paymentAmount = '';
        $this->paymentProof = null;
        $this->log('portal.payment_recorded', ['payment_id' => $payment->id]);

        $this->dispatch('ds-toast', message: 'سُجلت الدفعة بانتظار تأكيد المالية');
    }

    /** مسار الرفع اليدوي أُزيل من بوابة الشريك — التوقيع من الواجهة فقط. */

    /**
     * Amendments Q2 — توقيع إلكتروني داخل الرابط (لوحة + اسم + صفة).
     * Time: O(PDF) | Space: O(PDF)
     */
    public function signElectronically(int $contractId): void
    {
        $this->validate([
            'signaturePadData' => 'required|string|min:32',
            'signatureName' => 'required|string|max:255',
            'signaturePosition' => 'required|string|max:255',
        ]);

        $contract = $this->scopedContract($contractId);

        app(PartnershipContractService::class)->signElectronically(
            $contract,
            $this->signaturePadData,
            $this->signatureName,
            $this->signaturePosition,
            request()->userAgent(),
        );

        $this->signaturePadData = '';
        $this->signatureName = '';
        $this->signaturePosition = '';
        $this->log('portal.contract_signed_electronically', ['contract_id' => $contract->id]);

        $this->dispatch('ds-toast', message: 'تم التوقيع الإلكتروني — بانتظار تأكيد مدير الشراكات');
    }

    public function render(): View
    {
        $partnership = $this->link->partnership()->with([
            'organization', 'quotes.items', 'partnershipContracts.schedule', 'payments',
        ])->firstOrFail();
        $features = $partnership->portalFeatureFlags();
        $quotes = $partnership->quotes
            ->whereNotIn('status', [Quote::STATUS_REJECTED])
            ->sortByDesc('version')
            ->take(1)
            ->values();

        $wizard = $this->wizardState($partnership, $features, $quotes);

        return view('livewire.partnerships.partner-portal', [
            'partnership' => $partnership,
            'programs' => $this->allowedPrograms(),
            'quotes' => $quotes,
            'features' => $features,
            'diagnosisQuestions' => app(DiagnosisQuestionService::class)->activeQuestions(),
            'wizard' => $wizard,
        ])->layout('layouts.portal', ['title' => $this->pageTitle($wizard['focus'])]);
    }

    /**
     * @param  array{programs: bool, diagnosis: bool, quotes: bool, payments: bool, contract: bool}  $features
     * @return array{current: int, steps: list<array{id: int, key: string, label: string, state: string}>}
     */
    private function wizardState($partnership, array $features, $quotes): array
    {
        $diagnosisDone = $this->link->activities()
            ->where('action', 'portal.diagnosis_submitted')
            ->exists();
        $quoteAccepted = $quotes->contains(fn (Quote $quote) => $quote->status === Quote::STATUS_ACCEPTED);
        $quoteVisible = $quotes->isNotEmpty();
        $contract = $partnership->partnershipContracts->last();
        $signed = $contract?->hasSignedCopy() ?? false;
        $paymentSubmitted = $partnership->payments->whereIn('status', [
            PartnershipPayment::STATUS_PENDING,
            PartnershipPayment::STATUS_CONFIRMED,
        ])->isNotEmpty();
        $definitions = [
            ['id' => 1, 'key' => 'programs', 'label' => 'البرامج', 'enabled' => $features['programs'], 'done' => $this->selectedProgramIds !== [] && $quoteVisible],
            ['id' => 2, 'key' => 'diagnosis', 'label' => 'التشخيص', 'enabled' => $features['diagnosis'], 'done' => $diagnosisDone],
            ['id' => 3, 'key' => 'quotes', 'label' => 'عروض الأسعار', 'enabled' => $features['quotes'], 'done' => $quoteAccepted],
            ['id' => 4, 'key' => 'payments', 'label' => 'الدفعات', 'enabled' => $features['payments'], 'done' => $paymentSubmitted],
            ['id' => 5, 'key' => 'contract', 'label' => 'العقد', 'enabled' => $features['contract'], 'done' => $signed],
        ];
        $postQuoteOpen = $quoteAccepted || $contract !== null;

        $current = 1;
        foreach ($definitions as $step) {
            if (! $step['enabled']) {
                continue;
            }
            $current = $step['id'];
            if (! $step['done']) {
                break;
            }
        }

        $steps = [];
        foreach ($definitions as $step) {
            if (! $step['enabled']) {
                continue;
            }
            if ($step['id'] >= 4 && $postQuoteOpen) {
                $state = $step['done'] ? 'done' : ($step['id'] === $current ? 'current' : 'open');
            } else {
                $state = $step['id'] < $current ? 'done' : ($step['id'] === $current ? 'current' : ($step['id'] <= 3 ? 'open' : 'locked'));
            }
            $steps[] = [...$step, 'state' => $state];
        }

        $focus = $this->focusStep > 0 ? $this->focusStep : $current;

        return ['current' => $current, 'focus' => $focus, 'steps' => $steps];
    }

    public function openPortalStep(int $stepId): void
    {
        if (! isset(self::PAGE_KEYS[$stepId])) {
            return;
        }

        $this->focusStep = $stepId;
    }

    /** Time: O(1) | Space: O(1) */
    private function pageTitle(int $focus): string
    {
        return match ($focus) {
            1 => 'بوابة الجهة — البرامج',
            2 => 'بوابة الجهة — التشخيص',
            3 => 'بوابة الجهة — قبول العرض',
            4 => 'بوابة الجهة — الدفعات',
            5 => 'بوابة الجهة — العقد',
            default => 'بوابة الجهة',
        };
    }

    /** @param array<string, mixed> $metadata */
    private function log(string $action, array $metadata = []): void
    {
        app(PartnerPortalService::class)->log($this->link, $action, $metadata, request()->ip());
    }

    private function initializeCatalogSelection(): void
    {
        $quote = $this->link->partnership()
            ->with('quotes.items')
            ->firstOrFail()
            ->quotes
            ->sortByDesc('version')
            ->first();

        foreach ($quote?->items ?? [] as $item) {
            if ($item->program_id === null) {
                continue;
            }

            $this->selectedProgramIds[] = $item->program_id;
            $this->programQuantities[$item->program_id] = (string) $item->quantity;
            $this->programServices[$item->program_id] = $item->service_type;
        }

        // لا اختيار تلقائي — الجهة تحدّد البرامج بنفسها.
    }

    private function allowedPrograms()
    {
        $partnership = $this->link->partnership()->firstOrFail();
        $catalog = $partnership->allowedPrograms()
            ->where('programs.stage', Program::STAGE_ACTIVE)
            ->with(['prices' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
            ->get(['programs.id', 'programs.name', 'programs.description', 'programs.target_audience', 'programs.sessions_count', 'programs.hours_count']);

        if ($catalog->isEmpty()) {
            $catalog = Program::query()
                ->where('stage', Program::STAGE_ACTIVE)
                ->with(['prices' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'target_audience', 'sessions_count', 'hours_count']);
        }

        return $catalog->filter(fn (Program $program) => $program->prices->isNotEmpty())->values();
    }

    /** @return list<array{program_id: int, service_type: string, quantity: float, unit_price: float}> */
    private function selectionItems(): array
    {
        $allowedPrograms = $this->allowedPrograms();
        $allowedIds = $allowedPrograms->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedIds = collect($this->selectedProgramIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->validate([
            'selectedProgramIds' => 'required|array|min:1',
            'programQuantities' => 'array',
            'programServices' => 'array',
        ], [], ['selectedProgramIds' => 'البرامج المختارة']);

        if (array_diff($selectedIds, $allowedIds) !== []) {
            throw new \RuntimeException('لا يمكن اختيار برنامج خارج كتالوج الشراكة');
        }

        $items = [];
        foreach ($selectedIds as $programId) {
            $program = $allowedPrograms->firstWhere('id', $programId);
            $service = (string) ($this->programServices[$programId] ?? $program?->prices->first()?->service_type);
            $price = $program?->prices->firstWhere('service_type', $service);

            if (! $price) {
                throw new \RuntimeException('لا يوجد سعر نشط للخدمة المختارة');
            }

            $quantity = (float) ($this->programQuantities[$programId] ?? 1);
            if ($quantity < 0.01) {
                throw new \RuntimeException('يجب أن تكون الكمية أكبر من صفر');
            }

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
    private function applyItemsToQuote(Quote $quote, array $items): Quote
    {
        $service = app(QuoteService::class);
        $updated = $quote->status === Quote::STATUS_DRAFT
            ? $service->updateDraft($quote, $items)
            : $service->revise($quote, $items);

        $this->rememberSelection($updated, $items);

        return $updated;
    }

    private function syncQuoteFromSelection(): Quote
    {
        $items = $this->selectionItems();
        $partnership = $this->link->partnership()->firstOrFail();
        $partnership->allowedPrograms()->syncWithoutDetaching(
            collect($items)->pluck('program_id')->all()
        );

        $open = $partnership->quotes()
            ->whereNotIn('status', [Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED])
            ->orderByDesc('version')
            ->first();

        if ($open) {
            return $this->applyItemsToQuote($open, $items);
        }

        $created = app(QuoteService::class)->create($partnership, $items, advanceStage: false);
        $this->rememberSelection($created, $items);

        return $created;
    }

    /** @param list<array{program_id: int, service_type: string, quantity: float, unit_price: float}> $items */
    private function rememberSelection(Quote $quote, array $items): void
    {
        $this->log('portal.programs_selected', [
            'quote_id' => $quote->id,
            'program_ids' => array_column($items, 'program_id'),
            'quantities' => $this->programQuantities,
        ]);
    }

    private function diagnosisValue(DiagnosisQuestion $question): string
    {
        if ($question->key === 'audience' && $this->diagnosisAudience !== '') {
            return $this->diagnosisAudience;
        }
        if ($question->key === 'count' && $this->diagnosisCount !== '') {
            return $this->diagnosisCount;
        }
        if ($question->key === 'environment' && $this->diagnosisEnvironment !== '') {
            return $this->diagnosisEnvironment;
        }

        return trim((string) ($this->diagnosisAnswers[$question->id] ?? ''));
    }

    private function loadSavedDiagnosisAnswers(): void
    {
        $latest = app(DiagnosisQuestionService::class)->latestAnswers($this->link->partnership);
        $this->diagnosisAnswers = $latest;

        foreach (app(DiagnosisQuestionService::class)->activeQuestions() as $question) {
            $value = $latest[$question->id] ?? '';
            if ($value === '') {
                continue;
            }
            if ($question->key === 'audience') {
                $this->diagnosisAudience = $value;
            }
            if ($question->key === 'count') {
                $this->diagnosisCount = $value;
            }
            if ($question->key === 'environment') {
                $this->diagnosisEnvironment = $value;
            }
        }
    }

    private function scopedQuote(int $quoteId): Quote
    {
        return Quote::where('partnership_id', $this->link->partnership_id)->findOrFail($quoteId);
    }

    private function scopedScheduleItem(int $scheduleId): ContractPaymentSchedule
    {
        return ContractPaymentSchedule::query()
            ->whereHas('contract', fn ($q) => $q->where('partnership_id', $this->link->partnership_id))
            ->findOrFail($scheduleId);
    }

    private function scopedContract(int $contractId): PartnershipContract
    {
        return PartnershipContract::query()
            ->where('partnership_id', $this->link->partnership_id)
            ->findOrFail($contractId);
    }
}
