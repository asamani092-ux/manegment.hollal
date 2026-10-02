<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'reference_item_id', 'period_year', 'entitled', 'used', 'reserved', 'adjusted',
    ];
}
