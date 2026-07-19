<?php

namespace App\Services;

use App\Models\BeneficiaryGroup;
use App\Models\MeasurementForm;
use App\Models\MeasurementResponse;
use App\Models\OrganizationImpactRecord;
use App\Models\Project;

/**
 * 06B-B4 — measurement and impact.
 *
 * Results are always derived from the recorded answers; the improvement figure
 * is the pre/post difference. On close the impact ascends to the organization
 * (and, through the project, the program) record.
 */
class MeasurementService
{
    /**
     * Record one filled form. The score is summed from the answers and capped
     * by each question's max — nothing is accepted pre-totalled.
     *
     * @param  array<int|string, float|int>  $answers  question id => score
     */
    public function recordResponse(
        Project $project,
        MeasurementForm $form,
        string $phase,
        array $answers,
        ?BeneficiaryGroup $group = null,
    ): MeasurementResponse {
        if (! in_array($phase, [MeasurementResponse::PHASE_PRE, MeasurementResponse::PHASE_POST], true)) {
            throw new \InvalidArgumentException('مرحلة القياس يجب أن تكون قبلي أو بعدي');
        }

        $questions = $form->questions()->get()->keyBy('id');
        $total = 0.0;
        $clean = [];

        foreach ($answers as $questionId => $score) {
            $question = $questions->get((int) $questionId);

            if (! $question) {
                throw new \InvalidArgumentException('سؤال لا ينتمي لهذا النموذج');
            }

            $value = min(max((float) $score, 0), (float) $question->max_score);
            $clean[(int) $questionId] = $value;
            $total += $value;
        }

        return MeasurementResponse::create([
            'project_id' => $project->id,
            'measurement_form_id' => $form->id,
            'beneficiary_group_id' => $group?->id,
            'phase' => $phase,
            'answers' => $clean,
            'total_score' => round($total, 2),
            'max_score' => $form->maxScore(),
        ]);
    }

    /**
     * Pre/post comparison for a project.
     *
     * @return array{pre_percent: ?float, post_percent: ?float, improvement_percent: ?float, satisfaction_percent: ?float, beneficiaries: int}
     */
    public function results(Project $project): array
    {
        $responses = MeasurementResponse::where('project_id', $project->id)->with('form')->get();

        $tests = $responses->filter(fn ($r) => $r->form?->kind === MeasurementForm::KIND_TEST);
        $pre = $this->averagePercent($tests->where('phase', MeasurementResponse::PHASE_PRE));
        $post = $this->averagePercent($tests->where('phase', MeasurementResponse::PHASE_POST));

        $satisfaction = $this->averagePercent(
            $responses->filter(fn ($r) => $r->form?->kind === MeasurementForm::KIND_SATISFACTION)
        );

        return [
            'pre_percent' => $pre,
            'post_percent' => $post,
            'improvement_percent' => ($pre !== null && $post !== null) ? round($post - $pre, 2) : null,
            'satisfaction_percent' => $satisfaction,
            'beneficiaries' => (int) $project->beneficiaryGroups()->sum('size'),
        ];
    }

    /**
     * 06B-B4 — impact ascent: write the project's results into the partner
     * organization's cumulative impact record (idempotent per project).
     */
    public function ascendImpact(Project $project): ?OrganizationImpactRecord
    {
        $organizationId = $project->partnership?->organization_id;

        if (! $organizationId) {
            return null;
        }

        $results = $this->results($project);

        return OrganizationImpactRecord::updateOrCreate(
            ['organization_id' => $organizationId, 'project_id' => $project->id],
            [
                'program_id' => $project->program_id,
                'beneficiaries' => $results['beneficiaries'],
                'improvement_percent' => $results['improvement_percent'],
                'satisfaction_percent' => $results['satisfaction_percent'],
                'summary' => 'أثر مشروع: '.$project->name,
            ],
        );
    }

    /** @param \Illuminate\Support\Collection<int, MeasurementResponse> $responses */
    private function averagePercent($responses): ?float
    {
        if ($responses->isEmpty()) {
            return null;
        }

        return round($responses->avg(fn (MeasurementResponse $r) => $r->percent()), 2);
    }
}
