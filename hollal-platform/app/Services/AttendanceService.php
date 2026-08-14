<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 01-B4 — check-in/out for attendance-enabled employees. A day has a single
 * record; check-out updates the same row.
 *
 * Lateness uses org office start (attendance.office_start_time). Multi-shift
 * assignment is Phase B (see docs/plans/ATTENDANCE-BARCODE-DESIGN.md).
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
     * Office start HH:MM from settings (default 08:00).
     * Time: O(1) | Space: O(1)
     */
    public function officeStartTime(): string
    {
        $raw = (string) Setting::get('attendance.office_start_time', '08:00');

        return preg_match('/^\d{1,2}:\d{2}$/', $raw) ? $raw : '08:00';
    }

    /**
     * Minutes late vs office start for a check-in. 0 if on time / remote / missing.
     * Time: O(1) | Space: O(1)
     */
    public function latenessMinutes(AttendanceRecord $record, ?string $officeStart = null): int
    {
        if (! $record->check_in_at) {
            return 0;
        }

        $type = (string) ($record->type ?? '');
        if (in_array($type, ['عن بعد', 'تكليف خارجي', 'انقطاع'], true)) {
            return 0;
        }

        $start = $officeStart ?? $this->officeStartTime();
        [$h, $m] = array_map('intval', explode(':', $start));
        $expected = $record->check_in_at->copy()->setTime($h, $m, 0);
        $diff = $expected->diffInMinutes($record->check_in_at, false);

        return $diff > 0 ? (int) $diff : 0;
    }

    /**
     * Monthly attendance rows with lateness for print/report.
     * Time: O(n) records | Space: O(n)
     *
     * @return array{month: string, office_start: string, rows: list<array{date: string, employee: string, type: string, check_in: ?string, check_out: ?string, late_minutes: int}>}
     */
    public function monthlyReport(string $month, ?int $employeeId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $officeStart = $this->officeStartTime();

        $records = AttendanceRecord::query()
            ->select(['id', 'employee_id', 'date', 'check_in_at', 'check_out_at', 'type'])
            ->with('employee:id,name')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'date' => $record->date?->format('Y-m-d') ?? '',
                'employee' => $record->employee?->name ?? '—',
                'type' => (string) ($record->type ?? ''),
                'check_in' => $record->check_in_at?->format('H:i'),
                'check_out' => $record->check_out_at?->format('H:i'),
                'late_minutes' => $this->latenessMinutes($record, $officeStart),
            ];
        }

        return [
            'month' => $month,
            'office_start' => $officeStart,
            'rows' => $rows,
        ];
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
