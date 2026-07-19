<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingMinutes;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\User;
use App\Services\MeetingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 03-B1 — minutes approval cycle: decision conversion gate, post-approval edit
 * lock, versioned amendments.
 */
class MeetingApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    private function chairUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['esnad.tasks.create']);

        return $user;
    }

    public function test_cannot_convert_decision_before_approval(): void
    {
        $chair = $this->chairUser();
        $responsible = User::factory()->create();
        $meeting = Meeting::factory()->create(['chair_id' => $chair->id, 'approval_status' => 'مسودة']);
        $item = MeetingItem::create([
            'meeting_id' => $meeting->id,
            'topic' => 'قرار',
            'item_kind' => 'قرار',
            'decision' => 'إعداد الخطة',
            'responsible_id' => $responsible->id,
            'status' => 'open',
        ]);

        Livewire::actingAs($chair)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('convertToTask', $item->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertDatabaseMissing('tasks', ['meeting_id' => $meeting->id]);
    }

    public function test_can_convert_decision_after_approval(): void
    {
        $chair = $this->chairUser();
        $responsible = User::factory()->create();
        $meeting = Meeting::factory()->create(['chair_id' => $chair->id, 'approval_status' => 'معتمد']);
        $item = MeetingItem::create([
            'meeting_id' => $meeting->id,
            'topic' => 'قرار',
            'item_kind' => 'قرار',
            'decision' => 'إعداد الخطة',
            'responsible_id' => $responsible->id,
            'status' => 'open',
        ]);

        Livewire::actingAs($chair)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('convertToTask', $item->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertDatabaseHas('tasks', ['meeting_id' => $meeting->id, 'assigned_to' => $responsible->id]);
    }

    public function test_post_approval_item_edit_is_blocked(): void
    {
        $chair = $this->chairUser();
        $meeting = Meeting::factory()->create(['chair_id' => $chair->id, 'approval_status' => 'معتمد']);

        Livewire::actingAs($chair)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->set('topic', 'بند جديد')
            ->call('saveItem')
            ->assertDispatched('toast', type: 'error');

        $this->assertDatabaseMissing('meeting_items', ['topic' => 'بند جديد']);
    }

    public function test_approval_then_amendment_creates_new_version(): void
    {
        $chair = User::factory()->create();
        $meeting = Meeting::factory()->create(['approval_status' => 'مسودة', 'version' => 1]);
        $service = app(MeetingService::class);

        $service->approveMinutes($meeting, $chair);
        $this->assertTrue($meeting->fresh()->isApproved());

        $amendment = $service->amend($meeting, $chair, 'تصحيح تاريخ القرار');

        $this->assertSame(2, $meeting->fresh()->version);
        $this->assertSame(2, $amendment->version);
        $this->assertDatabaseHas('meeting_amendments', ['meeting_id' => $meeting->id, 'version' => 2]);
    }

    public function test_cannot_amend_unapproved_meeting(): void
    {
        $chair = User::factory()->create();
        $meeting = Meeting::factory()->create(['approval_status' => 'مسودة']);

        $this->expectException(\RuntimeException::class);
        app(MeetingService::class)->amend($meeting, $chair, 'تعديل');
    }
}
