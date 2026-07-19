<?php

namespace App\Livewire\Finance;

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

    public function mount(): void
    {
        $this->authorize('finance.budgets.view');
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
        ])->layout('layouts.app', ['title' => 'الموازنات']);
    }
}
