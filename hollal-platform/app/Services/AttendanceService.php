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
                'check_in' => hollal_time($record->check_in_at),
                'check_out' => hollal_time($record->check_out_at),
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

    /** ATT-3 — scan site barcode. Time: O(1) */
    public function checkInViaBarcode(User $employee, string $token): AttendanceRecord
    {
        $expected = (string) Setting::get('attendance.site_barcode_token', '');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            throw new \InvalidArgumentException('باركود المقر غير صالح');
        }

        $record = $this->checkIn($employee);
        $record->forceFill([
            'source' => 'باركود',
            'late_minutes' => $this->latenessMinutes($record),
        ])->save();

        return $record->fresh();
    }

    /** ATT-3 — field work pending manager approval. */
    public function startFieldWork(User $employee, string $location, ?string $proofPath = null): AttendanceRecord
    {
        $this->assertEnabled($employee);
        if (! $employee->profile?->is_field_worker) {
            throw new \InvalidArgumentException('الموظف غير مُعلَّم كميداني');
        }

        return AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            [
                'check_in_at' => now(),
                'type' => 'تكليف خارجي',
                'source' => 'عن_بعد',
                'field_location' => $location,
                'field_proof_path' => $proofPath,
                'approval_status' => 'بانتظار',
                'declared_by' => $employee->id,
            ],
        );
    }

    public function approveFieldWork(AttendanceRecord $record, User $manager): AttendanceRecord
    {
        $record->forceFill(['approval_status' => 'معتمد'])->save();

        return $record;
    }

    /**
     * ATT-1 — import CSV: fingerprint_id,date,check_in,check_out
     * Time: O(rows) | Space: O(1)
     *
     * @return array{import_id: int, rows: int}
     */
    public function importCsv(string $absolutePath, User $uploader): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('تعذر فتح ملف الاستيراد');
        }

        $count = 0;
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }
            [$fingerprint, $date, $checkIn] = $row;
            $checkOut = $row[3] ?? null;
            $profile = \App\Models\EmployeeProfile::query()->where('fingerprint_id', trim((string) $fingerprint))->first();
            if (! $profile) {
                continue;
            }
            $inAt = Carbon::parse(trim((string) $date).' '.trim((string) $checkIn));
            $record = AttendanceRecord::updateOrCreate(
                ['employee_id' => $profile->user_id, 'date' => $inAt->toDateString()],
                [
                    'check_in_at' => $inAt,
                    'check_out_at' => $checkOut ? Carbon::parse(trim((string) $date).' '.trim((string) $checkOut)) : null,
                    'type' => 'حضور',
                    'source' => 'بصمة',
                    'declared_by' => $uploader->id,
                ],
            );
            $late = $this->latenessMinutes($record);
            $hours = null;
            if ($record->check_in_at && $record->check_out_at) {
                $hours = round(($record->check_out_at->getTimestamp() - $record->check_in_at->getTimestamp()) / 3600, 2);
            }
            $record->forceFill(['late_minutes' => $late, 'work_hours' => $hours])->save();
            $count++;
        }
        fclose($handle);

        $import = \App\Models\AttendanceImport::create([
            'file_path' => $absolutePath,
            'rows_count' => $count,
            'uploaded_by' => $uploader->id,
        ]);

        return ['import_id' => $import->id, 'rows' => $count];
    }
}
