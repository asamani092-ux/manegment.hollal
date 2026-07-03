<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\MeetingsIndex;
use App\Livewire\Meetings\OpenDecisionsIndex;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->admin = User::factory()->create([
            'phone' => '0503333333',
            'must_change_password' => false,
        ]);
        $this->admin->givePermissionTo([
            'meetings.view',
            'meetings.create',
            'meetings.update',
            'meetings.delete',
            'tasks.create',
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function meetingRoutesProvider(): array
    {
        return [
            'meetings index' => [route('meetings.index')],
            'open decisions' => [route('meetings.open-decisions')],
        ];
    }

    #[DataProvider('meetingRoutesProvider')]
    public function test_guest_is_redirected_from_meeting_route(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('meetingRoutesProvider')]
    public function test_user_without_permission_receives_forbidden(string $url): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get($url)->assertForbidden();
    }

    #[DataProvider('meetingRoutesProvider')]
    public function test_authorized_user_can_access_meeting_route(string $url): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_guest_is_redirected_from_meeting_minutes(): void
    {
        $meeting = Meeting::factory()->create();

        $this->get(route('meetings.minutes', $meeting))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_receives_forbidden_on_minutes(): void
    {
        $meeting = Meeting::factory()->create();
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get(route('meetings.minutes', $meeting))->assertForbidden();
    }

    public function test_authorized_user_can_access_meeting_minutes(): void
    {
        $meeting = Meeting::factory()->create();

        $this->actingAs($this->admin)->get(route('meetings.minutes', $meeting))->assertOk();
    }

    public function test_admin_can_create_meeting_via_livewire(): void
    {
        $chair = User::factory()->create(['phone' => '0504444444']);

        Livewire::actingAs($this->admin)
            ->test(MeetingsIndex::class)
            ->call('openCreate')
            ->set('title', 'اجتماع التخطيط')
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('location', 'قاعة A')
            ->set('chair_id', $chair->id)
            ->set('status', 'scheduled')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('meetings', [
            'title' => 'اجتماع التخطيط',
        ]);
    }

    public function test_decision_can_be_converted_to_task(): void
    {
        $responsible = User::factory()->create(['phone' => '0505555555']);
        $meeting = Meeting::factory()->create(['chair_id' => $this->admin->id]);
        $item = MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'topic' => 'مناقشة الميزانية',
            'decision' => 'اعتماد الميزانية',
            'discussion_summary' => 'تمت الموافقة',
            'responsible_id' => $responsible->id,
            'due_date' => now()->addWeek(),
            'status' => 'open',
        ]);

        Livewire::actingAs($this->admin)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('convertToTask', $item->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'اعتماد الميزانية',
            'assigned_to' => $responsible->id,
            'assigned_by' => $this->admin->id,
            'meeting_id' => $meeting->id,
        ]);

        $item->refresh();
        $this->assertNotNull($item->task_id);
        $this->assertSame('in_progress', $item->status);
    }

    public function test_open_decisions_lists_unexecuted_items(): void
    {
        $meeting = Meeting::factory()->create();
        MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'decision' => 'قرار مفتوح',
            'status' => 'open',
        ]);
        MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'decision' => 'قرار منجز',
            'status' => 'done',
        ]);

        Livewire::actingAs($this->admin)
            ->test(OpenDecisionsIndex::class)
            ->assertSee('قرار مفتوح')
            ->assertDontSee('قرار منجز');
    }
}
