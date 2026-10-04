<?php

namespace App\Events;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;

class LeavePayImpactsRecorded
{
    use Dispatchable;

    public function __construct(public LeaveRequest $leave) {}
}
