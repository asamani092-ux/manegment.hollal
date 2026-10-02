<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePayImpact extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'leave_request_id', 'month', 'unpaid_days', 'partial_days', 'partial_pct',
    ];
}
