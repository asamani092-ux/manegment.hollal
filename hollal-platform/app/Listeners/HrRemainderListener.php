<?php

namespace App\Listeners;

use App\Events\AttendanceCycleClosed;
use App\Events\LeavePayImpactsRecorded;
use App\Events\ViolationApplied;
use App\Services\PayrollAdjustmentService;
use App\Services\ViolationService;

/**
 * Turns closed cycles, leave impacts, and applied violations into proposed lines.
 * Time: O(n) source rows | Space: O(1).
 */
class HrRemainderListener
{
    public function onCycleClosed(AttendanceCycleClosed $event): void
    {
        app(ViolationService::class)->suggestFromCycle($event->cycle);
        app(PayrollAdjustmentService::class)->proposeFromCycle($event->cycle);
    }

    public function onViolationApplied(ViolationApplied $event): void
    {
        app(PayrollAdjustmentService::class)->proposeFromViolation($event->violation);
    }

    public function onLeaveImpacts(LeavePayImpactsRecorded $event): void
    {
        app(PayrollAdjustmentService::class)->proposeFromLeave($event->leave);
    }
}
