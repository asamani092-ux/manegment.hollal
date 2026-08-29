<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\OpenDecisionsIndex;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\User;
use App\Services\MeetingMinutesPdfService;
use App\Support\PdfArabic;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Meetings tab round 1 — PDF Arabic, agenda decision, grouped open decisions.
 */
class MeetingsTabRound1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_agenda_line_opens_decision_form_prefilled(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['meetings.view', 'meetings.update']);
        $meeting = Meeting::factory()->create([
            'chair_id' => $user->id,
            'agenda' => "البند الأول\nالبند الثاني",
            'scheduled_at' => now()->subHour(),
        ]);

        Livewire::actingAs($user)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('openDecisionFromAgenda', 'البند الأول')
            ->assertSet('showItemModal', true)
            ->assertSet('topic', 'البند الأول')
            ->set('decision', 'اعتماد الخطة')
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('meeting_items', [
            'meeting_id' => $meeting->id,
            'topic' => 'البند الأول',
            'decision' => 'اعتماد الخطة',
        ]);
    }

    public function test_past_meeting_syncs_to_completed_on_minutes_mount(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['meetings.view', 'meetings.update']);
        $meeting = Meeting::factory()->create([
            'chair_id' => $user->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->subDay(),
        ]);

        Livewire::actingAs($user)
            ->test(MeetingMinutes::class, ['meeting' => $meeting]);

        $this->assertSame('completed', $meeting->fresh()->status);
    }

    public function test_open_decisions_grouped_by_meeting_then_drill_down(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['meetings.view', 'meetings.update']);
        $meeting = Meeting::factory()->create(['title' => 'اجتماع التجميع']);
        MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'decision' => 'قرار داخل الاجتماع',
            'status' => 'open',
        ]);

        Livewire::actingAs($user)
            ->test(OpenDecisionsIndex::class)
            ->assertSee('اجتماع التجميع', false)
            ->assertSee('عرض القرارات', false)
            ->assertDontSee('قرار داخل الاجتماع', false)
            ->call('selectMeeting', $meeting->id)
            ->assertSee('قرار داخل الاجتماع', false);
    }

    public function test_minutes_pdf_pipeline_has_ibmplex_and_rtl_direction(): void
    {
        $meeting = Meeting::factory()->create(['title' => 'محضر عربي']);
        $html = app(MeetingMinutesPdfService::class)->buildHtml($meeting);

        $this->assertStringContainsString('direction: rtl', $html);
        $this->assertSame('ibmplex', PdfArabic::defaultFont());
        $bytes = app(MeetingMinutesPdfService::class)->output($meeting);
        $this->assertStringStartsWith('%PDF', $bytes);
    }

    public function test_signed_upload_requires_file_before_submit(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['meetings.view', 'meetings.update']);
        $meeting = Meeting::factory()->create(['chair_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('openSignedUploadModal')
            ->call('uploadSignedMinutes')
            ->assertHasErrors(['signedPdfFile']);

        $pdf = UploadedFile::fake()->create('signed.pdf', 80, 'application/pdf');

        Livewire::actingAs($user)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('openSignedUploadModal')
            ->set('signedPdfFile', $pdf)
            ->call('uploadSignedMinutes')
            ->assertHasNoErrors();

        $this->assertNotNull($meeting->fresh()->signed_document_id);
    }
}
