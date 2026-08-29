<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\MeetingsArchiveIndex;
use App\Models\DocumentVersion;
use App\Models\Meeting;
use App\Models\MeetingAmendment;
use App\Models\MeetingItem;
use App\Models\User;
use App\Services\MeetingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Minutes amendment: request → approve → edit items → finalize.
 * Time of suite: O(tests) | Space: O(1) fixtures
 */
class MinutesAmendmentFourStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_four_step_flow_edits_item_and_keeps_original_version_path(): void
    {
        $requester = User::factory()->create(['must_change_password' => false]);
        $approver = User::factory()->create(['must_change_password' => false]);
        foreach ([$requester, $approver] as $user) {
            $user->givePermissionTo(['meetings.view', 'meetings.update', 'documents.view']);
        }

        $meeting = Meeting::factory()->create([
            'chair_id' => $approver->id,
            'secretary_id' => $requester->id,
            'approval_status' => Meeting::APPROVAL_DRAFT,
            'version' => 1,
            'title' => 'اجتماع مسار التعديل',
        ]);

        $item = MeetingItem::create([
            'meeting_id' => $meeting->id,
            'topic' => 'بند الميزانية',
            'decision' => 'اعتماد ١٠٠ ألف',
            'status' => 'open',
        ]);

        $service = app(MeetingService::class);
        $service->approveMinutes($meeting, $approver);
        $meeting->refresh();

        $docId = (int) $meeting->archived_document_id;
        $originalPath = DocumentVersion::query()
            ->where('document_id', $docId)
            ->where('version', 1)
            ->value('path');
        $this->assertNotNull($originalPath);

        $service->requestAmendment($meeting, $requester, 'تصحيح رقم الاعتماد');

        $pending = MeetingAmendment::query()->where('meeting_id', $meeting->id)->firstOrFail();

        try {
            $service->approveAmendment($pending, $requester);
            $this->fail('كان يجب رفض الموافقة الذاتية');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('لا يمكن لمقدّم الطلب', $e->getMessage());
        }

        $service->approveAmendment($pending, $approver);
        $this->assertSame(MeetingAmendment::STATUS_EDITING, $pending->fresh()->status);
        $this->assertSame(1, (int) $meeting->fresh()->version);

        Livewire::actingAs($requester)
            ->test(MeetingMinutes::class, ['meeting' => $meeting->fresh()])
            ->assertSee('تعديل مفتوح')
            ->call('openItemEdit', $item->id)
            ->set('decision', 'اعتماد ١٢٠ ألف')
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame('اعتماد ١٢٠ ألف', $item->fresh()->decision);

        Livewire::actingAs($approver)
            ->test(MeetingMinutes::class, ['meeting' => $meeting->fresh()])
            ->call('finalizeAmendment')
            ->assertHasNoErrors();

        $meeting->refresh();
        $this->assertSame(2, (int) $meeting->version);
        $this->assertSame(MeetingAmendment::STATUS_APPROVED, $pending->fresh()->status);
        $this->assertFalse($meeting->allowsItemEdit());
        $this->assertSame(
            $originalPath,
            DocumentVersion::query()->where('document_id', $docId)->where('version', 1)->value('path')
        );
        $this->assertSame(2, DocumentVersion::query()->where('document_id', $docId)->count());
    }

    public function test_archive_ui_finalize_button_after_editing_status(): void
    {
        $requester = User::factory()->create(['must_change_password' => false]);
        $approver = User::factory()->create(['must_change_password' => false]);
        foreach ([$requester, $approver] as $user) {
            $user->givePermissionTo(['meetings.view', 'meetings.update', 'documents.view']);
        }

        $meeting = Meeting::factory()->create([
            'chair_id' => $approver->id,
            'approval_status' => Meeting::APPROVAL_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'version' => 1,
        ]);

        $amendment = MeetingAmendment::create([
            'meeting_id' => $meeting->id,
            'version' => 2,
            'note' => 'استدراك',
            'status' => MeetingAmendment::STATUS_EDITING,
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($approver)
            ->test(MeetingsArchiveIndex::class)
            ->assertSee('اعتماد التغيير')
            ->call('finalizeAmendment', $amendment->id);

        $this->assertSame(MeetingAmendment::STATUS_APPROVED, $amendment->fresh()->status);
        $this->assertSame(2, (int) $meeting->fresh()->version);
    }
}
