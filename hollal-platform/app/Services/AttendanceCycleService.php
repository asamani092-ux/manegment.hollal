<?php

namespace App\Services;

use App\Models\AttendanceCycle;
use App\Models\AttendanceCycleDay;
use App\Models\AttendanceRecord;
use App\Models\ExcuseRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly attendance cycle. Time: O(days) | Space: O(days).
 */
class AttendanceCycleService
{
    public function __construct(private AttendanceDeductionService $deductions) {}

    public function open(string $month): AttendanceCycle
    {
        return AttendanceCycle::query()->firstOrCreate(
            ['month' => $month],
            ['status' => 'open']
        );
    }

    public function syncDay(AttendanceCycle $cycle, User $employee, string $date, int $rawLate = 0): AttendanceCycleDay
    {
        $leave = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('type', 'إجازة')
            ->exists();

        $excuseMinutes = (int) ExcuseRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('status', 'approved')
            ->sum('minutes');

        $chargeable = $leave ? 0 : max(0, $this->deductions->chargeableLateMinutes($rawLate) - $excuseMinutes);
        $status = $leave ? 'إجازة' : null;

        $approvedOvertime = (float) OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('status', 'approved')
            ->sum('hours');

        return AttendanceCycleDay::query()->updateOrCreate(
            [
                'attendance_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'date' => $date,
            ],
            [
                'status' => $status,
                'late_minutes' => $rawLate,
                'chargeable_late_minutes' => $chargeable,
                'overtime_hours' => $approvedOvertime,
            ]
        );
    }

    public function setStatus(AttendanceCycleDay $day, string $status, ?string $reason = null): AttendanceCycleDay
    {
        $day->update([
            'status' => $status,
            'correction_reason' => $reason,
        ]);

        return $day->fresh();
    }

    public function close(AttendanceCycle $cycle, User $by): AttendanceCycle
    {
        $missing = $cycle->days()->whereNull('status')->exists();
        if ($missing || $cycle->days()->count() === 0) {
            throw new \RuntimeException('لا يمكن إقفال الدورة وفيها أيام بلا حالة');
        }

        $cycle->update([
            'status' => 'closed',
            'approved_by' => $by->id,
            'closed_at' => now(),
        ]);
        event(new \App\Events\AttendanceCycleClosed($cycle->fresh()));

        return $cycle->fresh();
    }

    public function reopen(AttendanceCycle $cycle, string $reason): AttendanceCycle
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب إعادة الفتح مطلوب');
        }
        $cycle->update(['status' => 'open', 'reopened_reason' => $reason, 'closed_at' => null]);
        $cycle->days()->where('downstream_decided', false)->update(['status' => null]);

        return $cycle->fresh();
    }

    public function approveOvertime(OvertimeRequest $request, User $actor): OvertimeRequest
    {
        $done = app(\App\Services\Approval\ApprovalEngine::class)->gate(
            'overtime',
            $request->id,
            $request->employee_id,
            (float) $request->hours,
            $actor,
        );
        if ($done === false) {
            return $request->fresh();
        }
        $request->update(['status' => 'approved']);

        return $request->fresh();
    }

    public function approveExcuse(ExcuseRequest $request, User $actor): ExcuseRequest
    {
        $done = app(\App\Services\Approval\ApprovalEngine::class)->gate(
            'excuse',
            $request->id,
            $request->employee_id,
            (float) $request->minutes,
            $actor,
        );
        if ($done === false) {
            return $request->fresh();
        }
        $request->update(['status' => 'approved']);

        return $request->fresh();
    }

    public function overtimeAmount(float $hourlyWage, float $hours, bool $approved): float
    {
        if (! $approved || $hours <= 0) {
            return 0.0;
        }
        $formula = (string) \App\Support\Setting::get('hr.overtime.rate_formula', 'hourly_plus_50');
        $rate = $formula === 'hourly_plus_50' ? $hourlyWage * 1.5 : $hourlyWage;

        return round($rate * $hours, 2);
    }
}
