<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Tasks\TeamTasksIndex;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** TASK-1 / MEET-1 polish on core-tools-completion. */
class TaskMeetPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_team_reminder_notifies_subordinates_with_open_tasks(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo('esnad.tasks.team.view');
        $emp = User::factory()->create(['manager_id' => $manager->id, 'is_active' => true]);
        Task::factory()->create(['assigned_to' => $emp->id, 'status' => 'in_progress']);

        Livewire::actingAs($manager)
            ->test(TeamTasksIndex::class)
            ->call('sendTeamReminder');

        Notification::assertSentTo($emp, TaskReminder::class);
    }

    public function test_approved_minutes_block_item_delete(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['meetings.view', 'meetings.update']);
        $meeting = Meeting::factory()->create([
            'chair_id' => $user->id,
            'secretary_id' => $user->id,
            'approval_status' => Meeting::APPROVAL_APPROVED,
        ]);
        $item = MeetingItem::factory()->create(['meeting_id' => $meeting->id, 'responsible_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('deleteItem', $item->id);

        $this->assertDatabaseHas('meeting_items', ['id' => $item->id]);
    }

    public function test_team_tasks_filter_by_assignee_query(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.team.view']);
        $emp = User::factory()->create(['manager_id' => $manager->id]);
        Task::factory()->create(['assigned_to' => $emp->id, 'assigned_by' => $manager->id, 'status' => 'in_progress']);

        $this->actingAs($manager)
            ->get(route('team-tasks.index', ['tab' => 'team', 'assigneeId' => $emp->id]))
            ->assertOk();
    }
}
