<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 04-B7 — credit (دائن) / debit (مدين) note. Always bound to the original
 * invoice; carries its own unbroken sequence.
 */
class TaxInvoiceNote extends Model
{
    use SoftDeletes;

    public const TYPE_CREDIT = 'دائن';

    public const TYPE_DEBIT = 'مدين';

    /** @var list<string> */
    protected $fillable = [
        'tax_invoice_id', 'sequence', 'number', 'note_type', 'reason',
        'subtotal', 'vat_total', 'total', 'qr_payload', 'issued_at', 'issued_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TaxInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TaxInvoice::class, 'tax_invoice_id');
    }
}
