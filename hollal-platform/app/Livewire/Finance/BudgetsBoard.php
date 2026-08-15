<?php

namespace App\Livewire\Finance;

use App\Models\BudgetAddition;
use App\Models\Project;
use App\Services\BudgetService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 04-B6 — budgets aggregate screen. Every figure is derived live from the
 * expense ledger; nothing on this screen is stored or editable.
 */
class BudgetsBoard extends Component
{
    use AuthorizesRequests;

    /** '' = all, 'warning' = at/over the warning tier, 'over' = at/over 100%. */
    public string $tierFilter = '';

    public ?int $addProjectId = null;

    public string $addAmount = '';

    public string $addNote = '';

    public function mount(): void
    {
        $this->authorize('finance.budgets.view');
    }

    public function requestAddition(): void
    {
        abort_unless(
            auth()->user()->can('finance.budgets.view') || auth()->user()->can('finance.budgets.manage'),
            403
        );

        $this->validate([
            'addProjectId' => 'required|exists:projects,id',
            'addAmount' => 'required|numeric|min:0.01',
            'addNote' => 'nullable|string|max:500',
        ]);

        app(BudgetService::class)->requestAddition(
            Project::findOrFail($this->addProjectId),
            (float) $this->addAmount,
            auth()->user(),
            $this->addNote !== '' ? $this->addNote : null,
        );

        $this->reset(['addProjectId', 'addAmount', 'addNote']);
        $this->dispatch('toast', type: 'success', message: 'أُرسل طلب الإضافة — بانتظار اعتماد المدير التنفيذي');
    }

    public function approveAddition(int $id): void
    {
        abort_unless(auth()->user()->can('finance.budgets.manage'), 403);

        try {
            app(BudgetService::class)->approveAddition(BudgetAddition::findOrFail($id), auth()->user());
            $this->dispatch('toast', type: 'success', message: 'اعتُمدت الإضافة وأُضيفت للموازنة');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        $service = app(BudgetService::class);
        $warning = $service->warningThreshold();

        $rows = $service->board()->filter(function (array $row) use ($warning) {
            return match ($this->tierFilter) {
                'warning' => $row['percent'] >= $warning,
                'over' => $row['percent'] >= 100,
                default => true,
            };
        })->values();

        return view('livewire.finance.budgets-board', [
            'rows' => $rows,
            'warningThreshold' => $warning,
            'totals' => [
                'budget' => $rows->sum('budget'),
                'consumed' => $rows->sum('consumed'),
                'remaining' => $rows->sum('remaining'),
            ],
            'pendingAdditions' => BudgetAddition::query()
                ->where('status', BudgetAddition::STATUS_PENDING)
                ->with(['project:id,name', 'requester:id,name'])
                ->latest()
                ->get(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'budget']),
            'canApproveBudget' => auth()->user()->can('finance.budgets.manage'),
        ])->layout('layouts.app', ['title' => 'الموازنات']);
    }
}
