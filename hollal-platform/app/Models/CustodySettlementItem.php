<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustodySettlementItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'custody_id',
        'description',
        'amount',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'category_id',
        'invoice_number',
        'invoice_date',
        'invoice_file',
        'vendor_name',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'invoice_date' => 'date',
        ];
    }

    /** @return BelongsTo<Custody, $this> */
    public function custody(): BelongsTo
    {
        return $this->belongsTo(Custody::class);
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * احتساب الضريبة والإجمالي من المبلغ قبل الضريبة.
     * Time: O(1) | Space: O(1)
     */
    public static function computeTax(float $amount, float $vatRate = 0.15): array
    {
        $amount = round($amount, 2);
        $vatRate = max(0.0, $vatRate);
        $vatAmount = round($amount * $vatRate, 2);

        return [
            'amount' => $amount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total_amount' => round($amount + $vatAmount, 2),
        ];
    }
}
