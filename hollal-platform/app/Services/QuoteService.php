<?php

namespace App\Services;

use App\Models\Partnership;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\QuoteAwaitingExecutiveApproval;
use App\Support\Setting;
use App\Support\PdfArabic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 05-B3 — quote builder. Unit prices come from the program card, the tax rate
 * from platform settings, and every total is computed — never typed in.
 * Revising an issued quote produces a new version; the old one is preserved.
 */
class QuoteService
{
    /**
     * @param  list<array{program_id?: ?int, service_type: string, description?: string, quantity?: float|int, unit_price?: float|int}>  $items
     */
    public function create(Partnership $partnership, array $items, float $discount = 0, ?User $author = null, bool $advanceStage = false): Quote
    {
        return DB::transaction(function () use ($partnership, $items, $discount, $author, $advanceStage) {
            $version = (int) $partnership->quotes()->max('version') + 1;

            $quote = Quote::create([
                'partnership_id' => $partnership->id,
                'version' => $version,
                'status' => Quote::STATUS_DRAFT,
                'discount' => $discount,
                'tax_rate' => $this->taxRate(),
            ]);

            $this->fillItems($quote, $items);
            $this->recalculate($quote);

            if ($advanceStage) {
                app(PartnershipPipelineService::class)->advanceIfBefore(
                    $partnership,
                    Partnership::STAGE_QUOTE,
                    $author,
                    'إصدار عرض سعر',
                );
            }

            return $quote->fresh(['items']);
        });
    }

    /**
     * Revise a quote: a brand-new version linked back to the one it replaces.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function revise(Quote $quote, array $items, ?float $discount = null, ?User $author = null): Quote
    {
        return DB::transaction(function () use ($quote, $items, $discount, $author) {
            $new = Quote::create([
                'partnership_id' => $quote->partnership_id,
                'version' => (int) Quote::where('partnership_id', $quote->partnership_id)->max('version') + 1,
                'supersedes_id' => $quote->id,
                'status' => Quote::STATUS_DRAFT,
                'discount' => $discount ?? (float) $quote->discount,
                'tax_rate' => $this->taxRate(),
            ]);

            $this->fillItems($new, $items);
            $this->recalculate($new);

            return $new->fresh(['items']);
        });
    }

    /**
     * Update a draft in place while the partner is still configuring the
     * catalog. Issued versions remain immutable and use revise() instead.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function updateDraft(Quote $quote, array $items): Quote
    {
        if ($quote->status !== Quote::STATUS_DRAFT) {
            throw new \RuntimeException('لا يمكن تعديل عرض غير مسودة');
        }

        return DB::transaction(function () use ($quote, $items) {
            $quote->items()->delete();
            $quote->forceFill([
                'tax_rate' => $this->taxRate(),
                'entity_notes' => null,
            ])->save();
            $this->fillItems($quote, $items);
            $this->recalculate($quote);

            return $quote->fresh(['items']);
        });
    }

    public function approve(Quote $quote, User $approver): Quote
    {
        if ($approver->can('partnerships.quotes.finalize')) {
            return $this->finalize($quote, $approver);
        }

        return $this->approveInternally($quote, $approver);
    }

    /** Time: O(r) recipients | Space: O(r) */
    public function approveInternally(Quote $quote, User $approver): Quote
    {
        if (! in_array($quote->status, [Quote::STATUS_DRAFT, Quote::STATUS_WITH_NOTES], true)) {
            throw new \RuntimeException('لا يُعتمد داخليًا إلا عرض مسودة أو بملاحظات');
        }

        $quote->forceFill([
            'status' => Quote::STATUS_PENDING_FINAL,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        $recipients = User::permission('partnerships.quotes.finalize')
            ->get()
            ->filter(fn (User $user) => $user->id !== $approver->id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new QuoteAwaitingExecutiveApproval($quote->fresh()));
        }

        return $quote;
    }

    /** Time: O(1) | Space: O(1) */
    public function finalize(Quote $quote, User $approver): Quote
    {
        if (! $approver->can('partnerships.quotes.finalize')) {
            throw new \RuntimeException('لا اعتماد نهائي بلا صلاحية الاعتماد النهائي');
        }
        if (! in_array($quote->status, [
            Quote::STATUS_DRAFT,
            Quote::STATUS_WITH_NOTES,
            Quote::STATUS_PENDING_FINAL,
        ], true)) {
            throw new \RuntimeException('لا يُعتمد نهائيًا إلا عرض بانتظار ذلك');
        }

        $quote->forceFill([
            'status' => Quote::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        $partnership = $quote->partnership()->firstOrFail();
        $partnership->enablePortalFeatures(['quotes']);
        app(PartnershipPipelineService::class)->advanceIfBefore(
            $partnership,
            Partnership::STAGE_QUOTE,
            $approver,
            'الاعتماد النهائي لعرض السعر',
        );

        return $quote;
    }

    public function returnToDraft(Quote $quote, User $returner, string $notes): Quote
    {
        if (! $returner->can('partnerships.quotes.finalize')) {
            throw new \RuntimeException('إرجاع العرض للمسودة من صاحب الاعتماد النهائي فقط');
        }

        $quote->forceFill([
            'status' => Quote::STATUS_DRAFT,
            'entity_notes' => $notes,
            'approved_by' => null,
            'approved_at' => null,
        ])->save();

        $quote->partnership->forceFill(['internal_approval_notes' => $notes])->save();

        return $quote;
    }

    public function send(Quote $quote): Quote
    {
        if ($quote->status !== Quote::STATUS_APPROVED) {
            throw new \RuntimeException('لا يُرسل العرض قبل اعتماده داخليًا');
        }

        $quote->forceFill(['status' => Quote::STATUS_SENT, 'sent_at' => now()])->save();

        app(PartnershipPipelineService::class)->advanceIfBefore(
            $quote->partnership,
            Partnership::STAGE_QUOTE,
            null,
            'إرسال عرض السعر للجهة',
        );

        return $quote;
    }

    public function accept(Quote $quote, ?string $extraNotes = null): Quote
    {
        if (! in_array($quote->status, [Quote::STATUS_APPROVED, Quote::STATUS_SENT], true)) {
            throw new \RuntimeException('لا يُقبل العرض قبل اعتماده نهائيًا');
        }

        $notes = trim((string) $quote->entity_notes);
        $extra = trim((string) $extraNotes);
        if ($extra !== '') {
            $notes = $notes === '' ? $extra : $notes."\n".$extra;
        }

        $quote->forceFill([
            'status' => Quote::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'entity_notes' => $notes !== '' ? $notes : $quote->entity_notes,
        ])->save();

        return $quote;
    }

    public function addNotes(Quote $quote, string $notes): Quote
    {
        $quote->forceFill(['status' => Quote::STATUS_WITH_NOTES, 'entity_notes' => $notes])->save();

        return $quote;
    }

    /** Unit price for a service from the program card (0 when not priced). */
    public function priceFor(?int $programId, string $serviceType): float
    {
        if (! $programId) {
            return 0.0;
        }

        return (float) (ProgramPrice::query()
            ->where('program_id', $programId)
            ->where('service_type', $serviceType)
            ->value('unit_price') ?? 0);
    }

    public function taxRate(): float
    {
        return (float) Setting::get('finance.tax_rate', 0.15);
    }

    /** Recompute subtotal, tax and total from the line items. */
    public function recalculate(Quote $quote): Quote
    {
        $subtotal = round((float) $quote->items()->sum('line_total'), 2);
        $net = round(max($subtotal - (float) $quote->discount, 0), 2);
        $tax = round($net * (float) $quote->tax_rate, 2);

        $quote->forceFill([
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => round($net + $tax, 2),
        ])->save();

        return $quote;
    }

    public function renderPdf(Quote $quote): string
    {
        $quote->loadMissing(['items.program', 'partnership.organization']);

        $rows = '';
        foreach ($quote->items as $item) {
            $rows .= '<tr>'
                .'<td>'.e($item->description).'</td>'
                .'<td>'.e($item->service_type).'</td>'
                .'<td>'.number_format((float) $item->quantity, 2).'</td>'
                .'<td>'.number_format((float) $item->unit_price, 2).'</td>'
                .'<td>'.number_format((float) $item->line_total, 2).'</td>'
                .'</tr>';
        }

        $body = '<p>الجهة: '.e($quote->partnership->organization?->name ?? $quote->partnership->entity_name ?? '—').'</p>'
            .'<table border="1" cellspacing="0" cellpadding="4" width="100%">'
            .'<thead><tr><th>البند</th><th>الخدمة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>'
            .'<p>المجموع: '.number_format((float) $quote->subtotal, 2).'</p>'
            .'<p>الخصم: '.number_format((float) $quote->discount, 2).'</p>'
            .'<p>الضريبة ('.number_format((float) $quote->tax_rate * 100, 2).'%): '.number_format((float) $quote->tax_total, 2).'</p>'
            .'<p><strong>الإجمالي شامل الضريبة: '.number_format((float) $quote->total, 2).'</strong></p>';

        return PdfArabic::render('عرض سعر — نسخة '.(int) $quote->version, $body, includeCr: true);
    }

    /** @param list<array<string, mixed>> $items */
    private function fillItems(Quote $quote, array $items): void
    {
        foreach ($items as $item) {
            $programId = $item['program_id'] ?? null;
            $serviceType = (string) $item['service_type'];
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = isset($item['unit_price'])
                ? (float) $item['unit_price']
                : $this->priceFor($programId, $serviceType);

            $quote->items()->create([
                'program_id' => $programId,
                'service_type' => $serviceType,
                'description' => $item['description']
                    ?? (($programId ? (Program::find($programId)?->name.' — ') : '').$serviceType),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
            ]);
        }
    }
}
