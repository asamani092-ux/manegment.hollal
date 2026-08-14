<?php

namespace App\Notifications;

use App\Models\Task;
use App\Support\RecordUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder for open / recurring workload — database + mail channels.
 */
class TaskReminder extends Notification implements ShouldQueue
{
    use Queueable;
    use Concerns\SendsToPreferredChannels;

    public function __construct(
        public ?Task $task = null,
        public string $contextMessage = 'تذكير بمهامك المفتوحة في إسناد',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $url = $this->task
            ? RecordUrl::task($this->task->id)
            : route('tasks.index', absolute: false);

        return [
            'message' => $this->contextMessage,
            'url' => $url,
            'task_id' => $this->task?->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->toDatabase($notifiable);
        $url = $payload['url'] ?? null;
        if (is_string($url) && str_starts_with($url, '/')) {
            $url = url($url);
        }

        $mail = (new MailMessage)
            ->subject('منصة حلّل — تذكير مهمة')
            ->greeting('مرحبًا'.(isset($notifiable->name) ? ' '.$notifiable->name : ''))
            ->line($payload['message'] ?? 'تذكير بمهامك المفتوحة في إسناد');

        if (! empty($url)) {
            $mail->action('فتح المهمة', $url);
        }

        return $mail->line('هذه رسالة آلية من منصة حلّل الإدارية.');
    }
}
