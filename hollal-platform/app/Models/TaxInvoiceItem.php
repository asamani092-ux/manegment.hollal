<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxInvoiceItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tax_invoice_id', 'description', 'quantity', 'unit_price', 'vat_rate',
        'line_subtotal', 'line_vat', 'line_total',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'line_subtotal' => 'decimal:2',
            'line_vat' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<TaxInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TaxInvoice::class, 'tax_invoice_id');
    }
}
