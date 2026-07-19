<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;

/**
 * 06B-B2 — weighted progress. Only tasks carrying a FINAL triple-evaluation
 * rating count, and each rating carries its own weight; self and PM ratings
 * never move the number on their own.
 */
class ProjectProgressService
{
    /** @var array<string, float> final rating => weight */
    public const RATING_WEIGHTS = [
        'متميز' => 1.0,
        'متوسط' => 0.75,
        'مقبول' => 0.5,
        'متأخر' => 0.25,
    ];

    /**
     * @return array{total: int, evaluated: int, weighted_percent: float, overdue: int, distribution: array<string, int>}
     */
    public function summary(Project $project): array
    {
        $tasks = $project->tasks()->get();
        $total = $tasks->count();

        $evaluated = $tasks->filter(fn (Task $t) => isset(self::RATING_WEIGHTS[(string) $t->final_rating]));

        $weightSum = $evaluated->sum(fn (Task $t) => self::RATING_WEIGHTS[(string) $t->final_rating]);

        $distribution = [];
        foreach (array_keys(self::RATING_WEIGHTS) as $rating) {
            $distribution[$rating] = $evaluated->where('final_rating', $rating)->count();
        }

        return [
            'total' => $total,
            'evaluated' => $evaluated->count(),
            'weighted_percent' => $total > 0 ? round(($weightSum / $total) * 100, 2) : 0.0,
            'overdue' => $project->tasks()->overdue()->count(),
            'distribution' => $distribution,
        ];
    }

    /**
     * The project plan as a tree, built from parent_task_id.
     *
     * @return \Illuminate\Support\Collection<int, Task>
     */
    public function planTree(Project $project)
    {
        $tasks = $project->tasks()->orderBy('id')->get();
        $byParent = $tasks->groupBy('parent_task_id');

        $attach = function (Task $task) use (&$attach, $byParent) {
            $task->setRelation('children', ($byParent[$task->id] ?? collect())->each($attach)->values());

            return $task;
        };

        return ($byParent[null] ?? collect())->each($attach)->values();
    }
}
