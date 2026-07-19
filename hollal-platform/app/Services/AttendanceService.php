<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;

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

    private function assertEnabled(User $employee): void
    {
        if (! $employee->attendance_enabled) {
            throw new \InvalidArgumentException('برنامج الحضور غير مُفعّل لهذا الموظف.');
        }
    }
}
