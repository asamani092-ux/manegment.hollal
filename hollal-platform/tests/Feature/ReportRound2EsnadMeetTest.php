<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingsIndex;
use App\Livewire\Meetings\OpenDecisionsIndex;
use App\Livewire\Tasks\TasksCalendar;
use App\Livewire\Tasks\TasksIndex;
use App\Livewire\Tasks\TeamTasksIndex;
use App\Livewire\Tasks\WorkloadBoard;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MeetingInvite;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 2 — Batch 3 Esnad + Meetings.
 */
class ReportRound2EsnadMeetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_completed_task_card_has_is_completed_class(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['esnad.tasks.view']);
        Task::factory()->create([
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'status' => 'completed',
            'title' => 'مهمة مكتملة خضراء',
        ]);

        Livewire::actingAs($user)
            ->test(TasksIndex::class)
            ->assertSee('is-completed', false)
            ->assertSee('مهمة مكتملة خضراء', false);
    }

    public function test_team_task_detail_modal_shows_evidence_and_notes(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.team.view']);
        $member = User::factory()->create(['manager_id' => $manager->id, 'must_change_password' => false]);

        $task = Task::factory()->create([
            'assigned_by' => $manager->id,
            'assigned_to' => $member->id,
            'status' => 'pending_review',
            'submitted_file' => 'tasks/evidence.pdf',
            'attachment_path' => 'tasks/brief.pdf',
            'title' => 'مهمة فريق للتفاصيل',
        ]);

        Livewire::actingAs($manager)
            ->test(TeamTasksIndex::class)
            ->call('openDetail', $task->id)
            ->assertSet('showDetail', true)
            ->assertSee('مهمة فريق للتفاصيل', false)
            ->assertSee('تنزيل الشاهد', false)
            ->assertSee('تنزيل المرفق', false)
            ->assertSee('اعتماد من التفاصيل', false);
    }

    public function test_calendar_grid_shows_day_headers(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['esnad.tasks.view']);

        Livewire::actingAs($user)
            ->test(TasksCalendar::class)
            ->assertSee('السبت', false)
            ->assertSee('الأحد', false)
            ->assertSee('الجمعة', false)
            ->assertSeeHtml('ds-cal-grid')
            ->assertSeeHtml('ds-cal-dow');
    }

    public function test_recurring_generated_instances_listed_with_status_links(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['esnad.tasks.create', 'esnad.tasks.update', 'esnad.tasks.view']);

        $template = RecurringTaskTemplate::create([
            'title' => 'قالب تقرير',
            'assigned_to_id' => $user->id,
            'created_by' => $user->id,
            'pattern' => RecurringTaskTemplate::PATTERN_WEEKLY,
            'day_of_week' => 1,
            'is_active' => true,
        ]);

        $instance = Task::factory()->create([
            'title' => 'نسخة مولّدة من القالب',
            'recurring_template_id' => $template->id,
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'status' => 'in_progress',
        ]);

        Livewire::actingAs($user)
            ->test(WorkloadBoard::class, ['tab' => 'recurring'])
            ->assertSee('قوالب متكررة', false)
            ->assertSee($template->title, false)
            ->set('tab', 'reminders')
            ->set('followUpUserId', $user->id)
            ->assertSee('نسخة مولّدة من القالب', false)
            ->assertSee('قيد التنفيذ', false);
    }

    public function test_meeting_invite_notification_faked_on_create_with_attendees(): void
    {
        Notification::fake();

        $chair = User::factory()->create(['must_change_password' => false]);
        $chair->givePermissionTo(['meetings.view', 'meetings.create', 'meetings.update']);
        $attendee = User::factory()->create(['must_change_password' => false, 'email' => 'attendee@example.test']);

        Livewire::actingAs($chair)
            ->test(MeetingsIndex::class)
            ->call('openCreate')
            ->set('title', 'اجتماع الدعوة')
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('location', 'قاعة الاجتماعات')
            ->set('remote_link', 'https://meet.example.test/room')
            ->set('agenda', 'بند أول وبند ثانٍ')
            ->set('attendeeIds', [$attendee->id])
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($attendee, MeetingInvite::class);
        $this->assertDatabaseHas('meetings', [
            'title' => 'اجتماع الدعوة',
            'location' => 'قاعة الاجتماعات',
            'link' => 'https://meet.example.test/room',
        ]);
    }

    public function test_open_decisions_archive_tab_lists_closed_with_reason(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['meetings.view', 'meetings.update']);
        $meeting = Meeting::factory()->create();

        MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'decision' => 'قرار مؤرشف',
            'status' => 'done',
            'close_reason' => 'نُفّذ خارج المنصة',
            'closed_at' => now(),
        ]);

        MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'decision' => 'قرار ما زال مفتوحًا',
            'status' => 'open',
        ]);

        Livewire::actingAs($user)
            ->test(OpenDecisionsIndex::class)
            ->assertSee('تُنشأ القرارات من بنود المحضر', false)
            ->set('tab', 'archived')
            ->assertSee('قرار مؤرشف', false)
            ->assertSee('نُفّذ خارج المنصة', false)
            ->assertDontSee('قرار ما زال مفتوحًا', false);
    }
}
