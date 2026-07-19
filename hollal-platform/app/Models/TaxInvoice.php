<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 04-B7 — issued tax invoice. Totals are written once at issue time from the
 * line items and never edited afterwards (an invoice is corrected by a note).
 */
class TaxInvoice extends Model
{
    use SoftDeletes;

    public const TYPE_STANDARD = 'ضريبية';

    public const TYPE_SIMPLIFIED = 'مبسطة';

    public const MODE_INTERNAL = 'داخلي';

    public const MODE_EXTERNAL = 'خارجي';

    public const SOURCE_PAYMENT = 'دفعة';

    public const SOURCE_MANUAL = 'يدوي';

    /** @var list<string> */
    protected $fillable = [
        'sequence', 'number', 'invoice_type', 'mode', 'seller_name', 'seller_vat_number',
        'buyer_name', 'buyer_vat_number', 'organization_id', 'source_type', 'source_id',
        'subtotal', 'vat_total', 'total', 'currency', 'qr_payload', 'issued_at', 'issued_by',
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

    /** @return HasMany<TaxInvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TaxInvoiceItem::class);
    }

    /** @return HasMany<TaxInvoiceNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(TaxInvoiceNote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** True when the stored totals still equal the sum of the line items. */
    public function totalsMatchItems(): bool
    {
        $items = $this->items()->get();

        return round((float) $this->subtotal, 2) === round((float) $items->sum('line_subtotal'), 2)
            && round((float) $this->vat_total, 2) === round((float) $items->sum('line_vat'), 2)
            && round((float) $this->total, 2) === round((float) $items->sum('line_total'), 2);
    }
}
