<?php

namespace App\Livewire\Finance;

use App\Models\ExpenseCategory;
use App\Models\RevenueCategory;
use App\Services\FinancialReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 04-B6 — strict financial reports. Derivation only: the screen never writes and
 * never reads a stored total. A reconciliation banner states whether the line
 * items still tie back to the source ledgers.
 */
class FinancialReportsIndex extends Component
{
    use AuthorizesRequests;

    public string $month = '';

    public function mount(): void
    {
        $this->authorize('finance.reports.view');
        $this->month = now()->format('Y-m');
    }

    public function render(): View
    {
        $service = app(FinancialReportService::class);
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : now()->format('Y-m');

        $report = $service->monthly($month);

        return view('livewire.finance.financial-reports-index', [
            'report' => $report,
            'reconciles' => $service->reconciles($report),
            'expenseCategories' => ExpenseCategory::pluck('name_ar', 'id'),
            'revenueCategories' => RevenueCategory::pluck('name_ar', 'id'),
        ])->layout('layouts.app', ['title' => 'التقارير المالية']);
    }
}
