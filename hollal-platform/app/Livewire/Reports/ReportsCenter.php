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
        abort_unless($this->canAccessCenter(), 403);
        $this->month = now()->format('Y-m');
    }

    public function setTab(string $tab): void
    {
        $permission = match ($tab) {
            'project' => 'reports.projects.view',
            'impact' => 'reports.impact.view',
            'kpi' => 'reports.kpis.view',
            default => 'reports.monthly.view',
        };

        abort_unless(
            auth()->user()->can($permission) || auth()->user()->can('reports.view'),
            403
        );

        $this->tab = $tab;
    }

    /** Freeze the currently displayed report. */
    public function takeSnapshot(): void
    {
        abort_unless($this->canAccessCenter(), 403);

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

    public function exportCsv(): mixed
    {
        abort_unless(auth()->user()->can('reports.export'), 403);

        $service = app(ReportCenterService::class);
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : now()->format('Y-m');
        $payload = match ($this->tab) {
            'project' => $this->projectId
                ? $service->projectDashboard(Project::findOrFail($this->projectId))
                : [],
            'impact' => $service->impact($this->organizationId ? Organization::find($this->organizationId) : null),
            'kpi' => $service->kpis(),
            default => $service->monthly($month),
        };

        \App\Models\AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'report.exported',
            'target_type' => ReportSnapshot::class,
            'target_id' => null,
            'ip_address' => request()->ip(),
            'metadata' => ['tab' => $this->tab, 'month' => $month],
            'created_at' => now(),
        ]);

        return response()->streamDownload(function () use ($payload) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['المفتاح', 'القيمة']);
            foreach ($payload as $key => $value) {
                fputcsv($handle, [(string) $key, is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)]);
            }
            fclose($handle);
        }, 'report-'.$this->tab.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $service = app(ReportCenterService::class);
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : now()->format('Y-m');
        $user = auth()->user();

        return view('livewire.reports.reports-center', [
            'monthly' => ($user->can('reports.monthly.view') || $user->can('reports.view'))
                ? $service->monthly($month) : null,
            'projectReport' => ($user->can('reports.projects.view') || $user->can('reports.view')) && $this->projectId
                ? $service->projectDashboard(Project::findOrFail($this->projectId)) : null,
            'impact' => ($user->can('reports.impact.view') || $user->can('reports.view'))
                ? $service->impact($this->organizationId ? Organization::find($this->organizationId) : null) : null,
            'kpis' => ($user->can('reports.kpis.view') || $user->can('reports.view'))
                ? $service->kpis() : null,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'snapshots' => ReportSnapshot::orderByDesc('id')->limit(25)->get(),
            'canExport' => $user->can('reports.export'),
        ])->layout('layouts.app', ['title' => 'مركز التقارير']);
    }

    private function canAccessCenter(): bool
    {
        $user = auth()->user();

        return $user->can('reports.view')
            || $user->can('reports.monthly.view')
            || $user->can('reports.projects.view')
            || $user->can('reports.impact.view')
            || $user->can('reports.kpis.view');
    }
}
