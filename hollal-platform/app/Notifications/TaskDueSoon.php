<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskDueSoon extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\SendsToPreferredChannels;

    public function __construct(public Task $task) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $due = $this->task->due_date?->timezone(config('app.timezone'))->format('Y-m-d H:i');

        return [
            'message' => 'تستحق المهمة «'.$this->task->title.'» خلال يوم واحد'.($due ? ' ('.$due.')' : ''),
            'url' => route('tasks.index'),
            'task_id' => $this->task->id,
        ];
    }
}
