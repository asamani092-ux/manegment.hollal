<?php

namespace Tests\Feature;

use App\Livewire\Tasks\TasksCalendar;
use App\Livewire\Tasks\TasksIndex;
use App\Livewire\Tasks\TeamTasksIndex;
use App\Models\MeetingItem;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Esnad tab round 1 — ACL, approve-from-tasks, calendar due date, merged follow-up.
 */
class EsnadTabRound1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_employee_forbidden_on_team_followup_and_workload_redirect(): void
    {
        $employee = User::factory()->create(['must_change_password' => false]);
        $employee->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.create']);

        $this->actingAs($employee)->get(route('team-tasks.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('workload-board.index'))->assertForbidden();
    }

    public function test_assigner_approves_from_tasks_index(): void
    {
        $assigner = User::factory()->create(['must_change_password' => false]);
        $assigner->givePermissionTo('esnad.tasks.view');
        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'project_id' => null,
            'status' => 'pending_review',
        ]);

        Livewire::actingAs($assigner)
            ->test(TasksIndex::class)
            ->assertSee('بانتظار اعتمادي', false)
            ->call('approve', $task->id, 'متميز', 'تم')
            ->assertHasNoErrors();

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('متميز', $task->fresh()->final_rating);
    }

    public function test_tasks_index_status_completed_closes_linked_decision(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.update']);
        $task = Task::factory()->create([
            'assigned_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => 'in_progress',
        ]);
        $item = MeetingItem::factory()->create([
            'decision' => 'قرار من المهام',
            'status' => 'in_progress',
            'task_id' => $task->id,
        ]);

        Livewire::actingAs($user)
            ->test(TasksIndex::class)
            ->call('updateTaskStatus', $task->id, 'completed')
            ->assertHasNoErrors();

        $this->assertSame('completed', $task->fresh()->status);
        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertSame('أُغلقت بإكمال المهمة', $item->close_reason);
        $this->assertNotNull($item->closed_at);
    }

    public function test_open_decisions_page_heals_stale_open_items_for_completed_tasks(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['meetings.view', 'esnad.tasks.view']);
        $task = Task::factory()->create([
            'assigned_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $item = MeetingItem::factory()->create([
            'decision' => 'قرار قديم معلّق',
            'status' => 'in_progress',
            'task_id' => $task->id,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Meetings\OpenDecisionsIndex::class)
            ->assertOk();

        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertSame('أُغلقت بإكمال المهمة', $item->close_reason);
    }

    public function test_manager_sees_loads_tab_on_team_followup(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.team.view']);
        User::factory()->create(['manager_id' => $manager->id, 'is_active' => true]);

        Livewire::actingAs($manager)
            ->test(TeamTasksIndex::class, ['tab' => 'loads'])
            ->assertSet('tab', 'loads')
            ->assertSee('أحمال الفريق', false)
            ->assertSee('تذكير جماعي', false);
    }

    public function test_calendar_updates_due_date_and_shows_arabic_status(): void
    {
        $assigner = User::factory()->create(['must_change_password' => false]);
        $assigner->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.update']);
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'assigned_to' => $assigner->id,
            'status' => 'in_progress',
            'due_date' => now()->startOfMonth()->addDays(5)->setTime(10, 0),
        ]);

        $newDue = now()->startOfMonth()->addDays(8)->format('Y-m-d\T14:00');

        Livewire::actingAs($assigner)
            ->test(TasksCalendar::class)
            ->call('openTask', $task->id)
            ->assertSee('قيد التنفيذ', false)
            ->set('editDueDate', $newDue)
            ->call('saveDueDate')
            ->assertHasNoErrors();

        $this->assertSame(
            now()->startOfMonth()->addDays(8)->format('Y-m-d'),
            $task->fresh()->due_date->format('Y-m-d')
        );
    }

    public function test_workload_redirects_to_team_followup(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo('esnad.tasks.team.view');

        $this->actingAs($manager)
            ->get(route('workload-board.index', ['tab' => 'recurring']))
            ->assertRedirect(route('team-tasks.index', ['tab' => 'recurring']));
    }
}
