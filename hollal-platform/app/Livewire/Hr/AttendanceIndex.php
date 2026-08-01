<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Attendance declarations list + check-in/out.
 * Time: O(n) | Space: O(page).
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

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'typeFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'search' => ['except' => ''],
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
        // أي مستخدم مصادق — القائمة تُقيَّد في render
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

        // الإقرار يوثّق نوع اليوم فقط ولا يعبث بوقت الحضور الفعلي المسجَّل.
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

    public function render(): View
    {
        $canViewAll = auth()->user()->can('hr.employees.update');

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

        return view('livewire.hr.attendance-index', [
            'records' => $query->paginate(20),
            'attendanceEnabled' => (bool) (auth()->user()->attendance_enabled ?? false),
            'canViewAll' => $canViewAll,
        ]);
    }
}
