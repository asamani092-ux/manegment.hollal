<?php

namespace App\Livewire\Reports;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ReportSnapshot;
use App\Services\ReportCenterService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 08-B1 / 08-B2 — unified reports centre: monthly, project dashboard, impact,
 * KPIs, and the immutable snapshot list.
 */
class ReportsCenter extends Component
{
    use AuthorizesRequests;

    public string $tab = 'monthly'; // monthly|project|impact|kpi

    public string $month = '';

    public ?int $projectId = null;

    public ?int $organizationId = null;

    public function mount(): void
    {
        $this->authorize('reports.view');
        $this->month = now()->format('Y-m');
    }

    /** Freeze the currently displayed report. */
    public function takeSnapshot(): void
    {
        $this->authorize('reports.view');

        $service = app(ReportCenterService::class);

        match ($this->tab) {
            'project' => $this->projectId
                ? $service->snapshot(
                    ReportSnapshot::KIND_PROJECT_DASHBOARD,
                    'لوحة مشروع',
                    $service->projectDashboard(Project::findOrFail($this->projectId)),
                    null,
                    $this->projectId,
                    auth()->user(),
                )
                : null,
            'impact' => $service->snapshot(
                ReportSnapshot::KIND_IMPACT,
                'تقرير الأثر',
                $service->impact($this->organizationId ? Organization::find($this->organizationId) : null),
                null,
                $this->organizationId,
                auth()->user(),
            ),
            'kpi' => $service->snapshot(
                ReportSnapshot::KIND_KPI,
                'مؤشرات الأداء',
                $service->kpis(),
                null,
                null,
                auth()->user(),
            ),
            default => $service->snapshot(
                ReportSnapshot::KIND_MONTHLY,
                'التقرير الشهري',
                $service->monthly($this->month),
                $this->month,
                null,
                auth()->user(),
            ),
        };

        $this->dispatch('ds-toast', message: 'حُفظت لقطة التقرير (غير قابلة للتعديل)');
    }

    public function render(): View
    {
        $service = app(ReportCenterService::class);
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : now()->format('Y-m');

        return view('livewire.reports.reports-center', [
            'monthly' => $service->monthly($month),
            'projectReport' => $this->projectId ? $service->projectDashboard(Project::findOrFail($this->projectId)) : null,
            'impact' => $service->impact($this->organizationId ? Organization::find($this->organizationId) : null),
            'kpis' => $service->kpis(),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'snapshots' => ReportSnapshot::orderByDesc('id')->limit(25)->get(),
        ])->layout('layouts.app', ['title' => 'مركز التقارير']);
    }
}
