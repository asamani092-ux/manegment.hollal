<?php

namespace Tests\Feature;

use App\Livewire\Tasks\TasksIndex;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskAssigneeNotesTest extends TestCase
{
    use RefreshDatabase;

    protected User $assigner;

    protected User $assignee;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->assigner = User::factory()->create(['phone' => '0501111111']);
        $this->assigner->givePermissionTo(['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete']);

        $this->assignee = User::factory()->create(['phone' => '0502222222']);
        $this->assignee->givePermissionTo(['tasks.view']);

        $this->task = Task::factory()->create([
            'title' => 'مهمة للمُنفّذ',
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
        ]);
    }

    public function test_assignee_cannot_update_task_they_receive(): void
    {
        Livewire::actingAs($this->assignee)
            ->test(TasksIndex::class)
            ->call('openTaskEdit', $this->task->id)
            ->assertForbidden();
    }

    public function test_assignee_can_post_note_and_see_it_in_detail(): void
    {
        Livewire::actingAs($this->assignee)
            ->test(TasksIndex::class)
            ->call('openTaskView', $this->task->id)
            ->set('noteBody', 'ملاحظة من المُنفّذ')
            ->call('addTaskNote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('task_notes', [
            'task_id' => $this->task->id,
            'author_id' => $this->assignee->id,
            'body' => 'ملاحظة من المُنفّذ',
        ]);

        Livewire::actingAs($this->assignee)
            ->test(TasksIndex::class)
            ->call('openTaskView', $this->task->id)
            ->assertSee('ملاحظة من المُنفّذ');
    }

    public function test_card_view_renders_on_narrow_viewport(): void
    {
        $this->actingAs($this->assignee)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('ds-task-card', false)
            ->assertSee('مهمة للمُنفّذ');
    }

    public function test_task_policy_blocks_assignee_only_update(): void
    {
        $this->assertFalse($this->assignee->can('update', $this->task));
        $this->assertTrue($this->assignee->can('addNote', $this->task));
        $this->assertTrue($this->assigner->can('update', $this->task));
    }
}
