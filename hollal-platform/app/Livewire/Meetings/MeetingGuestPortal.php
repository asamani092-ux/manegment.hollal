<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use App\Models\MeetingGuest;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * P2 wave C — the external guest short link: view the meeting/minutes and
 * confirm/sign, without any employee account. The token is the only
 * identity; every read/write is scoped to it.
 */
class MeetingGuestPortal extends Component
{
    use WithFileUploads;

    public MeetingGuest $guest;

    public $signatureFile = null;

    public function mount(string $token): void
    {
        $guest = MeetingGuest::where('token', $token)->first();

        abort_if($guest === null, 404);

        $this->guest = $guest;

        if ($this->guest->viewed_at === null) {
            $this->guest->forceFill(['viewed_at' => now()])->save();
        }
    }

    public function confirm(): void
    {
        if ($this->guest->confirmed_at !== null) {
            return;
        }

        if ($this->signatureFile) {
            $this->validate(['signatureFile' => 'image|max:2048'], [], ['signatureFile' => 'صورة التوقيع']);
            $this->guest->signature_image_path = $this->signatureFile->store('signatures/guests', 'local');
        }

        $this->guest->confirmed_at = now();
        $this->guest->save();

        $this->signatureFile = null;
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل تأكيدكم، شكرًا لكم');
    }

    public function render(): View
    {
        $meeting = Meeting::query()
            ->select(['id', 'title', 'scheduled_at', 'agenda', 'location', 'link', 'chair_id', 'approval_status'])
            ->with([
                'chair:id,name',
                'items' => fn ($q) => $q->select(['id', 'meeting_id', 'topic', 'decision'])->orderBy('id'),
            ])
            ->findOrFail($this->guest->meeting_id);

        return view('livewire.meetings.meeting-guest-portal', [
            'meeting' => $meeting,
        ])->layout('layouts.guest', ['title' => 'دعوة اجتماع']);
    }
}
