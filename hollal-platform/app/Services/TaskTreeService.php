<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * 02-B4 — assemble the task hierarchy (up to 5 levels) without N+1. All tasks in
 * scope are fetched in a single query and nested in memory.
 */
class TaskTreeService
{
    /**
     * Nested tree of all tasks in a project.
     *
     * @return Collection<int, array{task: Task, children: Collection}>
     */
    public function treeForProject(int $projectId): Collection
    {
        $tasks = Task::query()
            ->where('project_id', $projectId)
            ->with(['assignee:id,name'])
            ->orderBy('id')
            ->get();

        return $this->nest($tasks, null);
    }

    /**
     * Nested subtree beneath a root task (single query for the whole subtree).
     *
     * @return array{task: Task, children: Collection}
     */
    public function treeForRoot(Task $root): array
    {
        $all = collect([$root]);
        $frontier = [$root->id];

        // Bounded by tree depth, not node count — never one query per node.
        for ($depth = 0; $depth < 10 && $frontier !== []; $depth++) {
            $children = Task::query()->whereIn('parent_task_id', $frontier)->get();
            if ($children->isEmpty()) {
                break;
            }
            $all = $all->merge($children);
            $frontier = $children->pluck('id')->all();
        }

        return [
            'task' => $root,
            'children' => $this->nest($all, $root->id),
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, array{task: Task, children: Collection}>
     */
    private function nest(Collection $tasks, ?int $parentId): Collection
    {
        return $tasks
            ->where('parent_task_id', $parentId)
            ->map(fn (Task $task) => [
                'task' => $task,
                'children' => $this->nest($tasks, $task->id),
            ])
            ->values();
    }
}
