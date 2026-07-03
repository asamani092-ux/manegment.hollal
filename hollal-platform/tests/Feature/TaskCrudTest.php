<?php

namespace Tests\Feature;

use App\Livewire\Tasks\TasksIndex;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $assigner;

    protected User $assignee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->assigner = User::factory()->create(['phone' => '0501111111']);
        $this->assigner->givePermissionTo(['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete']);

        $this->assignee = User::factory()->create(['phone' => '0502222222']);
    }

    public function test_assigner_can_create_task_via_livewire(): void
    {
        Livewire::actingAs($this->assigner)
            ->test(TasksIndex::class)
            ->call('openTaskCreate')
            ->set('title', 'مهمة جديدة')
            ->set('description', 'وصف المهمة')
            ->set('assigned_to', $this->assignee->id)
            ->set('priority', 'high')
            ->set('status', 'new')
            ->call('saveTask')
            ->assertHasNoErrors()
            ->assertSet('showTaskModal', false);

        $this->assertDatabaseHas('tasks', [
            'title' => 'مهمة جديدة',
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
            'priority' => 'high',
            'status' => 'new',
        ]);
    }

    public function test_assigner_can_view_task_via_livewire(): void
    {
        $task = Task::factory()->create([
            'title' => 'مهمة للعرض',
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
        ]);

        Livewire::actingAs($this->assigner)
            ->test(TasksIndex::class)
            ->call('openTaskView', $task->id)
            ->assertSet('showTaskModal', true)
            ->assertSet('taskViewOnly', true)
            ->assertSet('title', 'مهمة للعرض');
    }

    public function test_assigner_can_update_task_via_livewire(): void
    {
        $task = Task::factory()->create([
            'title' => 'عنوان قديم',
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
            'status' => 'new',
        ]);

        Livewire::actingAs($this->assigner)
            ->test(TasksIndex::class)
            ->call('openTaskEdit', $task->id)
            ->set('title', 'عنوان محدّث')
            ->set('status', 'in_progress')
            ->call('saveTask')
            ->assertHasNoErrors()
            ->assertSet('showTaskModal', false);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'عنوان محدّث',
            'status' => 'in_progress',
        ]);
    }

    public function test_assigner_can_delete_task_via_livewire(): void
    {
        $task = Task::factory()->create([
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
        ]);

        Livewire::actingAs($this->assigner)
            ->test(TasksIndex::class)
            ->call('deleteTask', $task->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
