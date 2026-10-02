<?php

namespace App\Events;

use App\Models\AttendanceCycle;
use Illuminate\Foundation\Events\Dispatchable;

class AttendanceCycleClosed
{
    use Dispatchable;

    public function __construct(public AttendanceCycle $cycle) {}
}
