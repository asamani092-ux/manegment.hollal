<?php

namespace App\Notifications\Concerns;

use App\Models\NotificationPreference;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 00-B3 — shared channel resolution + queued mail rendering for critical
 * notifications. Adds an in-app database record plus a queued mail message,
 * honouring per-user notification preferences (groundwork) when present.
 */
trait SendsToPreferredChannels
{
    /**
     * Channels this notification is delivered on for the given notifiable.
     *
     * @return list<string>
     */
    public function preferredChannels(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->mailEnabledFor($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Queued mail rendering reused from the in-app payload.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payload = method_exists($this, 'toDatabase')
            ? $this->toDatabase($notifiable)
            : $this->toArray($notifiable);

        $message = $payload['message'] ?? 'لديك إشعار جديد في منصة حلّل الإدارية.';

        $mail = (new MailMessage)
            ->subject('منصة حلّل الإدارية — إشعار')
            ->greeting('مرحبًا'.(isset($notifiable->name) ? ' '.$notifiable->name : ''))
            ->line($message);

        if (! empty($payload['url'])) {
            $mail->action('عرض التفاصيل', $payload['url']);
        }

        return $mail->line('هذه رسالة آلية من منصة حلّل الإدارية.');
    }

    private function mailEnabledFor(object $notifiable): bool
    {
        if (empty($notifiable->email)) {
            return false;
        }

        if (! isset($notifiable->id)) {
            return true;
        }

        $preference = NotificationPreference::query()
            ->where('user_id', $notifiable->id)
            ->where('channel', 'mail')
            ->where('event_type', class_basename(static::class))
            ->first();

        return $preference?->enabled ?? true;
    }
}
