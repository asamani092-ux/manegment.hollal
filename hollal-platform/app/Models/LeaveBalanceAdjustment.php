<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalanceAdjustment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'reference_item_id', 'days', 'reason', 'created_by',
    ];
}
