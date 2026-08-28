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

    /**
     * Bulk create: ensure shared criteria as responsibilities, then create missing period evaluations.
     * Time: O(e × c) | Space: O(e)
     *
     * @param  list<int>  $employeeIds
     * @param  list<string>  $criteria
     */
    public function createBulk(array $employeeIds, string $period, User $evaluator, array $criteria): int
    {
        $created = 0;

        foreach ($employeeIds as $employeeId) {
            $employee = User::query()->find($employeeId);
            if (! $employee) {
                continue;
            }

            $order = 1;
            foreach ($criteria as $body) {
                $exists = Responsibility::query()
                    ->where('employee_id', $employee->id)
                    ->where('body', $body)
                    ->where('is_active', true)
                    ->exists();
                if (! $exists) {
                    Responsibility::create([
                        'employee_id' => $employee->id,
                        'body' => $body,
                        'order' => $order,
                        'is_active' => true,
                    ]);
                }
                $order++;
            }

            $already = PeriodicEvaluation::query()
                ->where('employee_id', $employee->id)
                ->where('period', $period)
                ->exists();
            if ($already) {
                continue;
            }

            $this->create($employee, $period, $evaluator);
            $created++;
        }

        return $created;
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

    public function archive(PeriodicEvaluation $evaluation): void
    {
        if ($evaluation->isArchived()) {
            return;
        }

        if (! $this->scoresComplete($evaluation)) {
            throw new \RuntimeException('أكمل درجات كل المسؤوليات النشطة قبل الأرشفة.');
        }

        $evaluation->update(['status' => PeriodicEvaluation::STATUS_ARCHIVED]);
    }

    public function scoresComplete(PeriodicEvaluation $evaluation): bool
    {
        $responsibilityIds = Responsibility::query()
            ->where('employee_id', $evaluation->employee_id)
            ->where('is_active', true)
            ->pluck('id');

        if ($responsibilityIds->isEmpty()) {
            return false;
        }

        $scoredIds = $evaluation->scores()
            ->whereIn('responsibility_id', $responsibilityIds)
            ->whereNotNull('score')
            ->pluck('responsibility_id');

        return $scoredIds->count() === $responsibilityIds->count();
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
