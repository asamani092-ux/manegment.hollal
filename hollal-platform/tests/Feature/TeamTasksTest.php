<?php

namespace Tests\Feature;

use App\Livewire\Tasks\TeamTasksIndex;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02-B2 — team scope by manager_id tree + approval queue.
 */
class TeamTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_team_scope_respects_manager_hierarchy(): void
    {
        $manager = User::factory()->create();
        $subordinate = User::factory()->create(['manager_id' => $manager->id]);
        $outsider = User::factory()->create();

        $inTeam = Task::factory()->create(['assigned_to' => $subordinate->id]);
        Task::factory()->create(['assigned_to' => $outsider->id]);

        $teamTaskIds = Task::query()->teamOf($manager)->pluck('id');

        $this->assertTrue($teamTaskIds->contains($inTeam->id));
        $this->assertCount(1, $teamTaskIds);
    }

    public function test_approval_queue_filters_to_assigner_pending_review(): void
    {
        $assigner = User::factory()->create();
        $otherAssigner = User::factory()->create();

        $mine = Task::factory()->create(['assigned_by' => $assigner->id, 'status' => 'pending_review']);
        Task::factory()->create(['assigned_by' => $assigner->id, 'status' => 'in_progress']);
        Task::factory()->create(['assigned_by' => $otherAssigner->id, 'status' => 'pending_review']);

        $queue = Task::query()->pendingApprovalFor($assigner)->pluck('id');

        $this->assertSame([$mine->id], $queue->all());
    }

    public function test_assigner_can_approve_from_queue(): void
    {
        $assigner = User::factory()->create();
        $assigner->givePermissionTo('esnad.tasks.view');
        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'project_id' => null,
            'status' => 'pending_review',
        ]);

        Livewire::actingAs($assigner)
            ->test(TeamTasksIndex::class)
            ->call('approve', $task->id, 'متميز', 'عمل ممتاز');

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('متميز', $task->fresh()->final_rating);
    }

    public function test_assigner_can_return_from_queue(): void
    {
        $assigner = User::factory()->create();
        $assigner->givePermissionTo('esnad.tasks.view');
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'status' => 'pending_review',
        ]);

        Livewire::actingAs($assigner)
            ->test(TeamTasksIndex::class)
            ->call('returnTask', $task->id, 'يرجى إضافة المرفقات');

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_team_tab_requires_team_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('esnad.tasks.view');

        Livewire::actingAs($user)
            ->test(TeamTasksIndex::class)
            ->assertOk()
            ->assertViewHas('teamTasks', fn ($tasks) => $tasks->isEmpty());
    }
}
