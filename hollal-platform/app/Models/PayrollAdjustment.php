<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'month', 'reference_item_id', 'amount', 'computed_amount',
        'status', 'reason', 'source_type', 'source_id', 'decided_by',
        'payroll_run_item_id', 'deferred_to',
    ];

    /** @return BelongsTo<ReferenceItem, $this> */
    public function referenceItem(): BelongsTo
    {
        return $this->belongsTo(ReferenceItem::class);
    }
}
