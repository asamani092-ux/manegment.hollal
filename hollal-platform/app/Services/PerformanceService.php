<?php

namespace App\Services;

use App\Models\EmployeeEvaluation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Task and cycle performance for one employee.
 * Time: O(tasks + evaluations) | Space: O(projects).
 */
class PerformanceService
{
    /** @var array<string, int> */
    private const RATING_SCORE = [
        'متميز' => 4,
        'متوسط' => 3,
        'مقبول' => 2,
        'متأخر' => 1,
    ];

    /**
     * @return array{
     *     tasks: array{count: int, on_time_pct: float, overdue: int, average_rating: ?float},
     *     projects: array<int|string, array{count: int, on_time_pct: float, overdue: int, average_rating: ?float}>,
     *     cycles: list<array{name: string, score: ?float}>
     * }
     */
    public function summary(User $user, Carbon|string $from, Carbon|string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        $tasks = Task::query()
            ->where('assigned_to', $user->id)
            ->whereBetween('due_date', [$start, $end])
            ->get(['id', 'status', 'due_date', 'completed_at', 'final_rating', 'project_id']);

        $projects = [];
        foreach ($tasks->groupBy(fn (Task $task) => $task->project_id ?? 'none') as $key => $group) {
            $projects[$key] = $this->metrics($group);
        }

        $cycles = EmployeeEvaluation::query()
            ->where('employee_id', $user->id)
            ->whereIn('status', [EmployeeEvaluation::STATUS_APPROVED, EmployeeEvaluation::STATUS_ARCHIVED])
            ->whereHas('cycle', function ($query) use ($start, $end) {
                $query->whereDate('starts_at', '<=', $end->toDateString())
                    ->whereDate('ends_at', '>=', $start->toDateString());
            })
            ->with('cycle:id,name,quarter,year,starts_at,ends_at')
            ->get()
            ->map(fn (EmployeeEvaluation $row) => [
                'name' => $row->cycle?->periodLabel() ?? '',
                'score' => $row->total_score !== null ? (float) $row->total_score : null,
            ])
            ->values()
            ->all();

        return [
            'tasks' => $this->metrics($tasks),
            'projects' => $projects,
            'cycles' => $cycles,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Task>  $tasks
     * @return array{count: int, on_time_pct: float, overdue: int, average_rating: ?float}
     */
    private function metrics($tasks): array
    {
        $count = $tasks->count();
        $onTime = $tasks->filter(function (Task $task) {
            return $task->status === 'completed'
                && $task->completed_at
                && $task->due_date
                && $task->completed_at->lte($task->due_date);
        })->count();
        $overdue = $tasks->filter(function (Task $task) {
            return $task->status === 'overdue'
                || ($task->status !== 'completed' && $task->due_date && $task->due_date->lt(now()));
        })->count();
        $scores = $tasks->map(fn (Task $task) => self::RATING_SCORE[$task->final_rating] ?? null)->filter(fn ($score) => $score !== null);

        return [
            'count' => $count,
            'on_time_pct' => $count === 0 ? 0.0 : round(($onTime / $count) * 100, 1),
            'overdue' => $overdue,
            'average_rating' => $scores->isEmpty() ? null : round($scores->avg(), 2),
        ];
    }
}
