<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 01-B4 — check-in/out for attendance-enabled employees. A day has a single
 * record; check-out updates the same row.
 */
class AttendanceService
{
    public function checkIn(User $employee, ?User $declaredBy = null): AttendanceRecord
    {
        $this->assertEnabled($employee);

        return AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            [
                'check_in_at' => now(),
                'type' => 'حضور',
                'declared_by' => ($declaredBy ?? $employee)->id,
            ],
        );
    }

    public function checkOut(User $employee, ?User $declaredBy = null): AttendanceRecord
    {
        $this->assertEnabled($employee);

        return AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            [
                'check_out_at' => now(),
                'declared_by' => ($declaredBy ?? $employee)->id,
            ],
        );
    }

    /**
     * Overtime hours in a calendar month: worked − (weekly_hours / 5) per day.
     * Time: O(d) days | Space: O(1)
     */
    public function overtimeHoursForMonth(User $employee, string $month, ?Collection $records = null): float
    {
        $weekly = (float) ($employee->profile?->weekly_hours ?? 40);
        $dailyExpected = $weekly > 0 ? $weekly / 5 : 8.0;

        $rows = $records ?? AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [
                Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString(),
                Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString(),
            ])
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            ->get(['check_in_at', 'check_out_at']);

        $hours = 0.0;
        foreach ($rows as $record) {
            $worked = ($record->check_out_at->getTimestamp() - $record->check_in_at->getTimestamp()) / 3600;
            $hours += max(0, $worked - $dailyExpected);
        }

        return round($hours, 2);
    }

    private function assertEnabled(User $employee): void
    {
        if (! $employee->attendance_enabled) {
            throw new \InvalidArgumentException('برنامج الحضور غير مُفعّل لهذا الموظف.');
        }
    }
}
