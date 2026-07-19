<?php

namespace App\Services;

use App\Models\ExpenseRequest;
use App\Models\Organization;
use App\Models\OrganizationImpactRecord;
use App\Models\Partnership;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * 08-B1 / 08-B2 — the unified reports centre.
 *
 * Every indicator is derived at read time from the operational tables; the only
 * thing ever stored is an immutable snapshot of a derivation.
 */
class ReportCenterService
{
    /**
     * Monthly management report.
     *
     * @return array<string, mixed>
     */
    public function monthly(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $tasks = Task::query()->whereBetween('created_at', [$start, $end]);

        return [
            'month' => $month,
            'tasks_created' => (clone $tasks)->count(),
            'tasks_completed' => (clone $tasks)->where('status', 'completed')->count(),
            'tasks_overdue' => Task::query()->overdue()->count(),
            'projects_active' => Project::query()->whereNull('closed_at')->count(),
            'projects_closed' => Project::query()->whereBetween('closed_at', [$start, $end])->count(),
            'spend' => (float) ExpenseRequest::query()
                ->countedAsSpend()
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount'),
            'partnerships_by_stage' => collect(Partnership::PIPELINE_STAGES)
                ->mapWithKeys(fn (int $stage) => [
                    Partnership::STAGE_LABELS[$stage] => Partnership::where('stage', $stage)->count(),
                ])->all(),
            'visits_done' => ProjectVisit::where('status', ProjectVisit::STATUS_DONE)
                ->whereBetween('scheduled_on', [$start, $end])->count(),
        ];
    }

    /**
     * Project dashboard report — the indicators the Excel dashboard tracks.
     *
     * @return array<string, mixed>
     */
    public function projectDashboard(Project $project): array
    {
        $progress = app(ProjectProgressService::class)->summary($project);
        $budget = app(BudgetService::class)->consumption($project);
        $results = app(MeasurementService::class)->results($project);

        return [
            'project_id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'weighted_progress' => $progress['weighted_percent'],
            'tasks_total' => $progress['total'],
            'tasks_evaluated' => $progress['evaluated'],
            'tasks_overdue' => $progress['overdue'],
            'rating_distribution' => $progress['distribution'],
            'budget' => $budget['budget'],
            'consumed' => $budget['consumed'],
            'remaining' => $budget['remaining'],
            'consumption_percent' => $budget['percent'],
            'beneficiaries' => $results['beneficiaries'],
            'improvement_percent' => $results['improvement_percent'],
            'satisfaction_percent' => $results['satisfaction_percent'],
            'visits_done' => $project->visits()->where('status', ProjectVisit::STATUS_DONE)->count(),
            'consultations_closed' => $project->consultations()->where('status', 'مغلقة')->count(),
        ];
    }

    /**
     * Impact report across organizations.
     *
     * @return array<string, mixed>
     */
    public function impact(?Organization $organization = null): array
    {
        $records = OrganizationImpactRecord::query()
            ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
            ->get();

        return [
            'organization_id' => $organization?->id,
            'records' => $records->count(),
            'beneficiaries' => (int) $records->sum('beneficiaries'),
            'avg_improvement_percent' => $records->whereNotNull('improvement_percent')->avg('improvement_percent'),
            'avg_satisfaction_percent' => $records->whereNotNull('satisfaction_percent')->avg('satisfaction_percent'),
            'projects' => $records->pluck('project_id')->filter()->unique()->count(),
        ];
    }

    /**
     * Platform KPIs.
     *
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $tasksTotal = Task::count();
        $tasksCompleted = Task::where('status', 'completed')->count();
        $projects = Project::all();

        $weighted = $projects->isEmpty()
            ? 0.0
            : round($projects->avg(fn (Project $p) => app(ProjectProgressService::class)->summary($p)['weighted_percent']), 2);

        return [
            'task_completion_percent' => $tasksTotal > 0 ? round(($tasksCompleted / $tasksTotal) * 100, 2) : 0.0,
            'overdue_tasks' => Task::query()->overdue()->count(),
            'avg_project_progress_percent' => $weighted,
            'active_projects' => $projects->whereNull('closed_at')->count(),
            'active_partnerships' => Partnership::whereIn('stage', Partnership::PIPELINE_STAGES)->count(),
            'employees' => User::count(),
        ];
    }

    /**
     * Freeze a derivation into an immutable snapshot.
     *
     * @param  array<string, mixed>  $payload
     */
    public function snapshot(string $kind, string $label, array $payload, ?string $period = null, ?int $subjectId = null, ?User $actor = null): ReportSnapshot
    {
        return ReportSnapshot::create([
            'kind' => $kind,
            'label' => $label,
            'period' => $period,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'payload_hash' => ReportSnapshot::hashFor($payload),
            'generated_by' => $actor?->id,
        ]);
    }
}
