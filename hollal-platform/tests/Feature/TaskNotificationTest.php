<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyTasksOverdue;
use App\Livewire\Tasks\TasksIndex;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskOverdue;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $assigner;

    protected User $assignee;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->manager = User::factory()->create(['phone' => '0503333333']);
        $this->assigner = User::factory()->create(['phone' => '0501111111']);
        $this->assigner->givePermissionTo(['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete']);

        $this->assignee = User::factory()->create([
            'phone' => '0502222222',
            'manager_id' => $this->manager->id,
        ]);
    }

    public function test_task_assigned_notification_is_created_on_task_creation(): void
    {
        Livewire::actingAs($this->assigner)
            ->test(TasksIndex::class)
            ->call('openTaskCreate')
            ->set('title', 'مهمة للإشعار')
            ->set('assigned_to', $this->assignee->id)
            ->set('priority', 'medium')
            ->set('status', 'new')
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->assignee->id,
            'type' => TaskAssigned::class,
        ]);

        $notification = $this->assignee->fresh()->notifications->first();
        $this->assertStringContainsString('مهمة للإشعار', $notification->data['message']);
        $this->assertSame(route('tasks.index'), $notification->data['url']);
    }

    public function test_manager_is_notified_after_forty_eight_hours_overdue(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');

        $task = Task::factory()->create([
            'title' => 'مهمة متأخرة',
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
            'due_date' => now()->subHours(49),
            'status' => 'in_progress',
        ]);

        Artisan::call(NotifyTasksOverdue::class);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->assignee->id,
            'type' => TaskOverdue::class,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->manager->id,
            'type' => TaskOverdue::class,
        ]);

        $managerNotification = $this->manager->fresh()->notifications->first();
        $this->assertTrue($managerNotification->data['escalation'] ?? false);
        $this->assertStringContainsString('48', $managerNotification->data['message']);

        Carbon::setTestNow();
    }
}
