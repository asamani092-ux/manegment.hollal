<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\User;

/**
 * 02-B1 — task lifecycle with the triple evaluation (self → PM → final) and an
 * evidence gate. Every status change is captured in task_status_logs so the
 * "تحتاج تعديلًا" loop preserves full history.
 */
class TaskLifecycleService
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_COMPLETED = 'completed';

    public function submitForReview(Task $task, User $assignee, string $selfRating, ?string $note = null): Task
    {
        if ($assignee->id !== $task->assigned_to) {
            throw new \RuntimeException('التسليم للمراجعة يقتصر على المكلَّف بالمهمة.');
        }

        if ($task->required_evidence !== null && blank($task->submitted_file)) {
            throw new \RuntimeException('لا يمكن التسليم للمراجعة دون رفع الدليل المطلوب.');
        }

        $this->assertRating($selfRating);

        $this->transition($task, self::STATUS_PENDING_REVIEW, $assignee, $note);
        $task->update(['self_rating' => $selfRating, 'submission_note' => $note]);

        return $task;
    }

    public function recordPmRating(Task $task, User $pm, string $rating): Task
    {
        if ($task->project_id === null || $pm->id !== $task->project?->manager_id) {
            throw new \RuntimeException('تقييم مدير المشروع يقتصر على مدير مشروع المهمة.');
        }

        $this->assertRating($rating);
        $task->update(['pm_rating' => $rating]);

        return $task;
    }

    public function recordFinalRating(Task $task, User $assigner, string $rating, ?string $notes = null): Task
    {
        if ($assigner->id !== $task->assigned_by) {
            throw new \RuntimeException('التقييم النهائي يقتصر على مُسنِد المهمة.');
        }

        if ($task->project_id !== null && $task->pm_rating === null) {
            throw new \RuntimeException('يلزم تقييم مدير المشروع قبل التقييم النهائي.');
        }

        $this->assertRating($rating);

        $this->transition($task, self::STATUS_COMPLETED, $assigner, $notes);
        $task->update([
            'final_rating' => $rating,
            'final_notes' => $notes,
            'completed_at' => now(),
        ]);

        return $task;
    }

    public function requestRevision(Task $task, User $assigner, string $note): Task
    {
        if ($assigner->id !== $task->assigned_by) {
            throw new \RuntimeException('طلب التعديل يقتصر على مُسنِد المهمة.');
        }

        $this->transition($task, self::STATUS_IN_PROGRESS, $assigner, 'تحتاج تعديلًا: '.$note);

        return $task;
    }

    private function transition(Task $task, string $to, User $changedBy, ?string $note): void
    {
        TaskStatusLog::create([
            'task_id' => $task->id,
            'from_status' => $task->status,
            'to_status' => $to,
            'changed_by' => $changedBy->id,
            'note' => $note,
            'created_at' => now(),
        ]);

        $task->update(['status' => $to]);
    }

    private function assertRating(string $rating): void
    {
        if (! in_array($rating, Task::RATINGS, true)) {
            throw new \InvalidArgumentException('تقييم غير صالح.');
        }
    }
}
