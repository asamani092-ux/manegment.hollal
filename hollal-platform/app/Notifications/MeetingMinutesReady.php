<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * P2 wave C — sent once, after the meeting ends, with a DIRECT link to the
 * minutes page (unlike MeetingInvite, which deep-links the index modal).
 */
class MeetingMinutesReady extends Notification implements ShouldQueue
{
    use Concerns\SendsToPreferredChannels;
    use Queueable;

    public function __construct(public Meeting $meeting) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'محضر اجتماع «'.$this->meeting->title.'» جاهز للاطلاع والتأكيد',
            'url' => route('meetings.minutes', $this->meeting),
            'meeting_id' => $this->meeting->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('محضر الاجتماع جاهز: '.$this->meeting->title)
            ->greeting('مرحبًا'.(isset($notifiable->name) ? ' '.$notifiable->name : ''))
            ->line('انتهى اجتماع «'.$this->meeting->title.'» وأصبح محضره جاهزًا للاطلاع.')
            ->action('فتح المحضر والتأكيد', route('meetings.minutes', $this->meeting))
            ->line('الرجاء الاطلاع على المحضر وتأكيد استلامكم له.');
    }
}
