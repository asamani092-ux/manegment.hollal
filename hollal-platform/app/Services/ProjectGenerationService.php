<?php

namespace App\Services;

use App\Models\PlanTemplate;
use App\Models\Project;
use App\Models\ProjectGenerationRequest;
use App\Models\Task;
use App\Models\TemplateItem;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 06B-B1 — the generation engine.
 *
 * Copies the current template version's items (mandatory + the service items
 * sold in the quote) into Esnad tasks: dates from launch + offset, roles
 * translated to people where a person exists, hierarchy preserved. The copy
 * belongs to the project — editing it never touches the template, and editing
 * the template never touches an already generated project.
 */
class ProjectGenerationService
{
    /** Roles that belong to the entity: no account, visible through the link. */
    private const ENTITY_ROLE_PREFIXES = ['مدير جهة', 'منسق الجهة', 'معلم', 'مشرف الجهة'];

    public function generate(ProjectGenerationRequest $request): Project
    {
        $templates = app(PlanTemplateService::class);

        $hollal = PlanTemplate::where('kind', PlanTemplate::KIND_HOLLAL)->firstOrFail();
        $entity = PlanTemplate::where('kind', PlanTemplate::KIND_ENTITY)->firstOrFail();

        // Both templates must have cleared the review session (06A-B2).
        $templates->assertGeneratable($hollal);
        $templates->assertGeneratable($entity);

        return DB::transaction(function () use ($request, $hollal, $entity) {
            $partnership = $request->partnership;

            $project = Project::create([
                'name' => ($request->program?->name ?? 'مشروع').' — '
                    .($partnership->organization?->name ?? $partnership->entity_name ?? 'شراكة'),
                'partnership_id' => $partnership->id,
                'program_id' => $request->program_id,
                'kind' => 'شراكة',
                'launch_date' => $request->launch_date,
                'start_date' => $request->launch_date,
                'manager_id' => $request->project_manager_id,
                'status' => 'تخطيط',
                'budget' => $request->quote?->total,
                'hollal_template_version_id' => $hollal->current_version_id,
                'entity_template_version_id' => $entity->current_version_id,
                'generated_from_request_id' => $request->id,
            ]);

            $partnership->forceFill(['project_id' => $project->id])->save();

            $services = $request->included_services ?? [];
            $this->copyTemplate($hollal->currentVersion, $project, $services, $request->project_manager_id);
            $this->copyTemplate($entity->currentVersion, $project, $services, null);

            $request->forceFill([
                'status' => ProjectGenerationRequest::STATUS_GENERATED,
                'project_id' => $project->id,
            ])->save();

            return $project->fresh();
        });
    }

    /**
     * @param  list<string>  $includedServices
     */
    private function copyTemplate(
        TemplateVersion $version,
        Project $project,
        array $includedServices,
        ?int $defaultAssignee,
    ): void {
        $items = app(PlanTemplateService::class)->itemsForServices($version, $includedServices);
        $launch = $project->launch_date ?? now();
        $map = [];

        foreach ($items as $item) {
            /** @var TemplateItem $item */
            $parentTaskId = $item->parent_id ? ($map[$item->parent_id] ?? null) : null;

            // A parent that was filtered out (unsold service) takes its subtree with it.
            if ($item->parent_id && $parentTaskId === null) {
                continue;
            }

            $isEntityRole = $this->isEntityRole((string) $item->role);
            $start = $launch->copy()->addDays($item->start_offset_days);

            $task = Task::create([
                'title' => $item->title,
                'description' => $item->guidance_note,
                'type' => 'single',
                'assigned_by' => $defaultAssignee,
                'assigned_to' => $isEntityRole ? null : $this->resolveRole($project, (string) $item->role, $defaultAssignee),
                'project_id' => $project->id,
                'priority' => 'medium',
                'status' => 'new',
                'due_date' => $start->copy()->addDays(max($item->duration_days, 1)),
                'required_evidence' => $item->evidence_required,
                'template_item_id' => $item->id,
                'parent_task_id' => $parentTaskId,
                'entity_visible' => $isEntityRole,
                'role_label' => $item->role,
            ]);

            $map[$item->id] = $task->id;
        }
    }

    /** Translate a template role into a person on the project team. */
    private function resolveRole(Project $project, string $role, ?int $fallback): ?int
    {
        if ($role === '') {
            return $fallback;
        }

        $member = $project->team()
            ->whereHas('profile', fn ($q) => $q->where('job_title', $role))
            ->first()
            ?? User::query()->whereHas('profile', fn ($q) => $q->where('job_title', $role))->first();

        return $member?->id ?? $fallback;
    }

    private function isEntityRole(string $role): bool
    {
        foreach (self::ENTITY_ROLE_PREFIXES as $prefix) {
            if (str_contains($role, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
