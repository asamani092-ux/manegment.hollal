<?php

namespace App\Notifications;

use App\Models\Partnership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnershipPortalInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Partnership $partnership,
        public string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->partnership->organization?->name
            ?? $this->partnership->entity_name
            ?? 'الشريك';

        return (new MailMessage)
            ->subject('رابط بوابة الشراكة — '.$name)
            ->greeting('مرحبًا')
            ->line('يمكنكم متابعة الشراكة واختيار البرامج والكميات ومراجعة العرض من خلال بوابة الجهة.')
            ->action('فتح بوابة الجهة', $this->url)
            ->line('هذا الرابط خاص بجهتكم ولا يُشارك مع الآخرين.');
    }
}
