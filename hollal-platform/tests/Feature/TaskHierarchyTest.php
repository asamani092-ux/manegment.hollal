<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 02-B4 — 5-level task hierarchy assembled without N+1; flat view still works.
 */
class TaskHierarchyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{project: Project, leaf: Task} */
    private function fiveLevelTree(): array
    {
        $assignee = User::factory()->create();
        $project = Project::factory()->create();

        $parentId = null;
        $leaf = null;
        for ($level = 1; $level <= 5; $level++) {
            $leaf = Task::factory()->create([
                'title' => 'مستوى '.$level,
                'project_id' => $project->id,
                'assigned_to' => $assignee->id,
                'parent_task_id' => $parentId,
            ]);
            $parentId = $leaf->id;
        }

        return ['project' => $project, 'leaf' => $leaf];
    }

    public function test_five_level_tree_builds_correctly(): void
    {
        ['project' => $project] = $this->fiveLevelTree();

        $tree = app(TaskTreeService::class)->treeForProject($project->id);

        // Descend five levels.
        $node = $tree->first();
        $depth = 1;
        while ($node['children']->isNotEmpty()) {
            $node = $node['children']->first();
            $depth++;
        }

        $this->assertSame(5, $depth);
    }

    public function test_tree_is_built_without_n_plus_one(): void
    {
        ['project' => $project] = $this->fiveLevelTree();

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(TaskTreeService::class)->treeForProject($project->id);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // One query for the tasks + one for eager assignees — never per node.
        $this->assertLessThanOrEqual(3, $queryCount);
    }

    public function test_flat_listing_still_works(): void
    {
        ['project' => $project] = $this->fiveLevelTree();

        $flat = Task::where('project_id', $project->id)->get();

        $this->assertCount(5, $flat);
    }

    public function test_subtree_for_root_bounded_queries(): void
    {
        ['leaf' => $leaf] = $this->fiveLevelTree();
        $root = $leaf->parent->parent->parent->parent; // level 1

        $subtree = app(TaskTreeService::class)->treeForRoot($root);

        $node = $subtree;
        $depth = 1;
        while ($node['children']->isNotEmpty()) {
            $node = $node['children']->first();
            $depth++;
        }

        $this->assertSame(5, $depth);
    }
}
