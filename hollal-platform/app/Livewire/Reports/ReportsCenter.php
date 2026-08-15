<?php

namespace App\Livewire\Reports;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ReportSnapshot;
use App\Services\ReportCenterService;
use App\Services\ReportDocumentService;
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

    /** Id of the frozen snapshot currently expanded for read-only preview. */
    public ?int $previewSnapshotId = null;

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
        $user = auth()->user();

        $snapshot = match ($this->tab) {
            'project' => $this->projectId
                ? $service->snapshot(
                    ReportSnapshot::KIND_PROJECT_DASHBOARD,
                    'لوحة مشروع',
                    $service->projectDashboard(Project::findOrFail($this->projectId)),
                    null,
                    $this->projectId,
                    $user,
                )
                : null,
            'impact' => $service->snapshot(
                ReportSnapshot::KIND_IMPACT,
                'تقرير الأثر',
                $service->impact($this->organizationId ? Organization::find($this->organizationId) : null),
                null,
                $this->organizationId,
                $user,
            ),
            'kpi' => $service->snapshot(
                ReportSnapshot::KIND_KPI,
                'مؤشرات الأداء',
                $service->kpis(),
                null,
                null,
                $user,
            ),
            default => $service->snapshot(
                ReportSnapshot::KIND_MONTHLY,
                'التقرير الشهري',
                $service->monthly($this->month),
                $this->month,
                null,
                $user,
            ),
        };

        if ($snapshot) {
            app(ReportDocumentService::class)->archiveCenterExport(
                $this->tab,
                $snapshot->label,
                $snapshot->payload ?? [],
                $user,
                $snapshot,
            );
        }

        $this->dispatch('ds-toast', message: 'حُفظت لقطة التقرير (غير قابلة للتعديل)');
    }

    /** Expand a frozen snapshot inline to preview exactly what was saved. */
    public function previewSnapshot(int $snapshotId): void
    {
        abort_unless($this->canAccessCenter(), 403);

        $this->previewSnapshotId = $this->previewSnapshotId === $snapshotId ? null : $snapshotId;
    }

    public function closeSnapshotPreview(): void
    {
        $this->previewSnapshotId = null;
    }

    public function exportCsv(): mixed
    {
        abort_unless(auth()->user()->can('reports.export'), 403);

        $service = app(ReportCenterService::class);
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1 ? $this->month : now()->format('Y-m');
        $label = match ($this->tab) {
            'project' => 'لوحة مشروع',
            'impact' => 'تقرير الأثر',
            'kpi' => 'مؤشرات الأداء',
            default => 'التقرير الشهري',
        };
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

        app(ReportDocumentService::class)->archiveCenterExport(
            $this->tab,
            $label,
            $payload,
            auth()->user(),
        );

        $sections = $this->exportSections($this->tab, $payload);

        return response()->streamDownload(function () use ($sections, $label) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM — opens correctly in Excel with Arabic text
            fputcsv($handle, [$label, 'مركز التقارير الموحّد — '.now()->format('Y-m-d H:i')]);

            foreach ($sections as $sectionTitle => $rows) {
                fputcsv($handle, []);
                fputcsv($handle, [$sectionTitle]);
                fputcsv($handle, ['المؤشر', 'القيمة']);
                foreach ($rows as $key => $value) {
                    fputcsv($handle, [
                        (string) $key,
                        is_scalar($value) || $value === null ? (string) ($value ?? '—') : json_encode($value, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            fclose($handle);
        }, 'report-'.$this->tab.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Named, Arabic-labelled sections mirroring the on-screen preview — a real
     * multi-section export rather than a raw key→value dump.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    private function exportSections(string $tab, array $payload): array
    {
        return match ($tab) {
            'project' => [
                'لوحة المشروع' => [
                    'الإنجاز الموزون %' => $payload['weighted_progress'] ?? null,
                    'المهام' => $payload['tasks_total'] ?? null,
                    'المقيّمة نهائيًا' => $payload['tasks_evaluated'] ?? null,
                    'المتأخرة' => $payload['tasks_overdue'] ?? null,
                    'الموازنة' => $payload['budget'] ?? null,
                    'المستهلك' => $payload['consumed'] ?? null,
                    'المتبقي' => $payload['remaining'] ?? null,
                    'نسبة الاستهلاك %' => $payload['consumption_percent'] ?? null,
                    'المستفيدون' => $payload['beneficiaries'] ?? null,
                    'نسبة التحسن %' => $payload['improvement_percent'] ?? null,
                    'نسبة الرضا %' => $payload['satisfaction_percent'] ?? null,
                    'الزيارات المنفذة' => $payload['visits_done'] ?? null,
                    'الاستشارات المغلقة' => $payload['consultations_closed'] ?? null,
                ],
            ],
            'impact' => [
                'تقرير الأثر' => [
                    'عدد السجلات' => $payload['records'] ?? null,
                    'المستفيدون' => $payload['beneficiaries'] ?? null,
                    'متوسط التحسن %' => $payload['avg_improvement_percent'] ?? null,
                    'متوسط الرضا %' => $payload['avg_satisfaction_percent'] ?? null,
                ],
            ],
            'kpi' => [
                'مؤشرات الأداء' => [
                    'نسبة إنجاز المهام %' => $payload['task_completion_percent'] ?? null,
                    'المهام المتأخرة' => $payload['overdue_tasks'] ?? null,
                    'متوسط تقدم المشاريع %' => $payload['avg_project_progress_percent'] ?? null,
                    'المشاريع النشطة' => $payload['active_projects'] ?? null,
                    'الشراكات في الرحلة' => $payload['active_partnerships'] ?? null,
                    'الموظفون' => $payload['employees'] ?? null,
                ],
            ],
            default => [
                'التقرير الشهري' => [
                    'المهام المُنشأة' => $payload['tasks_created'] ?? null,
                    'المهام المكتملة' => $payload['tasks_completed'] ?? null,
                    'المهام المتأخرة' => $payload['tasks_overdue'] ?? null,
                    'المشاريع النشطة' => $payload['projects_active'] ?? null,
                    'المشاريع المغلقة' => $payload['projects_closed'] ?? null,
                    'المصروف' => $payload['spend'] ?? null,
                    'الزيارات المنفذة' => $payload['visits_done'] ?? null,
                ],
                'الشراكات حسب المرحلة' => $payload['partnerships_by_stage'] ?? [],
            ],
        };
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
            'previewedSnapshot' => $this->previewSnapshotId ? ReportSnapshot::find($this->previewSnapshotId) : null,
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
