<?php

namespace App\Livewire\Hr;

use App\Models\PayrollAdjustment;
use App\Models\ReferenceItem;
use App\Models\User;
use App\Services\PayrollAdjustmentService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Review proposed payroll lines. Time: O(n) rows | Space: O(n).
 */
class PayrollAdjustmentsIndex extends Component
{
    public string $month = '';

    public string $manualReference = '';

    public ?int $manualEmployeeId = null;

    public ?int $manualItemId = null;

    public string $manualAmount = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.payroll-adjustments.view') || auth()->user()->can('hr.payroll-adjustments.manage'), 403);
        $this->month = now()->format('Y-m');
    }

    public function approve(int $id): void
    {
        abort_unless(auth()->user()->can('hr.payroll-adjustments.manage'), 403);
        app(PayrollAdjustmentService::class)->approve(PayrollAdjustment::query()->findOrFail($id), auth()->user());
    }

    public function cancel(int $id, string $reason): void
    {
        abort_unless(auth()->user()->can('hr.payroll-adjustments.manage'), 403);
        app(PayrollAdjustmentService::class)->cancel(PayrollAdjustment::query()->findOrFail($id), auth()->user(), $reason);
    }

    public function defer(int $id, string $reason): void
    {
        abort_unless(auth()->user()->can('hr.payroll-adjustments.manage'), 403);
        app(PayrollAdjustmentService::class)->defer(PayrollAdjustment::query()->findOrFail($id), auth()->user(), $reason);
    }

    public function modifyAmount(int $id, float $amount, string $reason): void
    {
        abort_unless(auth()->user()->can('hr.payroll-adjustments.manage'), 403);
        app(PayrollAdjustmentService::class)->modify(PayrollAdjustment::query()->findOrFail($id), $amount, $reason);
    }

    public function addEarning(): void
    {
        abort_unless(auth()->user()->can('hr.payroll-adjustments.manage'), 403);
        $employee = User::query()->findOrFail($this->manualEmployeeId);
        $item = ReferenceItem::query()->findOrFail($this->manualItemId);
        app(PayrollAdjustmentService::class)->manualEarning($employee, $item, $this->month, (float) $this->manualAmount, $this->manualReference);
        $this->manualReference = '';
        $this->manualAmount = '';
    }

    public function render(): View
    {
        $rows = PayrollAdjustment::query()
            ->with('referenceItem:id,name_ar')
            ->when($this->month !== '', fn ($query) => $query->where('month', $this->month))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.hr.payroll-adjustments-index', [
            'rows' => $rows,
            'totals' => app(PayrollAdjustmentService::class)->totals($this->month, $this->month),
            'canManage' => auth()->user()->can('hr.payroll-adjustments.manage'),
            'earningItems' => \App\Models\ReferenceList::query()->where('key', 'payroll_adjustment_items')->exists()
                ? app(\App\Services\ReferenceListService::class)->activeItems('payroll_adjustment_items')
                    ->filter(fn (ReferenceItem $item) => ($item->attributes['kind'] ?? '') === 'earning')
                : collect(),
            'employees' => User::query()->orderBy('name')->limit(200)->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'تسويات الرواتب']);
    }
}
