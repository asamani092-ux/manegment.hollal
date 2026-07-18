<?php

namespace Tests\Feature;

use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Tasks\TasksIndex;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HorizontalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $assigner;

    protected User $assignee;

    protected User $outsider;

    protected Task $task;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->manager = User::factory()->create(['phone' => '0504444444']);
        $this->assigner = User::factory()->create(['phone' => '0501111111']);
        $this->assignee = User::factory()->create(['phone' => '0502222222']);
        $this->outsider = User::factory()->create(['phone' => '0503333333']);

        $this->outsider->givePermissionTo(['esnad.tasks.view', 'projects.view']);

        $this->task = Task::factory()->create([
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
        ]);

        $this->project = Project::factory()->create([
            'manager_id' => $this->manager->id,
        ]);
    }

    public function test_user_cannot_edit_task_they_are_not_involved_in(): void
    {
        Livewire::actingAs($this->outsider)
            ->test(TasksIndex::class)
            ->call('openTaskEdit', $this->task->id)
            ->assertForbidden();
    }

    public function test_user_cannot_delete_task_they_are_not_involved_in(): void
    {
        Livewire::actingAs($this->outsider)
            ->test(TasksIndex::class)
            ->call('deleteTask', $this->task->id)
            ->assertForbidden();
    }

    public function test_user_cannot_update_status_of_task_they_are_not_involved_in(): void
    {
        Livewire::actingAs($this->outsider)
            ->test(TasksIndex::class)
            ->call('updateTaskStatus', $this->task->id, 'completed')
            ->assertForbidden();
    }

    public function test_user_cannot_edit_project_they_do_not_manage_or_belong_to(): void
    {
        Livewire::actingAs($this->outsider)
            ->test(ProjectsIndex::class)
            ->call('openProjectEdit', $this->project->id)
            ->assertForbidden();
    }

    public function test_user_cannot_delete_project_they_do_not_manage_or_belong_to(): void
    {
        Livewire::actingAs($this->outsider)
            ->test(ProjectsIndex::class)
            ->call('deleteProject', $this->project->id)
            ->assertForbidden();
    }
}
