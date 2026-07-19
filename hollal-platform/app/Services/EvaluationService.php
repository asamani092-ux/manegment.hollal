<?php

namespace App\Services;

use App\Models\EvaluationScore;
use App\Models\PeriodicEvaluation;
use App\Models\Responsibility;
use App\Models\User;

/**
 * 01-B5 — periodic evaluations. The evaluator scores each responsibility (1–5);
 * once published the employee can read the scores and add a single comment, but
 * cannot change any score.
 */
class EvaluationService
{
    public function create(User $employee, string $period, User $evaluator): PeriodicEvaluation
    {
        return PeriodicEvaluation::create([
            'employee_id' => $employee->id,
            'period' => $period,
            'evaluator_id' => $evaluator->id,
            'status' => PeriodicEvaluation::STATUS_DRAFT,
        ]);
    }

    public function recordScore(PeriodicEvaluation $evaluation, Responsibility $responsibility, int $score, ?string $note = null): EvaluationScore
    {
        if ($score < 1 || $score > 5) {
            throw new \InvalidArgumentException('الدرجة يجب أن تكون بين 1 و 5.');
        }

        if ($evaluation->isPublished()) {
            throw new \RuntimeException('لا يمكن تعديل درجات تقييم منشور.');
        }

        return EvaluationScore::updateOrCreate(
            ['periodic_evaluation_id' => $evaluation->id, 'responsibility_id' => $responsibility->id],
            ['score' => $score, 'note' => $note],
        );
    }

    public function publish(PeriodicEvaluation $evaluation): void
    {
        $evaluation->update(['status' => PeriodicEvaluation::STATUS_PUBLISHED]);
    }

    /**
     * Employee's single comment — only after publication.
     */
    public function addEmployeeComment(PeriodicEvaluation $evaluation, string $comment): void
    {
        if (! $evaluation->isPublished()) {
            throw new \RuntimeException('لا يمكن التعليق قبل نشر التقييم.');
        }

        $evaluation->update(['employee_comment' => $comment]);
    }
}
