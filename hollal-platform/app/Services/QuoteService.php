<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Partnership;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\Quote;
use App\Models\User;
use App\Support\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

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
    public function create(Partnership $partnership, array $items, float $discount = 0, ?User $author = null): Quote
    {
        return DB::transaction(function () use ($partnership, $items, $discount, $author) {
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

    public function approve(Quote $quote, User $approver): Quote
    {
        $quote->forceFill([
            'status' => Quote::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $quote;
    }

    public function send(Quote $quote): Quote
    {
        if ($quote->status !== Quote::STATUS_APPROVED) {
            throw new \RuntimeException('لا يُرسل العرض قبل اعتماده داخليًا');
        }

        $quote->forceFill(['status' => Quote::STATUS_SENT, 'sent_at' => now()])->save();

        return $quote;
    }

    public function accept(Quote $quote): Quote
    {
        $quote->forceFill(['status' => Quote::STATUS_ACCEPTED, 'accepted_at' => now()])->save();

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
        $company = CompanyProfile::current();

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

        $html = '<div dir="rtl" style="font-family: dejavu sans;">'
            .'<h2>عرض سعر — نسخة '.(int) $quote->version.'</h2>'
            .'<p>'.e($company->name).' — الرقم الضريبي: '.e((string) $company->tax_number).'</p>'
            .'<p>الجهة: '.e($quote->partnership->organization?->name ?? $quote->partnership->entity_name ?? '—').'</p>'
            .'<table border="1" cellspacing="0" cellpadding="4" width="100%">'
            .'<thead><tr><th>البند</th><th>الخدمة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>'
            .'<p>المجموع: '.number_format((float) $quote->subtotal, 2).'</p>'
            .'<p>الخصم: '.number_format((float) $quote->discount, 2).'</p>'
            .'<p>الضريبة ('.number_format((float) $quote->tax_rate * 100, 2).'%): '.number_format((float) $quote->tax_total, 2).'</p>'
            .'<p><strong>الإجمالي شامل الضريبة: '.number_format((float) $quote->total, 2).'</strong></p>'
            .'</div>';

        return Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'dejavu sans')->output();
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
