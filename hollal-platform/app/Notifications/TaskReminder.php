<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskReminder extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\SendsToPreferredChannels;

    public function __construct(
        public ?Task $task,
        public string $message,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'url' => route('tasks.index', $this->task ? ['open' => $this->task->id] : []),
            'task_id' => $this->task?->id,
        ];
    }
}
