<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AttendanceService;
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * Attendance management (path-2): shifts, barcode, geofence locations,
 * enablement, approvals, monthly print.
 * Time: O(n) list | Space: O(page)
 */
class AttendanceIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public string $type = AttendanceService::TYPE_PRESENT;

    public string $notes = '';

    public string $typeFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    public string $printMonth = '';

    public bool $showPrint = false;

    /** In-page tabs (profile-style): enablement|shifts|barcode|pending|log|print */
    public string $activeTab = 'log';

    public string $rosterSearch = '';

    /** Shift form */
    public bool $showShiftForm = false;

    public ?int $editingShiftId = null;

    public string $shiftName = '';

    public string $shiftStart = '08:00';

    public string $shiftEnd = '16:00';

    public int $shiftGrace = 15;

    /** @var list<int> */
    public array $shiftWeekdays = [0, 1, 2, 3, 4];

    public string $assignSearch = '';

    public ?int $assignEmployeeId = null;

    public ?int $assignShiftId = null;

    /** Fixed site barcode (path-2ب) */
    public string $siteBarcodeToken = '';

    /** Location form (path-2ب) */
    public bool $showLocationForm = false;

    public ?int $editingLocationId = null;

    public string $locationName = '';

    public string $locationLatitude = '';

    public string $locationLongitude = '';

    public int $locationRadius = 150;

    public bool $locationActive = true;

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'typeFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'search' => ['except' => ''],
        'printMonth' => ['except' => ''],
        'activeTab' => ['except' => 'log', 'as' => 'tab'],
    ];

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        if ($this->printMonth === '') {
            $this->printMonth = now()->format('Y-m');
        }
        $this->siteBarcodeToken = app(AttendanceService::class)->siteBarcodeToken();

        $tab = request()->query('tab');
        if (is_string($tab) && $tab !== '') {
            $this->setTab($tab);
        } elseif (auth()->user()?->can('hr.employees.update')) {
            $this->activeTab = 'enablement';
        }
    }

    /**
     * Switch in-page attendance tab (profile-style).
     * Time: O(1) | Space: O(1)
     */
    public function setTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->tabCatalog())) {
            return;
        }
        $this->activeTab = $tab;
    }

    /**
     * Full tab key → Arabic label map.
     *
     * @return array<string, string>
     */
    public function tabCatalog(): array
    {
        return [
            'enablement' => 'تفعيل',
            'shifts' => 'ورديات وإسناد',
            'barcode' => 'باركود ومواقع',
            'pending' => 'اعتماد معلّق',
            'log' => 'سجل',
            'print' => 'طباعة',
        ];
    }

    public function saveSiteBarcode(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'siteBarcodeToken' => 'required|string|min:4|max:120',
        ], [], ['siteBarcodeToken' => 'باركود المقر']);

        try {
            $this->siteBarcodeToken = app(AttendanceService::class)
                ->setSiteBarcodeToken($this->siteBarcodeToken);
            $this->dispatch('toast', type: 'success', message: 'تم حفظ باركود المقر');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function rotateSiteBarcode(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->siteBarcodeToken = app(AttendanceService::class)->rotateSiteBarcodeToken();
        $this->dispatch('toast', type: 'success', message: 'تم توليد باركود مقر جديد');
    }

    public function openLocationForm(?int $id = null): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        if ($id) {
            $loc = AttendanceLocation::query()->findOrFail($id);
            $this->editingLocationId = $loc->id;
            $this->locationName = $loc->name;
            $this->locationLatitude = (string) $loc->latitude;
            $this->locationLongitude = (string) $loc->longitude;
            $this->locationRadius = (int) $loc->radius_meters;
            $this->locationActive = (bool) $loc->is_active;
        } else {
            $this->editingLocationId = null;
            $this->locationName = '';
            $this->locationLatitude = '';
            $this->locationLongitude = '';
            $this->locationRadius = 150;
            $this->locationActive = true;
        }
        $this->showLocationForm = true;
    }

    public function closeLocationForm(): void
    {
        $this->showLocationForm = false;
        $this->editingLocationId = null;
    }

    public function saveLocation(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'locationName' => 'required|string|max:120',
            'locationLatitude' => ['required', 'numeric', 'between:-90,90'],
            'locationLongitude' => ['required', 'numeric', 'between:-180,180'],
            'locationRadius' => 'required|integer|min:20|max:5000',
            'locationActive' => 'boolean',
        ], [], [
            'locationName' => 'اسم الموقع',
            'locationLatitude' => 'خط العرض',
            'locationLongitude' => 'خط الطول',
            'locationRadius' => 'نصف القطر',
        ]);

        $payload = [
            'name' => trim($this->locationName),
            'latitude' => (float) $this->locationLatitude,
            'longitude' => (float) $this->locationLongitude,
            'radius_meters' => $this->locationRadius,
            'is_active' => $this->locationActive,
        ];

        if ($this->editingLocationId) {
            AttendanceLocation::query()->whereKey($this->editingLocationId)->update($payload);
        } else {
            AttendanceLocation::query()->create($payload);
        }

        $this->closeLocationForm();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ موقع الحضور');
    }

    public function deleteLocation(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        AttendanceLocation::query()->whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف الموقع');
    }

    public function toggleLocationActive(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $loc = AttendanceLocation::query()->findOrFail($id);
        $loc->forceFill(['is_active' => ! $loc->is_active])->save();
        $this->dispatch(
            'toast',
            type: 'success',
            message: $loc->is_active ? 'فُعّل الموقع' : 'أُوقف الموقع'
        );
    }

    public function checkIn(): void
    {
        $user = auth()->user();
        abort_unless($user->attendance_enabled ?? false, 403);

        try {
            app(AttendanceService::class)->checkIn($user);
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل الحضور');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function checkOut(): void
    {
        $user = auth()->user();
        abort_unless($user->attendance_enabled ?? false, 403);

        try {
            app(AttendanceService::class)->checkOut($user);
            $this->dispatch('toast', type: 'success', message: 'تم تسجيل الانصراف');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function declareType(): void
    {
        $this->validate([
            'type' => 'required|string|in:حضور,عن بعد,ميداني',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        abort_unless($user->attendance_enabled ?? false, 403);

        try {
            app(AttendanceService::class)->declareDayType($user, $this->type, $this->notes ?: null);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->reset(['notes']);
        $msg = in_array($this->type, ['عن بعد', 'ميداني'], true)
            ? 'تم تسجيل الإقرار — بانتظار اعتماد المدير'
            : 'تم تسجيل الإقرار';
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    public function toggleAttendanceEnabled(int $userId): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $employee = User::query()->findOrFail($userId);
        $employee->forceFill(['attendance_enabled' => ! $employee->attendance_enabled])->save();

        $this->dispatch(
            'toast',
            type: 'success',
            message: $employee->attendance_enabled ? 'فُعّل برنامج الحضور' : 'أُوقف برنامج الحضور'
        );
    }

    public function openShiftForm(?int $id = null): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        if ($id) {
            $shift = WorkShift::query()->findOrFail($id);
            $this->editingShiftId = $shift->id;
            $this->shiftName = $shift->name;
            $this->shiftStart = $shift->startHm();
            $this->shiftEnd = $shift->endHm();
            $this->shiftGrace = (int) $shift->grace_minutes;
            $this->shiftWeekdays = array_map('intval', $shift->weekdays ?? []);
        } else {
            $this->editingShiftId = null;
            $this->shiftName = '';
            $this->shiftStart = '08:00';
            $this->shiftEnd = '16:00';
            $this->shiftGrace = 15;
            $this->shiftWeekdays = [0, 1, 2, 3, 4];
        }
        $this->showShiftForm = true;
    }

    public function closeShiftForm(): void
    {
        $this->showShiftForm = false;
        $this->editingShiftId = null;
    }

    public function saveShift(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'shiftName' => 'required|string|max:120',
            'shiftStart' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'shiftEnd' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'shiftGrace' => 'required|integer|min:0|max:240',
            'shiftWeekdays' => 'required|array|min:1',
            'shiftWeekdays.*' => 'integer|min:0|max:6',
        ], [], [
            'shiftName' => 'اسم الوردية',
            'shiftStart' => 'بداية الوردية',
            'shiftEnd' => 'نهاية الوردية',
            'shiftGrace' => 'مرونة التأخير',
            'shiftWeekdays' => 'أيام الأسبوع',
        ]);

        $days = array_values(array_unique(array_map('intval', $this->shiftWeekdays)));
        sort($days);

        $partsStart = array_map('intval', explode(':', $this->shiftStart));
        $partsEnd = array_map('intval', explode(':', $this->shiftEnd));

        $payload = [
            'name' => trim($this->shiftName),
            'start_time' => sprintf('%02d:%02d', $partsStart[0] ?? 0, $partsStart[1] ?? 0),
            'end_time' => sprintf('%02d:%02d', $partsEnd[0] ?? 0, $partsEnd[1] ?? 0),
            'grace_minutes' => $this->shiftGrace,
            'weekdays' => $days,
            'is_active' => true,
        ];

        if ($this->editingShiftId) {
            WorkShift::query()->whereKey($this->editingShiftId)->update($payload);
        } else {
            WorkShift::query()->create($payload);
        }

        $this->closeShiftForm();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ الوردية');
    }

    public function deleteShift(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $shift = WorkShift::query()->findOrFail($id);
        EmployeeProfile::query()->where('work_shift_id', $shift->id)->update(['work_shift_id' => null]);
        $shift->delete();

        $this->dispatch('toast', type: 'success', message: 'تم حذف الوردية');
    }

    public function assignShift(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'assignEmployeeId' => 'required|exists:users,id',
            'assignShiftId' => 'nullable|exists:work_shifts,id',
        ], [], [
            'assignEmployeeId' => 'الموظف',
            'assignShiftId' => 'الوردية',
        ]);

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $this->assignEmployeeId],
            []
        );
        $profile->forceFill(['work_shift_id' => $this->assignShiftId ?: null])->save();

        $this->reset(['assignEmployeeId', 'assignShiftId']);
        $this->dispatch('toast', type: 'success', message: 'تم إسناد الوردية');
    }

    public function approvePending(int $recordId): void
    {
        $record = AttendanceRecord::query()->with('employee')->findOrFail($recordId);
        try {
            app(AttendanceService::class)->approveDayType($record, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم اعتماد اليوم');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function rejectPending(int $recordId): void
    {
        $record = AttendanceRecord::query()->with('employee')->findOrFail($recordId);
        try {
            app(AttendanceService::class)->rejectDayType($record, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم رفض اليوم');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function openMonthlyPrint(): void
    {
        abort_unless(auth()->user()->can('hr.employees.view') || auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'printMonth' => 'required|date_format:Y-m',
        ]);

        $this->showPrint = true;
    }

    public function closePrint(): void
    {
        $this->showPrint = false;
    }

    public function render(): View
    {
        $user = auth()->user();
        $canViewAll = $user->can('hr.employees.update');
        $canManage = $user->can('hr.employees.update');
        $service = app(AttendanceService::class);
        $expected = $service->expectedStartFor($user);
        $officeStart = $expected['start'];
        $shiftGrace = $expected['grace'];

        $query = AttendanceRecord::query()
            ->select(['id', 'employee_id', 'date', 'check_in_at', 'check_out_at', 'type', 'notes', 'approval_status', 'source', 'late_minutes'])
            ->with(['employee:id,name', 'employee.profile.workShift'])
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            ->latest('date');

        if (! $canViewAll) {
            $query->where('employee_id', $user->id);
        } elseif ($this->search !== '') {
            $query->whereHas('employee', fn ($e) => $e->where('name', 'like', '%'.$this->search.'%'));
        }

        $records = $query->paginate(20);
        $lateById = [];
        $earlyById = [];
        $extraById = [];
        $extraLabelById = [];
        foreach ($records as $record) {
            $lateById[$record->id] = $service->latenessMinutes($record);
            $earlyById[$record->id] = $service->earlyLeaveMinutes($record);
            $extraMins = $service->extraWorkMinutes($record);
            $extraById[$record->id] = $extraMins;
            $extraLabelById[$record->id] = $service->formatExtraWorkLabel($extraMins);
        }

        $printReport = null;
        if ($this->showPrint && $this->printMonth !== '') {
            $employeeId = $canViewAll ? null : $user->id;
            $printReport = $service->monthlyReport($this->printMonth, $employeeId);
        }

        $roster = collect();
        $shifts = collect();
        $assignCandidates = collect();
        $pendingApprovals = collect();
        $locations = collect();

        if ($canManage) {
            $roster = User::query()
                ->select(['id', 'name', 'attendance_enabled', 'is_active'])
                ->with(['profile:id,user_id,work_shift_id', 'profile.workShift:id,name'])
                ->where('is_active', true)
                ->when($this->rosterSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->rosterSearch.'%'))
                ->orderBy('name')
                ->limit(40)
                ->get();

            $shifts = WorkShift::query()->orderBy('name')->get();

            $assignCandidates = User::query()
                ->select(['id', 'name'])
                ->where('is_active', true)
                ->when($this->assignSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->assignSearch.'%'))
                ->orderBy('name')
                ->limit(50)
                ->get();

            $locations = AttendanceLocation::query()->orderBy('name')->get();
        }

        // Pending remote/field: manager sees subordinates; HR sees all.
        $pendingQuery = AttendanceRecord::query()
            ->select(['id', 'employee_id', 'date', 'type', 'notes', 'approval_status', 'created_at'])
            ->with('employee:id,name,manager_id')
            ->where('approval_status', AttendanceService::APPROVAL_PENDING)
            ->whereIn('type', [AttendanceService::TYPE_REMOTE, AttendanceService::TYPE_FIELD])
            ->latest('date');

        if ($canManage) {
            // HR: all pending
        } else {
            $subIds = User::query()->where('manager_id', $user->id)->pluck('id');
            $pendingQuery->whereIn('employee_id', $subIds);
        }
        $pendingApprovals = $pendingQuery->limit(40)->get()
            ->filter(fn ($r) => $service->canDecideDayType($r, $user));

        $tabs = $this->visibleTabs($canManage, $pendingApprovals->isNotEmpty());
        if (! array_key_exists($this->activeTab, $tabs)) {
            $this->activeTab = array_key_first($tabs) ?: 'log';
        }

        return view('livewire.hr.attendance-index', [
            'records' => $records,
            'lateById' => $lateById,
            'earlyById' => $earlyById,
            'extraById' => $extraById,
            'extraLabelById' => $extraLabelById,
            'attendanceEnabled' => (bool) ($user->attendance_enabled ?? false),
            'canViewAll' => $canViewAll,
            'canManage' => $canManage,
            'officeStart' => $officeStart,
            'shiftGrace' => $shiftGrace,
            'userShift' => $expected['shift'],
            'monthlyWorkingDays' => (int) Setting::get('attendance.monthly_working_days', 22),
            'printReport' => $printReport,
            'roster' => $roster,
            'shifts' => $shifts,
            'assignCandidates' => $assignCandidates,
            'pendingApprovals' => $pendingApprovals,
            'locations' => $locations,
            'weekdayLabels' => WorkShift::WEEKDAY_LABELS,
            'tabs' => $tabs,
        ])->layout('layouts.app', ['title' => 'الحضور']);
    }

    /**
     * Tabs visible for the current viewer.
     * Time: O(1) | Space: O(1)
     *
     * @return array<string, string>
     */
    protected function visibleTabs(bool $canManage, bool $hasPending): array
    {
        $all = $this->tabCatalog();
        $keys = [];
        if ($canManage) {
            $keys = ['enablement', 'shifts', 'barcode', 'pending', 'log', 'print'];
        } else {
            if ($hasPending) {
                $keys[] = 'pending';
            }
            $keys = array_merge($keys, ['log', 'print']);
        }

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $all[$key];
        }

        return $out;
    }
}
