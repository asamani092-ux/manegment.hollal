<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Support\RecordUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MeetingInvite extends Notification implements ShouldQueue
{
    use Queueable;
    use Concerns\SendsToPreferredChannels;

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
            'message' => 'دعوة لاجتماع: '.$this->meeting->title.' — '.$this->meeting->scheduled_at?->format('Y-m-d H:i'),
            'url' => RecordUrl::meeting($this->meeting->id),
            'meeting_id' => $this->meeting->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('دعوة اجتماع: '.$this->meeting->title)
            ->greeting('مرحبًا'.(isset($notifiable->name) ? ' '.$notifiable->name : ''))
            ->line('تمت دعوتك إلى الاجتماع التالي:')
            ->line('العنوان: '.$this->meeting->title)
            ->line('الوقت: '.($this->meeting->scheduled_at?->format('Y-m-d H:i') ?? '—'));

        if ($this->meeting->location) {
            $mail->line('المكان: '.$this->meeting->location);
        }

        $remote = $this->meeting->link;
        if ($remote) {
            $mail->line('رابط عن بُعد: '.$remote);
        }

        if ($this->meeting->agenda) {
            $mail->line('جدول الأعمال: '.Str::limit($this->meeting->agenda, 200));
        }

        return $mail
            ->action('عرض الاجتماع', RecordUrl::meeting($this->meeting->id))
            ->line('هذه رسالة آلية من منصة حلّل الإدارية.');
    }
}
