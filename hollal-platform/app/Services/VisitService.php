<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\QuoteItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 06B-B3 — field visits and consultations, counted against the quantities the
 * contract actually bought.
 */
class VisitService
{
    public function schedule(Project $project, string $scheduledOn, ?User $visitor = null, ?string $purpose = null): ProjectVisit
    {
        return ProjectVisit::create([
            'project_id' => $project->id,
            'visitor_id' => $visitor?->id,
            'scheduled_on' => $scheduledOn,
            'purpose' => $purpose,
            'status' => ProjectVisit::STATUS_SCHEDULED,
        ]);
    }

    /**
     * File the visit report. Recommendations are kept as structured strings so
     * each one can be turned into a corrective task.
     *
     * @param  list<string>  $recommendations
     * @param  list<string>  $evidencePaths
     */
    public function report(
        ProjectVisit $visit,
        ?string $notes,
        ?string $positives,
        ?string $challenges,
        array $recommendations = [],
        array $evidencePaths = [],
    ): ProjectVisit {
        $visit->forceFill([
            'notes' => $notes,
            'positives' => $positives,
            'challenges' => $challenges,
            'recommendations' => array_values($recommendations),
            'evidence_paths' => array_values($evidencePaths),
            'status' => ProjectVisit::STATUS_DONE,
            'reported_at' => now(),
        ])->save();

        return $visit;
    }

    public function approve(ProjectVisit $visit, User $approver): ProjectVisit
    {
        $visit->forceFill(['approved_by' => $approver->id, 'approved_at' => now()])->save();

        return $visit;
    }

    /** Turn one recommendation into a corrective task linked back to the visit. */
    public function createCorrectiveTask(ProjectVisit $visit, string $recommendation, ?User $assignee = null, ?User $assigner = null): Task
    {
        return DB::transaction(fn () => Task::create([
            'title' => 'مهمة تصحيحية: '.$recommendation,
            'description' => 'ناتجة عن تقرير الزيارة رقم '.$visit->id,
            'type' => 'single',
            'assigned_by' => $assigner?->id,
            'assigned_to' => $assignee?->id ?? $visit->project->manager_id,
            'project_id' => $visit->project_id,
            'priority' => 'high',
            'status' => 'new',
            'role_label' => 'مهمة تصحيحية',
            'due_date' => now()->addWeek(),
            'required_evidence' => 'إثبات المعالجة',
        ]));
    }

    public function openConsultation(Project $project, string $subject, ?string $request = null, string $via = Consultation::VIA_INTERNAL): Consultation
    {
        return Consultation::create([
            'project_id' => $project->id,
            'subject' => $subject,
            'request' => $request,
            'requested_via' => $via,
            'status' => Consultation::STATUS_OPEN,
        ]);
    }

    public function assignConsultation(Consultation $consultation, User $specialist): Consultation
    {
        $consultation->forceFill([
            'specialist_id' => $specialist->id,
            'status' => Consultation::STATUS_ASSIGNED,
        ])->save();

        return $consultation;
    }

    public function closeConsultation(Consultation $consultation, string $response): Consultation
    {
        $consultation->forceFill([
            'response' => $response,
            'status' => Consultation::STATUS_CLOSED,
            'closed_at' => now(),
        ])->save();

        return $consultation;
    }

    /**
     * Consumed vs contracted quantities for visits and consultations.
     *
     * @return array<string, array{contracted: int, consumed: int, remaining: int}>
     */
    public function quotas(Project $project): array
    {
        $contracted = fn (string $service) => (int) QuoteItem::query()
            ->whereIn('quote_id', function ($q) use ($project) {
                $q->select('quote_id')
                    ->from('partnership_contracts')
                    ->where('partnership_id', $project->partnership_id)
                    ->whereNull('deleted_at');
            })
            ->where('service_type', $service)
            ->sum('quantity');

        $visitsContracted = $contracted('زيارة');
        $visitsConsumed = $project->visits()->where('status', ProjectVisit::STATUS_DONE)->count();

        $consultationsContracted = $contracted('استشارة');
        $consultationsConsumed = $project->consultations()->where('status', Consultation::STATUS_CLOSED)->count();

        return [
            'زيارة' => [
                'contracted' => $visitsContracted,
                'consumed' => $visitsConsumed,
                'remaining' => max($visitsContracted - $visitsConsumed, 0),
            ],
            'استشارة' => [
                'contracted' => $consultationsContracted,
                'consumed' => $consultationsConsumed,
                'remaining' => max($consultationsContracted - $consultationsConsumed, 0),
            ],
        ];
    }
}
