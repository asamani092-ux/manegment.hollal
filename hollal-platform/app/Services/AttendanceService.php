<?php

namespace App\Services;

use App\Models\AttendanceColumnMap;
use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 01-B4 — check-in/out for attendance-enabled employees. A day has a single
 * record; check-out updates the same row.
 *
 * Path-2 (shifts): lateness uses the employee's assigned WorkShift (start +
 * grace_minutes). Falls back to org office start when no shift is assigned.
 *
 * Day types (path-2): حضور · عن بعد · ميداني. Remote/field stay pending until
 * the direct manager (or HR with hr.employees.update) approves/rejects.
 *
 * Path-1 import (monthly file): a single upload *replaces* fingerprint
 * movements for that calendar month. When an imported fingerprint row
 * conflicts with a platform punch for the same employee+date, the imported
 * fingerprint wins. Platform punches never overwrite an existing بصمة row.
 */
class AttendanceService
{
    public const TYPE_PRESENT = 'حضور';

    public const TYPE_REMOTE = 'عن بعد';

    public const TYPE_FIELD = 'ميداني';

    /** @var list<string> */
    public const DAY_TYPES = [self::TYPE_PRESENT, self::TYPE_REMOTE, self::TYPE_FIELD];

    public const APPROVAL_PENDING = 'بانتظار';

    public const APPROVAL_APPROVED = 'معتمد';

    public const APPROVAL_REJECTED = 'مرفوض';

    public const SOURCE_MANUAL = 'يدوي';

    public const SOURCE_FINGERPRINT = 'بصمة';

    public const SOURCE_BARCODE = 'باركود';

    public const SOURCE_REMOTE = 'عن_بعد';

    /** Logical roles shown in Arabic mapping UI (internal keys only). */
    public const MAP_FINGERPRINT = 'fingerprint';

    public const MAP_DATE = 'date';

    public const MAP_CHECK_IN = 'check_in';

    public const MAP_CHECK_OUT = 'check_out';

    /** @return array<string, string> role => Arabic label */
    public static function columnRoleLabels(): array
    {
        return [
            self::MAP_FINGERPRINT => 'معرّف البصمة',
            self::MAP_DATE => 'التاريخ',
            self::MAP_CHECK_IN => 'وقت الحضور',
            self::MAP_CHECK_OUT => 'وقت الانصراف',
        ];
    }

    public function checkIn(User $employee, ?User $declaredBy = null): AttendanceRecord
    {
        $this->assertEnabled($employee);
        $this->assertNotFingerprintLocked($employee, today()->toDateString());
        $this->assertShiftAllowsToday($employee);

        $record = AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            [
                'check_in_at' => now(),
                'type' => self::TYPE_PRESENT,
                'source' => self::SOURCE_MANUAL,
                'approval_status' => null,
                'declared_by' => ($declaredBy ?? $employee)->id,
            ],
        );

        $late = $this->latenessMinutes($record);
        $record->forceFill(['late_minutes' => $late])->save();

        return $record->fresh();
    }

    public function checkOut(User $employee, ?User $declaredBy = null): AttendanceRecord
    {
        $this->assertEnabled($employee);
        $this->assertNotFingerprintLocked($employee, today()->toDateString());

        $record = AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            [
                'check_out_at' => now(),
                'declared_by' => ($declaredBy ?? $employee)->id,
            ],
        );

        if ($record->check_in_at && $record->check_out_at) {
            $hours = round(
                ($record->check_out_at->getTimestamp() - $record->check_in_at->getTimestamp()) / 3600,
                2
            );
            // earlyLeaveMinutes is computed on read for display; no auto-deduction.
            $record->forceFill(['work_hours' => $hours])->save();
        }

        return $record->fresh();
    }

    /**
     * Declare day type. Remote/field stay pending manager (or HR) approval.
     */
    public function declareDayType(User $employee, string $type, ?string $notes = null, ?User $declaredBy = null): AttendanceRecord
    {
        $this->assertEnabled($employee);
        if (! in_array($type, self::DAY_TYPES, true)) {
            throw new \InvalidArgumentException('نوع اليوم غير صالح');
        }
        $this->assertNotFingerprintLocked($employee, today()->toDateString());

        $needsApproval = in_array($type, [self::TYPE_REMOTE, self::TYPE_FIELD], true);

        $payload = [
            'type' => $type,
            'notes' => $notes,
            'source' => $needsApproval ? self::SOURCE_REMOTE : self::SOURCE_MANUAL,
            'approval_status' => $needsApproval ? self::APPROVAL_PENDING : null,
            'declared_by' => ($declaredBy ?? $employee)->id,
        ];
        if ($needsApproval) {
            $payload['late_minutes'] = 0;
        }

        return AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            $payload,
        );
    }

    /**
     * Approve pending remote/field declaration. Actor must be direct manager or HR.
     */
    public function approveDayType(AttendanceRecord $record, User $actor): AttendanceRecord
    {
        $this->assertCanDecideDayType($record, $actor);
        if ($record->approval_status !== self::APPROVAL_PENDING) {
            throw new \InvalidArgumentException('السجل ليس بانتظار الاعتماد');
        }

        $record->forceFill(['approval_status' => self::APPROVAL_APPROVED])->save();

        return $record->fresh();
    }

    /**
     * Reject pending remote/field declaration.
     */
    public function rejectDayType(AttendanceRecord $record, User $actor): AttendanceRecord
    {
        $this->assertCanDecideDayType($record, $actor);
        if ($record->approval_status !== self::APPROVAL_PENDING) {
            throw new \InvalidArgumentException('السجل ليس بانتظار الاعتماد');
        }

        $record->forceFill(['approval_status' => self::APPROVAL_REJECTED])->save();

        return $record->fresh();
    }

    /** Whether actor may approve/reject this employee's remote/field day. */
    public function canDecideDayType(AttendanceRecord $record, User $actor): bool
    {
        if ($actor->can('hr.employees.update')) {
            return true;
        }

        $employee = $record->relationLoaded('employee')
            ? $record->employee
            : $record->employee()->first();

        return $employee && (int) $employee->manager_id === (int) $actor->id;
    }

    /**
     * Office start HH:MM from settings (default 08:00). Used when no shift.
     * Time: O(1) | Space: O(1)
     */
    public function officeStartTime(): string
    {
        $raw = (string) Setting::get('attendance.office_start_time', '08:00');

        return preg_match('/^\d{1,2}:\d{2}$/', $raw) ? $raw : '08:00';
    }

    /**
     * Resolve active shift for an employee (from profile).
     */
    public function shiftFor(User $employee): ?\App\Models\WorkShift
    {
        $employee->loadMissing('profile.workShift');
        $shift = $employee->profile?->workShift;

        return ($shift && $shift->is_active) ? $shift : null;
    }

    /**
     * Expected start HH:MM and grace minutes for lateness (shift or org default).
     *
     * @return array{start: string, end: ?string, grace: int, shift: ?\App\Models\WorkShift}
     */
    public function expectedStartFor(User $employee): array
    {
        $shift = $this->shiftFor($employee);
        if ($shift) {
            return [
                'start' => $shift->startHm(),
                'end' => $shift->endHm(),
                'grace' => max(0, (int) $shift->grace_minutes),
                'shift' => $shift,
            ];
        }

        return [
            'start' => $this->officeStartTime(),
            'end' => null,
            'grace' => 0,
            'shift' => null,
        ];
    }

    /**
     * Minutes late vs employee's shift start after grace (flexibility).
     * When a shift is assigned, its start+grace always win over org office_start.
     * Falls back to office start (or $fallbackStart) only when no shift.
     * 0 if on time / remote / field / missing punch.
     * Time: O(1) | Space: O(1)
     */
    public function latenessMinutes(AttendanceRecord $record, ?string $fallbackStart = null): int
    {
        if (! $record->check_in_at) {
            return 0;
        }

        $type = (string) ($record->type ?? '');
        if (in_array($type, [self::TYPE_REMOTE, self::TYPE_FIELD, 'تكليف خارجي', 'انقطاع'], true)) {
            return 0;
        }

        $employee = $record->relationLoaded('employee')
            ? $record->employee
            : User::query()->with('profile.workShift')->find($record->employee_id);

        if ($employee) {
            $employee->loadMissing('profile.workShift');
            $expected = $this->expectedStartFor($employee);
            // Shift start always takes precedence — never override with org office_start.
            $start = $expected['shift']
                ? $expected['start']
                : ($fallbackStart ?? $expected['start']);
            $grace = (int) $expected['grace'];
        } else {
            $start = $fallbackStart ?? $this->officeStartTime();
            $grace = 0;
        }

        [$h, $m] = array_map('intval', explode(':', $start));
        $expectedAt = $record->check_in_at->copy()->setTime($h, $m, 0);
        $diff = $expectedAt->diffInMinutes($record->check_in_at, false);
        if ($diff <= 0) {
            return 0;
        }

        // Flexibility absorbs the first N minutes — only excess counts as late.
        return max(0, (int) $diff - $grace);
    }

    /**
     * Early leave minutes vs shift end_time. Display-only (no payroll deduction).
     * 0 when no shift, no check-out, or remote/field day.
     * Time: O(1) | Space: O(1)
     */
    public function earlyLeaveMinutes(AttendanceRecord $record): int
    {
        if (! $record->check_out_at) {
            return 0;
        }

        $type = (string) ($record->type ?? '');
        if (in_array($type, [self::TYPE_REMOTE, self::TYPE_FIELD, 'تكليف خارجي', 'انقطاع'], true)) {
            return 0;
        }

        $employee = $record->relationLoaded('employee')
            ? $record->employee
            : User::query()->with('profile.workShift')->find($record->employee_id);

        if (! $employee) {
            return 0;
        }

        $expected = $this->expectedStartFor($employee);
        if (! $expected['end']) {
            return 0;
        }

        [$h, $m] = array_map('intval', explode(':', $expected['end']));
        $expectedEnd = $record->check_out_at->copy()->setTime($h, $m, 0);
        $diff = $record->check_out_at->diffInMinutes($expectedEnd, false);

        return $diff > 0 ? (int) $diff : 0;
    }

    /**
     * Monthly attendance rows with lateness from each employee's shift
     * (start + grace). Early leave is display-only vs shift end.
     * Time: O(n) records | Space: O(n)
     *
     * @return array{month: string, office_start: string, rows: list<array{date: string, employee: string, type: string, check_in: ?string, check_out: ?string, late_minutes: int, early_leave_minutes: int, source: string, approval_status: ?string, shift_start: ?string}>}
     */
    public function monthlyReport(string $month, ?int $employeeId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $officeStart = $this->officeStartTime();

        $records = AttendanceRecord::query()
            ->select(['id', 'employee_id', 'date', 'check_in_at', 'check_out_at', 'type', 'source', 'approval_status', 'late_minutes'])
            ->with(['employee:id,name', 'employee.profile.workShift'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        $rows = [];
        foreach ($records as $record) {
            $shiftMeta = $record->employee
                ? $this->expectedStartFor($record->employee)
                : ['start' => $officeStart, 'end' => null, 'grace' => 0, 'shift' => null];

            $rows[] = [
                'date' => $record->date?->format('Y-m-d') ?? '',
                'employee' => $record->employee?->name ?? '—',
                'type' => (string) ($record->type ?? ''),
                'check_in' => hollal_time($record->check_in_at),
                'check_out' => hollal_time($record->check_out_at),
                // Always from employee shift when assigned — do not force org office_start.
                'late_minutes' => $this->latenessMinutes($record),
                'early_leave_minutes' => $this->earlyLeaveMinutes($record),
                'source' => (string) ($record->source ?? ''),
                'approval_status' => $record->approval_status,
                'shift_start' => $shiftMeta['shift'] ? $shiftMeta['start'] : null,
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

    /** Imported fingerprint wins — platform punches must not overwrite it. */
    private function assertNotFingerprintLocked(User $employee, string $date): void
    {
        $exists = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('source', self::SOURCE_FINGERPRINT)
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('يوجد سجل بصمة مستورد لهذا اليوم — لا يمكن تعديله من المنصة.');
        }
    }

    private function assertShiftAllowsToday(User $employee): void
    {
        $shift = $this->shiftFor($employee);
        if (! $shift) {
            return;
        }
        if (! $shift->coversWeekday((int) now()->dayOfWeek)) {
            throw new \InvalidArgumentException('اليوم ليس ضمن أيام وردية الموظف.');
        }
    }

    private function assertCanDecideDayType(AttendanceRecord $record, User $actor): void
    {
        if (! $this->canDecideDayType($record, $actor)) {
            throw new \InvalidArgumentException('غير مصرح باعتماد هذا السجل');
        }
    }

    /** ATT-3 — scan site barcode. Time: O(1) */
    public function checkInViaBarcode(User $employee, string $token): AttendanceRecord
    {
        $expected = (string) Setting::get('attendance.site_barcode_token', '');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            throw new \InvalidArgumentException('باركود المقر غير صالح');
        }

        $this->assertNotFingerprintLocked($employee, today()->toDateString());

        $record = $this->checkIn($employee);
        $record->forceFill([
            'source' => self::SOURCE_BARCODE,
            'late_minutes' => $this->latenessMinutes($record),
        ])->save();

        return $record->fresh();
    }

    /** ATT-3 — field work pending manager approval. */
    public function startFieldWork(User $employee, string $location, ?string $proofPath = null): AttendanceRecord
    {
        $this->assertEnabled($employee);
        $this->assertNotFingerprintLocked($employee, today()->toDateString());
        if (! $employee->profile?->is_field_worker) {
            throw new \InvalidArgumentException('الموظف غير مُعلَّم كميداني');
        }

        return AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            [
                'check_in_at' => now(),
                'type' => self::TYPE_FIELD,
                'source' => self::SOURCE_REMOTE,
                'field_location' => $location,
                'field_proof_path' => $proofPath,
                'approval_status' => self::APPROVAL_PENDING,
                'declared_by' => $employee->id,
                'late_minutes' => 0,
            ],
        );
    }

    public function approveFieldWork(AttendanceRecord $record, User $manager): AttendanceRecord
    {
        return $this->approveDayType($record, $manager);
    }

    /**
     * Legacy convenience: fixed-order columns, immediate replace-commit for the inferred month.
     *
     * @return array{import_id: int, rows: int}
     */
    public function importCsv(string $absolutePath, User $uploader): array
    {
        return $this->importFile($absolutePath, $uploader);
    }

    /**
     * Import with default column order (0=بصمة,1=تاريخ,2=حضور,3=انصراف), replace month.
     * Prefer stageImport + commitReplaceImport for interactive mapping.
     *
     * @return array{import_id: int, rows: int}
     */
    public function importFile(string $absolutePath, User $uploader, ?string $sourceLabel = null, ?string $month = null): array
    {
        $all = $this->readAllRows($absolutePath);
        if ($all === []) {
            throw new \InvalidArgumentException('الملف فارغ');
        }

        $headers = array_map(fn ($c) => trim((string) ($c ?? '')), $all[0]);
        $looksLikeHeader = $this->rowLooksLikeHeader($headers);
        $mapping = $looksLikeHeader
            ? $this->guessMappingFromHeaders($headers)
            : [
                self::MAP_FINGERPRINT => 0,
                self::MAP_DATE => 1,
                self::MAP_CHECK_IN => 2,
                self::MAP_CHECK_OUT => 3,
            ];

        $dataRows = $looksLikeHeader ? array_slice($all, 1) : $all;
        $mapped = $this->mapDataRows($dataRows, $mapping);
        $month ??= $this->inferMonthFromMapped($mapped) ?? now()->format('Y-m');
        $sourceLabel = trim((string) ($sourceLabel ?: 'افتراضي'));

        $import = $this->stageImportFromMapped(
            absolutePath: $absolutePath,
            uploader: $uploader,
            sourceLabel: $sourceLabel,
            month: $month,
            mapping: $mapping,
            headers: $looksLikeHeader ? $headers : array_values(self::columnRoleLabels()),
            mappedRows: $mapped,
        );

        if ($import->status === AttendanceImport::STATUS_NEEDS_MATCH) {
            // Auto-skip unknown fingerprints in legacy path (same as old behaviour).
            $import->forceFill([
                'unmatched_rows' => [],
                'status' => AttendanceImport::STATUS_DRAFT,
            ])->save();
        }

        return $this->commitReplaceImport($import, $uploader);
    }

    /**
     * Read first-row headers for interactive mapping UI.
     *
     * @return list<string>
     */
    public function parseFileHeaders(string $absolutePath): array
    {
        $all = $this->readAllRows($absolutePath);
        if ($all === []) {
            return [];
        }

        return array_values(array_map(
            fn ($c) => trim((string) ($c ?? '')),
            $all[0]
        ));
    }

    /**
     * @return list<list<string|null>>
     */
    public function readAllRows(string $absolutePath): array
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return ($ext === 'xlsx' || $ext === 'xls')
            ? $this->readSpreadsheetRows($absolutePath)
            : $this->readCsvRows($absolutePath);
    }

    /**
     * Suggest mapping indices from a learned source map or header heuristics.
     *
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    public function suggestMapping(string $sourceLabel, array $headers): array
    {
        $learned = $this->findColumnMap($sourceLabel);
        if ($learned) {
            $fromLearned = $this->mappingFromLearnedHeaders($headers, $learned);
            if ($fromLearned !== null) {
                return $fromLearned;
            }
        }

        return $this->guessMappingFromHeaders($headers);
    }

    public function findColumnMap(string $sourceLabel): ?AttendanceColumnMap
    {
        $label = trim($sourceLabel);
        if ($label === '') {
            return null;
        }

        return AttendanceColumnMap::query()->where('source_label', $label)->first();
    }

    /**
     * Persist last successful column mapping for a source/جهة.
     *
     * @param  list<string>  $headers
     * @param  array<string, int>  $mapping
     */
    public function learnColumnMap(string $sourceLabel, array $headers, array $mapping, User $actor): AttendanceColumnMap
    {
        $label = trim($sourceLabel) !== '' ? trim($sourceLabel) : 'افتراضي';

        return AttendanceColumnMap::updateOrCreate(
            ['source_label' => $label],
            [
                'headers' => array_values($headers),
                'mapping' => $mapping,
                'updated_by' => $actor->id,
            ],
        );
    }

    /**
     * Stage an import: map columns, split matched vs unmatched fingerprints.
     *
     * @param  array<string, int>  $mapping
     */
    public function stageImport(
        string $absolutePath,
        User $uploader,
        string $sourceLabel,
        string $month,
        array $mapping,
    ): AttendanceImport {
        $this->assertValidMapping($mapping);
        $mapping = array_map(static fn ($v) => (int) $v, $mapping);
        $all = $this->readAllRows($absolutePath);
        if ($all === []) {
            throw new \InvalidArgumentException('الملف فارغ');
        }

        // Interactive staging always treats the first row as column titles.
        $headers = array_map(fn ($c) => trim((string) ($c ?? '')), $all[0]);
        $dataRows = array_slice($all, 1);
        $mapped = $this->mapDataRows($dataRows, $mapping);

        return $this->stageImportFromMapped(
            absolutePath: $absolutePath,
            uploader: $uploader,
            sourceLabel: trim($sourceLabel) !== '' ? trim($sourceLabel) : 'افتراضي',
            month: $month,
            mapping: $mapping,
            headers: $headers,
            mappedRows: $mapped,
        );
    }

    /**
     * Apply manual employee matches for unknown fingerprints, then ready to commit.
     *
     * @param  array<int, int>  $rowIndexToEmployeeId  unmatched row index => employee_id
     */
    public function applyManualFingerprintMatches(AttendanceImport $import, array $rowIndexToEmployeeId): AttendanceImport
    {
        $unmatched = $import->unmatched_rows ?? [];
        $staged = $import->staged_rows ?? [];

        foreach ($rowIndexToEmployeeId as $idx => $employeeId) {
            $idx = (int) $idx;
            $employeeId = (int) $employeeId;
            if (! isset($unmatched[$idx]) || $employeeId <= 0) {
                continue;
            }
            $row = $unmatched[$idx];
            $profile = EmployeeProfile::query()->where('user_id', $employeeId)->first();
            if ($profile && blank($profile->fingerprint_id) && ! blank($row['fingerprint'] ?? null)) {
                $profile->forceFill(['fingerprint_id' => $row['fingerprint']])->save();
            }
            $row['employee_id'] = $employeeId;
            $staged[] = $row;
            unset($unmatched[$idx]);
        }

        $import->forceFill([
            'staged_rows' => array_values($staged),
            'unmatched_rows' => array_values($unmatched),
            'status' => $unmatched === [] || count($unmatched) === 0
                ? AttendanceImport::STATUS_DRAFT
                : AttendanceImport::STATUS_NEEDS_MATCH,
        ])->save();

        return $import->fresh();
    }

    /**
     * Replace fingerprint movements for the import month, then write staged rows.
     * Conflict rule (current): imported fingerprint overwrites any platform punch
     * for the same employee+date.
     *
     * @return array{import_id: int, rows: int}
     */
    public function commitReplaceImport(AttendanceImport $import, User $uploader): array
    {
        if (in_array($import->status, [AttendanceImport::STATUS_NEEDS_MATCH], true)
            && ! empty($import->unmatched_rows)) {
            throw new \InvalidArgumentException('أكمل مطابقة البصمات غير المعروفة قبل الاعتماد');
        }

        $month = (string) $import->import_month;
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \InvalidArgumentException('شهر الاستيراد غير صالح');
        }

        $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $staged = $import->staged_rows ?? [];

        $count = DB::transaction(function () use ($import, $uploader, $from, $to, $staged, $month) {
            // Replace prior fingerprint imports for this calendar month (not cumulative).
            AttendanceRecord::query()
                ->where('source', 'بصمة')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->delete();

            $written = 0;
            foreach ($staged as $row) {
                $employeeId = (int) ($row['employee_id'] ?? 0);
                if ($employeeId <= 0 || blank($row['date'] ?? null) || blank($row['check_in'] ?? null)) {
                    continue;
                }

                $inAt = Carbon::parse(trim((string) $row['date']).' '.trim((string) $row['check_in']));
                $outAt = ! blank($row['check_out'] ?? null)
                    ? Carbon::parse(trim((string) $row['date']).' '.trim((string) $row['check_out']))
                    : null;

                // Imported fingerprint wins over platform attendance for the same day:
                // remove any existing punch (يدوي/باركود/…) then write البصمة.
                AttendanceRecord::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('date', $inAt->toDateString())
                    ->delete();

                $record = AttendanceRecord::create([
                    'employee_id' => $employeeId,
                    'date' => $inAt->toDateString(),
                    'check_in_at' => $inAt,
                    'check_out_at' => $outAt,
                    'type' => 'حضور',
                    'source' => 'بصمة',
                    'declared_by' => $uploader->id,
                ]);
                $late = $this->latenessMinutes($record);
                $hours = null;
                if ($record->check_in_at && $record->check_out_at) {
                    $hours = round(($record->check_out_at->getTimestamp() - $record->check_in_at->getTimestamp()) / 3600, 2);
                }
                $record->forceFill(['late_minutes' => $late, 'work_hours' => $hours])->save();
                $written++;
            }

            $headers = is_array($import->column_mapping) ? ($import->column_mapping['_headers'] ?? []) : [];
            $mapping = $import->column_mapping ?? [];
            unset($mapping['_headers']);
            if ($headers !== [] && $mapping !== []) {
                $this->learnColumnMap((string) $import->source_label, $headers, $mapping, $uploader);
            } elseif ($mapping !== []) {
                $this->learnColumnMap(
                    (string) $import->source_label,
                    array_values(self::columnRoleLabels()),
                    $mapping,
                    $uploader,
                );
            }

            $import->forceFill([
                'status' => AttendanceImport::STATUS_DONE,
                'replaced' => true,
                'rows_count' => $written,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'unmatched_rows' => [],
                'staged_rows' => $staged,
            ])->save();

            return $written;
        });

        return ['import_id' => $import->id, 'rows' => $count];
    }

    /**
     * @param  list<list<string|null>>  $dataRows
     * @param  array<string, int>  $mapping
     * @return list<array{fingerprint: string, date: string, check_in: string, check_out: ?string}>
     */
    public function mapDataRows(array $dataRows, array $mapping): array
    {
        $out = [];
        foreach ($dataRows as $row) {
            $fp = trim((string) ($row[$mapping[self::MAP_FINGERPRINT]] ?? ''));
            $date = trim((string) ($row[$mapping[self::MAP_DATE]] ?? ''));
            $in = trim((string) ($row[$mapping[self::MAP_CHECK_IN]] ?? ''));
            $outTime = isset($mapping[self::MAP_CHECK_OUT])
                ? trim((string) ($row[$mapping[self::MAP_CHECK_OUT]] ?? ''))
                : '';
            if ($fp === '' && $date === '') {
                continue;
            }
            if ($this->isHeaderishFingerprint($fp)) {
                continue;
            }
            try {
                Carbon::parse($date);
            } catch (\Throwable) {
                continue;
            }
            if ($in === '') {
                continue;
            }
            $out[] = [
                'fingerprint' => $fp,
                'date' => $date,
                'check_in' => $in,
                'check_out' => $outTime !== '' ? $outTime : null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{fingerprint: string, date: string, check_in: string, check_out: ?string}>  $mappedRows
     * @param  list<string>  $headers
     * @param  array<string, int>  $mapping
     */
    private function stageImportFromMapped(
        string $absolutePath,
        User $uploader,
        string $sourceLabel,
        string $month,
        array $mapping,
        array $headers,
        array $mappedRows,
    ): AttendanceImport {
        $profiles = EmployeeProfile::query()
            ->whereNotNull('fingerprint_id')
            ->where('fingerprint_id', '!=', '')
            ->get(['user_id', 'fingerprint_id'])
            ->keyBy(fn ($p) => (string) $p->fingerprint_id);

        $staged = [];
        $unmatched = [];
        foreach ($mappedRows as $row) {
            $fp = (string) $row['fingerprint'];
            $profile = $profiles->get($fp);
            if ($profile) {
                $staged[] = $row + ['employee_id' => (int) $profile->user_id];
            } else {
                $unmatched[] = $row;
            }
        }

        $payload = $mapping + ['_headers' => array_values($headers)];

        return AttendanceImport::create([
            'file_path' => $absolutePath,
            'source_label' => $sourceLabel,
            'import_month' => $month,
            'status' => $unmatched === []
                ? AttendanceImport::STATUS_DRAFT
                : AttendanceImport::STATUS_NEEDS_MATCH,
            'column_mapping' => $payload,
            'staged_rows' => $staged,
            'unmatched_rows' => $unmatched,
            'replaced' => false,
            'rows_count' => 0,
            'uploaded_by' => $uploader->id,
        ]);
    }

    /** @param  array<string, int|string>  $mapping */
    private function assertValidMapping(array $mapping): void
    {
        foreach ([self::MAP_FINGERPRINT, self::MAP_DATE, self::MAP_CHECK_IN] as $role) {
            if (! isset($mapping[$role]) || $mapping[$role] === '' || $mapping[$role] === null) {
                throw new \InvalidArgumentException('يجب تحديد أعمدة معرّف البصمة والتاريخ ووقت الحضور');
            }
            if (! is_numeric($mapping[$role])) {
                throw new \InvalidArgumentException('يجب تحديد أعمدة معرّف البصمة والتاريخ ووقت الحضور');
            }
        }
        $vals = [
            (int) $mapping[self::MAP_FINGERPRINT],
            (int) $mapping[self::MAP_DATE],
            (int) $mapping[self::MAP_CHECK_IN],
        ];
        if (isset($mapping[self::MAP_CHECK_OUT]) && $mapping[self::MAP_CHECK_OUT] !== '' && $mapping[self::MAP_CHECK_OUT] !== null) {
            $vals[] = (int) $mapping[self::MAP_CHECK_OUT];
        }
        if (count($vals) !== count(array_unique($vals))) {
            throw new \InvalidArgumentException('لا يجوز تعيين العمود نفسه لأكثر من حقل');
        }
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    public function guessMappingFromHeaders(array $headers): array
    {
        $map = [
            self::MAP_FINGERPRINT => 0,
            self::MAP_DATE => 1,
            self::MAP_CHECK_IN => 2,
            self::MAP_CHECK_OUT => 3,
        ];

        foreach ($headers as $i => $header) {
            $h = mb_strtolower(trim($header));
            if ($h === '') {
                continue;
            }
            if (str_contains($h, 'fingerprint') || str_contains($h, 'بصم') || str_contains($h, 'رقم الجهاز') || $h === 'id') {
                $map[self::MAP_FINGERPRINT] = $i;
            } elseif (str_contains($h, 'date') || str_contains($h, 'تاريخ')) {
                $map[self::MAP_DATE] = $i;
            } elseif (str_contains($h, 'check_in') || str_contains($h, 'checkin') || str_contains($h, 'حضور') || str_contains($h, 'دخول') || str_contains($h, 'in')) {
                $map[self::MAP_CHECK_IN] = $i;
            } elseif (str_contains($h, 'check_out') || str_contains($h, 'checkout') || str_contains($h, 'انصراف') || str_contains($h, 'خروج') || str_contains($h, 'out')) {
                $map[self::MAP_CHECK_OUT] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>|null
     */
    private function mappingFromLearnedHeaders(array $headers, AttendanceColumnMap $learned): ?array
    {
        $savedHeaders = $learned->headers ?? [];
        $savedMapping = $learned->mapping ?? [];
        if ($savedHeaders === [] || $savedMapping === []) {
            return null;
        }

        // Prefer matching by saved header names → current indices.
        $result = [];
        foreach ([self::MAP_FINGERPRINT, self::MAP_DATE, self::MAP_CHECK_IN, self::MAP_CHECK_OUT] as $role) {
            if (! array_key_exists($role, $savedMapping)) {
                continue;
            }
            $oldIdx = (int) $savedMapping[$role];
            $oldName = $savedHeaders[$oldIdx] ?? null;
            if ($oldName === null) {
                continue;
            }
            $newIdx = array_search($oldName, $headers, true);
            if ($newIdx === false) {
                // Fall back to same index if headers length matches.
                if (count($headers) === count($savedHeaders) && isset($headers[$oldIdx])) {
                    $newIdx = $oldIdx;
                } else {
                    return null;
                }
            }
            $result[$role] = (int) $newIdx;
        }

        return isset($result[self::MAP_FINGERPRINT], $result[self::MAP_DATE], $result[self::MAP_CHECK_IN])
            ? $result
            : null;
    }

    /** @param  list<string>  $headers */
    private function rowLooksLikeHeader(array $headers): bool
    {
        $joined = mb_strtolower(implode(' ', $headers));

        return str_contains($joined, 'fingerprint')
            || str_contains($joined, 'date')
            || str_contains($joined, 'بصم')
            || str_contains($joined, 'تاريخ')
            || str_contains($joined, 'حضور')
            || str_contains($joined, 'check');
    }

    private function isHeaderishFingerprint(string $fp): bool
    {
        $l = mb_strtolower($fp);

        return $l === 'fingerprint_id'
            || $l === 'fingerprint'
            || $l === 'معرّف البصمة'
            || $l === 'معرف البصمة'
            || $l === 'بصمة';
    }

    /**
     * @param  list<array{date: string}>  $mapped
     */
    private function inferMonthFromMapped(array $mapped): ?string
    {
        foreach ($mapped as $row) {
            try {
                return Carbon::parse($row['date'])->format('Y-m');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsvRows(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('تعذر فتح ملف الاستيراد');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<string|null>>
     */
    private function readSpreadsheetRows(string $absolutePath): array
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('مكتبة الجداول غير متوفرة');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];
        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $rows[] = array_map(
                static fn ($cell) => $cell === null ? null : (string) $cell,
                $row
            );
        }

        return $rows;
    }
}
