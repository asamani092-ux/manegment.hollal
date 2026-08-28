<?php

namespace App\Services;

use App\Models\AttendanceCycleApproval;
use App\Models\AttendanceManualIndicator;
use App\Models\AttendanceRecord;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Carbon;

/**
 * ATT-2/ATT-4 — attendance cycle window and payroll deduction formulas.
 * Manual late-hours / absence indicators (no file) feed the same formulas;
 * final SAR amounts are never entered by hand.
 * Time: O(employees × days) | Space: O(employees)
 */
class AttendanceDeductionService
{
    /** @return array{from: Carbon, to: Carbon} */
    public function currentCycle(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $startDay = max(1, min(28, (int) Setting::get('attendance.cycle_start_day', 25)));

        if ($asOf->day >= $startDay) {
            $from = $asOf->copy()->day($startDay)->startOfDay();
            $to = $asOf->copy()->addMonthNoOverflow()->day($startDay)->subDay()->endOfDay();
        } else {
            $from = $asOf->copy()->subMonthNoOverflow()->day($startDay)->startOfDay();
            $to = $asOf->copy()->day($startDay)->subDay()->endOfDay();
        }

        return ['from' => $from, 'to' => $to];
    }

    public function cycleDays(): int
    {
        return max(1, (int) Setting::get('attendance.cycle_days', 30));
    }

    public function dailyBaseHours(): float
    {
        return max(1.0, (float) Setting::get('hr.daily_base_hours', 8));
    }

    public function graceMinutes(): int
    {
        return max(0, (int) Setting::get('attendance.grace_minutes', 15));
    }

    public function absenceMultiplier(): float
    {
        return max(0.0, (float) Setting::get('attendance.absence_multiplier', 1.5));
    }

    public function absencePolicy(): string
    {
        return (string) Setting::get('attendance.absence_policy', 'ثابت');
    }

    /**
     * Chargeable late minutes: delay beyond grace counts from the first minute (full delay, not delay−grace).
     */
    public function chargeableLateMinutes(int $rawLateMinutes): int
    {
        $grace = $this->graceMinutes();
        if ($rawLateMinutes <= $grace) {
            return 0;
        }

        return $rawLateMinutes;
    }

    public function dayValue(float $salary): float
    {
        return round($salary / $this->cycleDays(), 4);
    }

    public function hourValue(float $salary): float
    {
        return round($this->dayValue($salary) / $this->dailyBaseHours(), 4);
    }

    /**
     * Deduction from late hours using settings formulas (no manual SAR entry).
     */
    public function lateDeductionFromHours(float $lateHours, float $salary): float
    {
        return round(max(0, $lateHours) * $this->hourValue($salary), 2);
    }

    /**
     * Deduction from absence days using settings multiplier.
     */
    public function absenceDeductionFromDays(int $absenceDays, float $salary): float
    {
        return round(max(0, $absenceDays) * $this->absenceMultiplier() * $this->dayValue($salary), 2);
    }

    /**
     * Upsert HR manual indicators for an employee in a cycle (hours/days only).
     */
    public function saveManualIndicator(
        User $employee,
        Carbon $from,
        Carbon $to,
        float $lateHours,
        int $absenceDays,
        User $actor,
        ?string $notes = null,
    ): AttendanceManualIndicator {
        return AttendanceManualIndicator::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'cycle_from' => $from->toDateString(),
                'cycle_to' => $to->toDateString(),
            ],
            [
                'late_hours' => max(0, $lateHours),
                'absence_days' => max(0, $absenceDays),
                'notes' => $notes,
                'entered_by' => $actor->id,
            ],
        );
    }

    /**
     * @return array{employee_id: int, name: string, present_days: int, absence_days: int, late_minutes: int, chargeable_late_minutes: int, overtime_hours: float, late_deduction: float, absence_deduction: float, total_deduction: float, source: string}
     */
    public function employeeCycleSummary(User $employee, Carbon $from, Carbon $to, float $salary): array
    {
        $manual = AttendanceManualIndicator::query()
            ->where('employee_id', $employee->id)
            ->whereDate('cycle_from', $from->toDateString())
            ->whereDate('cycle_to', $to->toDateString())
            ->first();

        if ($manual) {
            $lateHours = (float) $manual->late_hours;
            $absenceDays = (int) $manual->absence_days;
            $lateDeduction = $this->lateDeductionFromHours($lateHours, $salary);
            $absenceDeduction = $this->absenceDeductionFromDays($absenceDays, $salary);

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'present_days' => 0,
                'absence_days' => $absenceDays,
                'late_minutes' => (int) round($lateHours * 60),
                'chargeable_late_minutes' => (int) round($lateHours * 60),
                'overtime_hours' => 0.0,
                'late_deduction' => $lateDeduction,
                'absence_deduction' => $absenceDeduction,
                'total_deduction' => round($lateDeduction + $absenceDeduction, 2),
                'source' => 'يدوي',
            ];
        }

        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $presentDays = 0;
        $lateMinutes = 0;
        $attendance = app(AttendanceService::class);

        foreach ($records as $record) {
            if (in_array($record->approval_status, ['مرفوض'], true)) {
                continue;
            }
            if ($record->type === 'انقطاع') {
                continue;
            }
            if ($record->check_in_at || in_array($record->type, ['عن بعد', 'تكليف خارجي'], true) || $record->source === 'عن_بعد') {
                $presentDays++;
            }
            $raw = (int) ($record->late_minutes ?: $attendance->latenessMinutes($record));
            $lateMinutes += $this->chargeableLateMinutes($raw);
        }

        $workingDays = $this->workingDaysInRange($from, $to);
        $absenceDays = max(0, $workingDays - $presentDays);
        $lateHours = $lateMinutes / 60;
        $lateDeduction = $this->lateDeductionFromHours($lateHours, $salary);
        $absenceDeduction = $this->absenceDeductionFromDays($absenceDays, $salary);

        return [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'present_days' => $presentDays,
            'absence_days' => $absenceDays,
            'late_minutes' => $lateMinutes,
            'chargeable_late_minutes' => $lateMinutes,
            'overtime_hours' => 0.0,
            'late_deduction' => $lateDeduction,
            'absence_deduction' => $absenceDeduction,
            'total_deduction' => round($lateDeduction + $absenceDeduction, 2),
            'source' => 'سجلات',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cycleReport(Carbon $from, Carbon $to): array
    {
        $employees = User::query()
            ->where('attendance_enabled', true)
            ->where('is_active', true)
            ->with('profile')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($employees as $employee) {
            $salary = (float) \App\Models\SalaryComponent::query()
                ->where('employee_id', $employee->id)
                ->where('type', \App\Models\SalaryComponent::TYPE_BASE)
                ->sum('amount');

            $rows[] = $this->employeeCycleSummary($employee, $from, $to, $salary);
        }

        return $rows;
    }

    public function approveCycle(Carbon $from, Carbon $to, User $approver): AttendanceCycleApproval
    {
        $snapshot = $this->cycleReport($from, $to);

        return AttendanceCycleApproval::updateOrCreate(
            ['cycle_from' => $from->toDateString(), 'cycle_to' => $to->toDateString()],
            [
                'status' => AttendanceCycleApproval::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'snapshot' => $snapshot,
            ],
        );
    }

    /**
     * After approval: request correction with a required reason (HR).
     */
    public function requestCorrection(AttendanceCycleApproval $approval, string $reason, User $actor): AttendanceCycleApproval
    {
        if ($approval->status !== AttendanceCycleApproval::STATUS_APPROVED) {
            throw new \InvalidArgumentException('التصحيح متاح بعد اعتماد الدورة فقط');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new \InvalidArgumentException('سبب التصحيح إلزامي');
        }

        $approval->forceFill([
            'status' => AttendanceCycleApproval::STATUS_CORRECTION_PENDING,
            'correction_reason' => $reason,
            'correction_requested_by' => $actor->id,
            'correction_requested_at' => now(),
            'correction_approved_by' => null,
            'correction_approved_at' => null,
        ])->save();

        return $approval->fresh();
    }

    /**
     * Approve correction request → reopen cycle as draft for re-approval.
     * Requires hr.employees.update (enforced by caller).
     */
    public function approveCorrection(AttendanceCycleApproval $approval, User $approver): AttendanceCycleApproval
    {
        if ($approval->status !== AttendanceCycleApproval::STATUS_CORRECTION_PENDING) {
            throw new \InvalidArgumentException('لا يوجد طلب تصحيح معلّق');
        }

        $approval->forceFill([
            'status' => AttendanceCycleApproval::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
            'correction_approved_by' => $approver->id,
            'correction_approved_at' => now(),
        ])->save();

        return $approval->fresh();
    }

    /**
     * Apply approved cycle snapshot as deduction variables on a draft payroll run.
     * Time: O(rows) | Space: O(1)
     */
    public function applyApprovedToPayrollDraft(PayrollRun $run, AttendanceCycleApproval $approval): int
    {
        if (! $run->isEditable()) {
            throw new \InvalidArgumentException('يُطبَّق خصم الحضور على مسودة المسير فقط.');
        }
        if ($approval->status !== AttendanceCycleApproval::STATUS_APPROVED) {
            throw new \InvalidArgumentException('يُطبَّق الخصم من تقرير معتمد فقط');
        }

        $rows = $approval->snapshot ?? [];
        $applied = 0;
        $payroll = app(PayrollRunService::class);

        foreach ($rows as $row) {
            $amount = (float) ($row['total_deduction'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $item = $run->items()->where('employee_id', $row['employee_id'])->first();
            if (! $item) {
                continue;
            }
            $payroll->addVariable(
                $item,
                'خصم حضور الدورة',
                'من تقرير الحضور المعتمد '.$approval->cycle_from->toDateString().'–'.$approval->cycle_to->toDateString(),
                $amount,
                'deduction',
            );
            $applied++;
        }

        $run->forceFill([
            'cycle_from' => $approval->cycle_from,
            'cycle_to' => $approval->cycle_to,
        ])->save();

        return $applied;
    }

    private function workingDaysInRange(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            if (! $cursor->isFriday() && ! $cursor->isSaturday()) {
                $days++;
            }
            $cursor->addDay();
        }

        return max(1, $days);
    }
}
