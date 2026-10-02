<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'month', 'reference_item_id', 'amount', 'computed_amount',
        'status', 'reason', 'source_type', 'source_id',
    ];
}
