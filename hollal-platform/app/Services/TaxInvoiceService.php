<?php

namespace App\Services;

use App\Models\TaxInvoice;
use App\Models\TaxInvoiceItem;
use App\Models\TaxInvoiceNote;
use App\Models\User;
use App\Support\Setting;
use App\Support\TlvQr;
use Illuminate\Support\Facades\DB;

/**
 * 04-B7 — tax invoicing Phase A.
 *
 * Sequence: allocated inside a transaction under a row lock on
 * tax_invoice_sequences, so concurrent issues can never reuse or skip a number.
 * Totals: always computed from the line items — never accepted from the caller.
 */
class TaxInvoiceService
{
    public const SCOPE_INVOICE = 'invoice';

    public const SCOPE_NOTE = 'note';

    /**
     * Issue an invoice from raw line items.
     *
     * @param  list<array{description: string, quantity?: float|int, unit_price: float|int, vat_rate?: float}>  $items
     * @param  array{name: string, vat_number?: ?string, organization_id?: ?int}  $buyer
     */
    public function issue(
        array $items,
        array $buyer,
        ?User $issuer = null,
        string $sourceType = TaxInvoice::SOURCE_MANUAL,
        ?int $sourceId = null,
        ?string $invoiceType = null,
    ): TaxInvoice {
        if ($items === []) {
            throw new \InvalidArgumentException('لا يمكن إصدار فاتورة بدون بنود');
        }

        return DB::transaction(function () use ($items, $buyer, $issuer, $sourceType, $sourceId, $invoiceType) {
            $sequence = $this->nextSequence(self::SCOPE_INVOICE);
            $lines = $this->buildLines($items);

            $invoice = TaxInvoice::create([
                'sequence' => $sequence,
                'number' => $this->formatNumber('INV', $sequence),
                'invoice_type' => $invoiceType ?? TaxInvoice::TYPE_STANDARD,
                'mode' => $this->mode(),
                'seller_name' => (string) Setting::get('finance.tax.seller_name', 'مؤسسة حلّل'),
                'seller_vat_number' => (string) Setting::get('finance.tax.seller_vat_number', '300000000000003'),
                'buyer_name' => $buyer['name'],
                'buyer_vat_number' => $buyer['vat_number'] ?? null,
                'organization_id' => $buyer['organization_id'] ?? null,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'subtotal' => $lines['subtotal'],
                'vat_total' => $lines['vat_total'],
                'total' => $lines['total'],
                'currency' => (string) Setting::get('finance.currency', 'SAR'),
                'issued_at' => now(),
                'issued_by' => $issuer?->id,
            ]);

            foreach ($lines['items'] as $line) {
                TaxInvoiceItem::create(['tax_invoice_id' => $invoice->id] + $line);
            }

            $invoice->qr_payload = TlvQr::encode(
                $invoice->seller_name,
                (string) $invoice->seller_vat_number,
                $invoice->issued_at->toIso8601String(),
                number_format((float) $invoice->total, 2, '.', ''),
                number_format((float) $invoice->vat_total, 2, '.', ''),
            );
            $invoice->save();

            return $invoice->fresh(['items']);
        });
    }

    /**
     * Issue an invoice for a confirmed partnership payment (hook used by 05-B6).
     * Idempotent: a payment can only ever produce one invoice.
     */
    public function issueFromPayment(
        int $paymentId,
        float $amount,
        string $buyerName,
        ?string $buyerVatNumber = null,
        ?int $organizationId = null,
        ?User $issuer = null,
        ?string $description = null,
    ): TaxInvoice {
        $existing = TaxInvoice::query()
            ->where('source_type', TaxInvoice::SOURCE_PAYMENT)
            ->where('source_id', $paymentId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->issue(
            items: [[
                'description' => $description ?? 'دفعة شراكة رقم '.$paymentId,
                'quantity' => 1,
                'unit_price' => $amount,
            ]],
            buyer: [
                'name' => $buyerName,
                'vat_number' => $buyerVatNumber,
                'organization_id' => $organizationId,
            ],
            issuer: $issuer,
            sourceType: TaxInvoice::SOURCE_PAYMENT,
            sourceId: $paymentId,
        );
    }

    /**
     * Issue a credit (دائن) or debit (مدين) note against an existing invoice.
     */
    public function issueNote(
        TaxInvoice $invoice,
        string $noteType,
        float $amount,
        string $reason,
        ?User $issuer = null,
    ): TaxInvoiceNote {
        if (! in_array($noteType, [TaxInvoiceNote::TYPE_CREDIT, TaxInvoiceNote::TYPE_DEBIT], true)) {
            throw new \InvalidArgumentException('نوع الإشعار غير صالح');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('قيمة الإشعار يجب أن تكون أكبر من صفر');
        }

        return DB::transaction(function () use ($invoice, $noteType, $amount, $reason, $issuer) {
            $sequence = $this->nextSequence(self::SCOPE_NOTE);
            $rate = $this->vatRate();
            $subtotal = round($amount, 2);
            $vat = round($subtotal * $rate, 2);
            $total = round($subtotal + $vat, 2);

            $note = TaxInvoiceNote::create([
                'tax_invoice_id' => $invoice->id,
                'sequence' => $sequence,
                'number' => $this->formatNumber($noteType === TaxInvoiceNote::TYPE_CREDIT ? 'CRN' : 'DBN', $sequence),
                'note_type' => $noteType,
                'reason' => $reason,
                'subtotal' => $subtotal,
                'vat_total' => $vat,
                'total' => $total,
                'issued_at' => now(),
                'issued_by' => $issuer?->id,
            ]);

            $note->qr_payload = TlvQr::encode(
                $invoice->seller_name,
                (string) $invoice->seller_vat_number,
                $note->issued_at->toIso8601String(),
                number_format($total, 2, '.', ''),
                number_format($vat, 2, '.', ''),
            );
            $note->save();

            return $note;
        });
    }

    /** Configured invoicing mode: internal (داخلي) or external (خارجي). */
    public function mode(): string
    {
        $mode = (string) Setting::get('finance.tax.mode', TaxInvoice::MODE_INTERNAL);

        return in_array($mode, [TaxInvoice::MODE_INTERNAL, TaxInvoice::MODE_EXTERNAL], true)
            ? $mode
            : TaxInvoice::MODE_INTERNAL;
    }

    public function vatRate(): float
    {
        return (float) Setting::get('finance.tax_rate', 0.15);
    }

    /**
     * Allocate the next number in an unbroken sequence. Must run inside a
     * transaction: the sequence row is locked for the duration.
     */
    private function nextSequence(string $scope): int
    {
        DB::table('tax_invoice_sequences')->insertOrIgnore([
            'scope' => $scope,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('tax_invoice_sequences')->where('scope', $scope)->lockForUpdate()->first();
        $next = (int) $row->last_number + 1;

        DB::table('tax_invoice_sequences')
            ->where('scope', $scope)
            ->update(['last_number' => $next, 'updated_at' => now()]);

        return $next;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, subtotal: float, vat_total: float, total: float}
     */
    private function buildLines(array $items): array
    {
        $defaultRate = $this->vatRate();
        $lines = [];
        $subtotal = 0.0;
        $vatTotal = 0.0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) $item['unit_price'];
            $rate = (float) ($item['vat_rate'] ?? $defaultRate);

            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineVat = round($lineSubtotal * $rate, 2);
            $lineTotal = round($lineSubtotal + $lineVat, 2);

            $lines[] = [
                'description' => (string) $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => $rate,
                'line_subtotal' => $lineSubtotal,
                'line_vat' => $lineVat,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineSubtotal;
            $vatTotal += $lineVat;
        }

        $subtotal = round($subtotal, 2);
        $vatTotal = round($vatTotal, 2);

        return [
            'items' => $lines,
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => round($subtotal + $vatTotal, 2),
        ];
    }

    private function formatNumber(string $prefix, int $sequence): string
    {
        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
