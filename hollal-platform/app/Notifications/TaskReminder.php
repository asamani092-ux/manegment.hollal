<?php

namespace App\Notifications;

use App\Models\Task;
use App\Support\RecordUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Simple reminder for open / recurring workload follow-up.
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
            : route('tasks.index');

        return [
            'message' => $this->contextMessage,
            'url' => $url,
            'task_id' => $this->task?->id,
        ];
    }
}
