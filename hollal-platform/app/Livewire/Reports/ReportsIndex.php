<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\WeeklyReport;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class ReportsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public ?int $selectedReportId = null;

    protected $queryString = ['selectedReportId' => ['except' => null, 'as' => 'report']];

    public function mount(): void
    {
        $this->authorize('viewAny', WeeklyReport::class);
    }

    public function openReport(int $id): void
    {
        $report = WeeklyReport::findOrFail($id);
        $this->authorize('view', $report);
        $this->selectedReportId = $id;
    }

    public function closeReport(): void
    {
        $this->selectedReportId = null;
    }

    public function render(): View
    {
        $selectedReport = $this->selectedReportId
            ? WeeklyReport::find($this->selectedReportId)
            : null;

        if ($selectedReport) {
            $this->authorize('view', $selectedReport);
        }

        return view('livewire.reports.reports-index', [
            'reports' => WeeklyReport::query()
                ->select(['id', 'week_start', 'week_end', 'generated_at', 'week_spend'])
                ->orderByDesc('generated_at')
                ->paginate(10),
            'selectedReport' => $selectedReport,
        ])->layout('layouts.app', ['title' => 'التقارير الأسبوعية']);
    }
}
