<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\MeetingGuest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * P2 wave C — external guest invite (no employee account): mail-only, carries
 * the guest's unique tokenized short link.
 */
class MeetingGuestInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Meeting $meeting,
        public MeetingGuest $guest,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('meetings.guest.portal', $this->guest->token);

        $mail = (new MailMessage)
            ->subject('دعوة اجتماع: '.$this->meeting->title)
            ->greeting('مرحبًا '.$this->guest->name)
            ->line('تمت دعوتكم كضيف إلى الاجتماع التالي:')
            ->line('العنوان: '.$this->meeting->title)
            ->line('الوقت: '.hollal_dt($this->meeting->scheduled_at));

        if ($this->meeting->location) {
            $mail->line('المكان: '.$this->meeting->location);
        }

        if ($this->meeting->link) {
            $mail->line('رابط عن بُعد: '.$this->meeting->link);
        }

        return $mail
            ->action('عرض تفاصيل الاجتماع', $url)
            ->line('هذا الرابط خاص بكم، الرجاء عدم مشاركته مع أحد.');
    }
}
