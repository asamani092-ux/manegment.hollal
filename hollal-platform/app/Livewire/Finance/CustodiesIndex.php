<?php

namespace App\Livewire\Finance;

use App\Models\Custody;
use App\Models\User;
use App\Services\CustodyService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 04-B3 UI — custody requests and lifecycle actions.
 * Time: O(n) list | Space: O(page size).
 */
class CustodiesIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showRequestModal = false;

    public string $amount = '';

    public string $purpose = '';

    public ?int $employee_id = null;

    public string $statusFilter = '';

    public string $search = '';

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function updatingStatusFilter(): void
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
            auth()->user()->can('finance.custodies.view')
            || auth()->user()->can('finance.custodies.approve')
            || auth()->user()->can('finance.custodies.disburse'),
            403
        );
    }

    public function openRequestModal(): void
    {
        $this->reset(['amount', 'purpose', 'employee_id']);
        $this->employee_id = auth()->id();
        $this->showRequestModal = true;
    }

    public function submitRequest(): void
    {
        $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'required|string|min:3',
            'employee_id' => 'required|exists:users,id',
        ]);

        $employee = User::findOrFail($this->employee_id);
        if ($this->employee_id !== auth()->id()) {
            abort_unless(auth()->user()->can('finance.custodies.approve'), 403);
        }

        app(CustodyService::class)->request(
            $employee,
            (float) $this->amount,
            $this->purpose,
            null,
            null,
            null,
            auth()->user()
        );

        $this->showRequestModal = false;
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل طلب العهدة');
    }

    public function approveCustody(int $id): void
    {
        abort_unless(auth()->user()->can('finance.custodies.approve'), 403);
        $custody = Custody::findOrFail($id);
        app(CustodyService::class)->approve($custody, auth()->user());
        $this->dispatch('toast', type: 'success', message: 'تم اعتماد العهدة');
    }

    public function disburseCustody(int $id): void
    {
        abort_unless(auth()->user()->can('finance.custodies.disburse'), 403);
        $custody = Custody::findOrFail($id);
        app(CustodyService::class)->disburse($custody);
        $this->dispatch('toast', type: 'success', message: 'تم صرف العهدة');
    }

    public function render(): View
    {
        $query = Custody::query()
            ->select(['id', 'employee_id', 'amount', 'purpose', 'status', 'due_date', 'created_at'])
            ->with('employee:id,name')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->whereHas(
                'employee',
                fn ($e) => $e->where('name', 'like', '%'.$this->search.'%')
            ))
            ->latest();

        if (! auth()->user()->can('finance.custodies.view') && auth()->user()->can('finance.custodies.approve')) {
            $query->whereIn('status', [Custody::STATUS_REQUESTED, Custody::STATUS_APPROVED]);
        } elseif (! auth()->user()->can('finance.custodies.view')) {
            $query->where('employee_id', auth()->id());
        }

        return view('livewire.finance.custodies-index', [
            'custodies' => $query->paginate(10),
            'employees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statusOptions' => [
                Custody::STATUS_REQUESTED,
                Custody::STATUS_APPROVED,
                Custody::STATUS_DISBURSED,
                Custody::STATUS_SETTLING,
                Custody::STATUS_CLOSED,
            ],
            'canApprove' => auth()->user()->can('finance.custodies.approve'),
            'canDisburse' => auth()->user()->can('finance.custodies.disburse'),
        ])->layout('layouts.app', ['title' => 'العهد']);
    }
}
