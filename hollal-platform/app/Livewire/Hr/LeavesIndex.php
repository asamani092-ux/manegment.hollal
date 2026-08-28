<?php

namespace App\Livewire\Hr;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * Leave requests submit/approve UI.
 * Time: O(n) list | Space: O(page).
 */
class LeavesIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public bool $showForm = false;

    public string $type = LeaveRequest::TYPE_ANNUAL;

    public string $from_date = '';

    public string $to_date = '';

    public string $reason = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $search = '';

    public string $viewMode = 'table';

    public ?int $open = null;

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'search' => ['except' => ''],
        'viewMode' => ['except' => 'table'],
        'open' => ['except' => null],
    ];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('hr.leaves.request')
            || auth()->user()->can('hr.leaves.approve')
            || auth()->user()->can('hr.leaves.view-all')
            || auth()->user()->can('hr.employees.view'),
            403
        );
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['table', 'cards'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()->can('hr.leaves.request'), 403);
        $this->reset(['type', 'from_date', 'to_date', 'reason']);
        $this->type = LeaveRequest::TYPE_ANNUAL;
        $this->showForm = true;
    }

    public function submitLeave(): void
    {
        abort_unless(auth()->user()->can('hr.leaves.request'), 403);

        $this->validate([
            'type' => 'required|in:سنوية,مرضية,استثنائية',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            app(LeaveService::class)->submit(
                auth()->user(),
                $this->type,
                $this->from_date,
                $this->to_date,
                $this->reason ?: null,
            );
        } catch (\Throwable $e) {
            $this->addError('from_date', $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'تم تقديم طلب الإجازة');
    }

    public function approve(int $id): void
    {
        abort_unless(
            auth()->user()->can('hr.leaves.approve')
            || auth()->user()->can('hr.employees.update'),
            403
        );

        $leave = LeaveRequest::findOrFail($id);
        $this->assertCanDecide($leave);

        try {
            app(LeaveService::class)->approve($leave, auth()->user());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'تم اعتماد الإجازة');
    }

    public function reject(int $id): void
    {
        abort_unless(
            auth()->user()->can('hr.leaves.approve')
            || auth()->user()->can('hr.employees.update'),
            403
        );

        $leave = LeaveRequest::findOrFail($id);
        $this->assertCanDecide($leave);

        app(LeaveService::class)->reject($leave, auth()->user());
        $this->dispatch('toast', type: 'success', message: 'تم رفض الإجازة');
    }

    private function assertCanDecide(LeaveRequest $leave): void
    {
        $user = auth()->user();

        // لا يعتمد أحد إجازته حتى لو كان مسؤول الموارد.
        abort_if($leave->employee_id === $user->id, 403);

        if ($user->can('hr.leaves.view-all') || $user->can('hr.employees.update')) {
            return;
        }

        abort_unless($leave->employee?->manager_id === $user->id, 403);
    }

    public function render(): View
    {
        $user = auth()->user();
        $query = LeaveRequest::query()
            ->select(['id', 'employee_id', 'type', 'from_date', 'to_date', 'days_count', 'reason', 'status', 'approver_id', 'created_at'])
            ->with([
                'employee:id,name,manager_id',
                'employee.profile:id,user_id,annual_leave_balance',
                'approver:id,name',
            ])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->open, fn ($q) => $q->where('id', $this->open))
            ->when($this->search && ! $this->open, fn ($q) => $q->whereHas(
                'employee',
                fn ($e) => $e->where('name', 'like', '%'.$this->search.'%')
            ))
            ->latest();

        if ($user->can('hr.leaves.view-all') || $user->can('hr.employees.update')) {
            // all
        } elseif ($user->can('hr.leaves.approve')) {
            $subIds = User::query()->where('manager_id', $user->id)->pluck('id');
            $query->where(function ($q) use ($user, $subIds) {
                $q->where('employee_id', $user->id)
                    ->orWhereIn('employee_id', $subIds);
            });
        } else {
            $query->where('employee_id', $user->id);
        }

        $balance = (int) ($user->profile?->annual_leave_balance ?? 21);

        return view('livewire.hr.leaves-index', [
            'leaves' => $query->paginate(20),
            'balance' => $balance,
            'canApprove' => $user->can('hr.leaves.approve') || $user->can('hr.employees.update'),
            'canRequest' => $user->can('hr.leaves.request'),
        ]);
    }
}
