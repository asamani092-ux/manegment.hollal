<?php

namespace App\Notifications;

use App\Models\Partnership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PART-4 — notify the partner organization when the pipeline stage moves.
 * Time: O(1) | Space: O(1)
 */
class PartnershipStageChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Partnership $partnership,
        public ?int $fromStage,
        public int $toStage,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $from = Partnership::STAGE_LABELS[$this->fromStage] ?? '—';
        $to = Partnership::STAGE_LABELS[$this->toStage] ?? $this->partnership->stageLabel();
        $name = $this->partnership->organization?->name ?? $this->partnership->entity_name;

        $greeting = $notifiable instanceof AnonymousNotifiable
            ? 'مرحبًا'
            : 'مرحبًا'.(isset($notifiable->name) ? ' '.$notifiable->name : '');

        return (new MailMessage)
            ->subject('تحديث مرحلة الشراكة — '.$name)
            ->greeting($greeting)
            ->line('انتقلت شراكة «'.$name.'» من مرحلة «'.$from.'» إلى «'.$to.'».')
            ->line('هذه رسالة آلية من منصة حلّل الإدارية.');
    }
}
