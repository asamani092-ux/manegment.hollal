<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingGuestPortal;
use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\MeetingsIndex;
use App\Models\Committee;
use App\Models\Meeting;
use App\Models\MeetingGuest;
use App\Models\User;
use App\Notifications\MeetingGuestInvite;
use App\Notifications\MeetingInvite;
use App\Notifications\MeetingMinutesReady;
use App\Services\MeetingService;
use App\Support\RecordUrl;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase-2 wave C — meeting creation pickers, pre/post-meeting confirm flow,
 * profile signature stamping, external guest portal, and the manually
 * signed-PDF archive path.
 */
class MeetingWaveCTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create(['must_change_password' => false]);
        $this->admin->givePermissionTo(['meetings.view', 'meetings.create', 'meetings.update', 'meetings.delete']);
    }

    public function test_picking_an_employee_adds_and_resets_the_picker(): void
    {
        $employee = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(MeetingsIndex::class)
            ->call('openCreate')
            ->set('pickEmployeeId', $employee->id)
            ->assertSet('attendeeIds', [$employee->id])
            ->assertSet('pickEmployeeId', null);
    }

    public function test_choosing_a_committee_bulk_adds_members_and_allows_individual_removal(): void
    {
        $chair = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $committee = Committee::create(['name' => 'لجنة المشتريات', 'is_active' => true, 'chair_id' => $chair->id]);
        $committee->members()->attach([$member1->id, $member2->id]);

        Livewire::actingAs($this->admin)
            ->test(MeetingsIndex::class)
            ->call('openCreate')
            ->set('pickCommitteeId', $committee->id)
            ->assertSet('attendeeIds', fn ($ids) => count($ids) === 2 && in_array($member1->id, $ids) && in_array($member2->id, $ids))
            ->call('removeAttendee', $member1->id)
            ->assertSet('attendeeIds', [$member2->id]);
    }

    public function test_creating_meeting_with_external_guest_persists_and_invites_guest(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin)
            ->test(MeetingsIndex::class)
            ->call('openCreate')
            ->set('title', 'اجتماع الشركاء')
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('addGuestRow')
            ->set('guestRows.0.name', 'ضيف خارجي')
            ->set('guestRows.0.email', 'guest@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $meeting = Meeting::where('title', 'اجتماع الشركاء')->firstOrFail();

        $this->assertDatabaseHas('meeting_guests', [
            'meeting_id' => $meeting->id,
            'name' => 'ضيف خارجي',
            'email' => 'guest@example.com',
        ]);

        Notification::assertSentOnDemand(MeetingGuestInvite::class);
    }

    public function test_invite_notification_carries_calendar_link_not_direct_minutes_url(): void
    {
        Notification::fake();

        $attendee = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(MeetingsIndex::class)
            ->call('openCreate')
            ->set('title', 'اجتماع الفريق')
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('attendeeIds', [$attendee->id])
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($attendee, MeetingInvite::class, function ($notification) {
            $meeting = Meeting::where('title', 'اجتماع الفريق')->firstOrFail();
            $payload = $notification->toDatabase($this->admin);

            return $payload['url'] === RecordUrl::meeting($meeting->id)
                && ! str_contains($payload['url'], '/minutes');
        });
    }

    public function test_confirm_button_blocked_before_meeting_ends(): void
    {
        $meeting = Meeting::factory()->create(['scheduled_at' => now()->addDay()]);
        $meeting->attendees()->attach($this->admin->id);

        Livewire::actingAs($this->admin)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('confirmMyAttendance')
            ->assertDispatched('toast', type: 'error');

        $pivot = $meeting->attendees()->where('users.id', $this->admin->id)->first()->pivot;
        $this->assertNull($pivot->confirmed_at);
    }

    public function test_first_confirm_without_saved_signature_prompts_upload_then_stamps(): void
    {
        $meeting = Meeting::factory()->create(['scheduled_at' => now()->subHour()]);
        $meeting->attendees()->attach($this->admin->id);

        $component = Livewire::actingAs($this->admin)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('confirmMyAttendance')
            ->assertSet('showSignatureModal', true);

        $file = UploadedFile::fake()->create('signature.png', 10, 'image/png');

        $component->set('signatureFile', $file)
            ->call('saveSignatureAndConfirm')
            ->assertSet('showSignatureModal', false);

        $this->admin->refresh();
        $this->assertNotNull($this->admin->signature_image_path);
        Storage::disk('local')->assertExists($this->admin->signature_image_path);

        $pivot = $meeting->attendees()->where('users.id', $this->admin->id)->first()->pivot;
        $this->assertNotNull($pivot->confirmed_at);
        $this->assertSame($this->admin->signature_image_path, $pivot->signature_image_path);
    }

    public function test_confirm_with_existing_saved_signature_stamps_directly_without_modal(): void
    {
        $this->admin->forceFill(['signature_image_path' => 'signatures/existing.png'])->save();
        Storage::disk('local')->put('signatures/existing.png', 'fake-image-bytes');

        $meeting = Meeting::factory()->create(['scheduled_at' => now()->subHour()]);
        $meeting->attendees()->attach($this->admin->id);

        Livewire::actingAs($this->admin)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('confirmMyAttendance')
            ->assertSet('showSignatureModal', false)
            ->assertDispatched('toast', type: 'success');

        $pivot = $meeting->attendees()->where('users.id', $this->admin->id)->first()->pivot;
        $this->assertSame('signatures/existing.png', $pivot->signature_image_path);
    }

    public function test_attendee_without_meetings_view_permission_can_open_minutes(): void
    {
        $attendee = User::factory()->create(['must_change_password' => false]);
        $meeting = Meeting::factory()->create();
        $meeting->attendees()->attach($attendee->id);

        $this->actingAs($attendee)
            ->get(route('meetings.minutes', $meeting))
            ->assertOk();
    }

    public function test_outsider_still_forbidden_from_minutes_without_permission_or_attendance(): void
    {
        $outsider = User::factory()->create(['must_change_password' => false]);
        $meeting = Meeting::factory()->create();

        $this->actingAs($outsider)
            ->get(route('meetings.minutes', $meeting))
            ->assertForbidden();
    }

    public function test_minutes_ready_notification_sent_once_after_meeting_ends(): void
    {
        Notification::fake();

        $attendee = User::factory()->create();
        $meeting = Meeting::factory()->create(['scheduled_at' => now()->subHour()]);
        $meeting->attendees()->attach($attendee->id);

        app(MeetingService::class)->notifyMinutesReadyIfDue($meeting);
        Notification::assertSentTo($attendee, MeetingMinutesReady::class);

        Notification::fake();
        app(MeetingService::class)->notifyMinutesReadyIfDue($meeting->fresh());
        Notification::assertNothingSent();
    }

    public function test_minutes_ready_notification_not_sent_before_meeting_ends(): void
    {
        Notification::fake();

        $attendee = User::factory()->create();
        $meeting = Meeting::factory()->create(['scheduled_at' => now()->addDay()]);
        $meeting->attendees()->attach($attendee->id);

        app(MeetingService::class)->notifyMinutesReadyIfDue($meeting);
        Notification::assertNothingSent();
    }

    public function test_guest_portal_view_marks_viewed_and_confirm_stamps_signature(): void
    {
        $meeting = Meeting::factory()->create();
        $guest = MeetingGuest::create([
            'meeting_id' => $meeting->id,
            'name' => 'ضيف',
            'email' => 'g@example.com',
            'token' => 'test-token-123',
        ]);

        Livewire::test(MeetingGuestPortal::class, ['token' => $guest->token])
            ->assertOk();

        $this->assertNotNull($guest->fresh()->viewed_at);

        $file = UploadedFile::fake()->create('sig.png', 10, 'image/png');

        Livewire::test(MeetingGuestPortal::class, ['token' => $guest->token])
            ->set('signatureFile', $file)
            ->call('confirm');

        $guest->refresh();
        $this->assertNotNull($guest->confirmed_at);
        $this->assertNotNull($guest->signature_image_path);
        Storage::disk('local')->assertExists($guest->signature_image_path);
    }

    public function test_guest_portal_rejects_unknown_token(): void
    {
        Livewire::test(MeetingGuestPortal::class, ['token' => 'does-not-exist'])
            ->assertStatus(404);
    }

    public function test_chair_can_upload_signed_pdf_and_link_it_to_archive(): void
    {
        $meeting = Meeting::factory()->create(['chair_id' => $this->admin->id]);
        $pdf = UploadedFile::fake()->create('signed-minutes.pdf', 100, 'application/pdf');

        Livewire::actingAs($this->admin)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->call('openSignedUploadModal')
            ->set('signedPdfFile', $pdf)
            ->call('uploadSignedMinutes')
            ->assertSet('showSignedUploadModal', false);

        $meeting->refresh();
        $this->assertNotNull($meeting->signed_document_id);

        $document = $meeting->signedDocument;
        $this->assertNotNull($document);
        $this->assertFalse($document->is_auto_archived);
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($this->admin)
            ->get(route('meetings.minutes.signed', $meeting))
            ->assertOk()
            ->assertHeader('Content-Disposition', \App\Support\DownloadHeaders::contentDisposition('minutes-signed-'.$meeting->id.'.pdf', 'attachment'));

        $this->actingAs($this->admin)
            ->get(route('meetings.minutes.signed', ['meeting' => $meeting, 'inline' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', \App\Support\DownloadHeaders::contentDisposition('minutes-signed-'.$meeting->id.'.pdf', 'inline'));
    }
}
