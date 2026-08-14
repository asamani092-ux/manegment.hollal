<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\Setting;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Attendance management: check-in/out, declarations, HR enablement, monthly print.
 * Time: O(n) list | Space: O(page)
 */
class AttendanceIndex extends Component
{
    use WithPagination;

    public string $type = 'حضور';

    public string $notes = '';

    public string $typeFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    public string $printMonth = '';

    public bool $showPrint = false;

    public string $rosterSearch = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'typeFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'search' => ['except' => ''],
        'printMonth' => ['except' => ''],
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
    }

    public function checkIn(): void
    {
        $user = auth()->user();
        abort_unless($user->attendance_enabled ?? false, 403);

        app(AttendanceService::class)->checkIn($user);
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل الحضور');
    }

    public function checkOut(): void
    {
        $user = auth()->user();
        abort_unless($user->attendance_enabled ?? false, 403);

        app(AttendanceService::class)->checkOut($user);
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل الانصراف');
    }

    public function declareType(): void
    {
        $this->validate([
            'type' => 'required|string|in:حضور,عن بعد,تكليف خارجي,انقطاع',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        abort_unless($user->attendance_enabled ?? false, 403);

        AttendanceRecord::updateOrCreate(
            ['employee_id' => $user->id, 'date' => today()],
            [
                'type' => $this->type,
                'notes' => $this->notes ?: null,
                'declared_by' => $user->id,
            ]
        );

        $this->reset(['notes']);
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل الإقرار');
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
        $canViewAll = auth()->user()->can('hr.employees.update');
        $canManage = auth()->user()->can('hr.employees.update');
        $service = app(AttendanceService::class);
        $officeStart = $service->officeStartTime();

        $query = AttendanceRecord::query()
            ->select(['id', 'employee_id', 'date', 'check_in_at', 'check_out_at', 'type', 'notes'])
            ->with('employee:id,name')
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            ->latest('date');

        if (! $canViewAll) {
            $query->where('employee_id', auth()->id());
        } elseif ($this->search !== '') {
            $query->whereHas('employee', fn ($e) => $e->where('name', 'like', '%'.$this->search.'%'));
        }

        $records = $query->paginate(20);
        $lateById = [];
        foreach ($records as $record) {
            $lateById[$record->id] = $service->latenessMinutes($record, $officeStart);
        }

        $printReport = null;
        if ($this->showPrint && $this->printMonth !== '') {
            $employeeId = $canViewAll ? null : auth()->id();
            $printReport = $service->monthlyReport($this->printMonth, $employeeId);
        }

        $roster = collect();
        if ($canManage) {
            $roster = User::query()
                ->select(['id', 'name', 'attendance_enabled', 'is_active'])
                ->where('is_active', true)
                ->when($this->rosterSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->rosterSearch.'%'))
                ->orderBy('name')
                ->limit(40)
                ->get();
        }

        return view('livewire.hr.attendance-index', [
            'records' => $records,
            'lateById' => $lateById,
            'attendanceEnabled' => (bool) (auth()->user()->attendance_enabled ?? false),
            'canViewAll' => $canViewAll,
            'canManage' => $canManage,
            'officeStart' => $officeStart,
            'monthlyWorkingDays' => (int) Setting::get('attendance.monthly_working_days', 22),
            'printReport' => $printReport,
            'roster' => $roster,
        ]);
    }
}
