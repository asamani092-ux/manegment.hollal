<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * GEN-7 — reset link delivered via MAIL_MAILER (log in trial).
 */
class PasswordResetLink extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $identifier = $notifiable->email ?: $notifiable->phone;
        $url = url(route('password.reset', [
            'token' => $this->token,
            'identifier' => $identifier,
        ], false));

        return (new MailMessage)
            ->subject('منصة حلّل — تعيين كلمة مرور جديدة')
            ->greeting('مرحبًا'.(isset($notifiable->name) ? ' '.$notifiable->name : ''))
            ->line('وصلك هذا الرابط لأن طلب استعادة كلمة المرور سُجّل لحسابك.')
            ->action('تعيين كلمة مرور جديدة', $url)
            ->line('إن لم تطلب ذلك فتجاهل الرسالة.');
    }
}
