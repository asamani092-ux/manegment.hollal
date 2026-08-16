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
        $dueLabel = $this->task->due_date ? ' ('.hollal_dt($this->task->due_date).')' : '';

        return [
            'message' => 'تستحق المهمة «'.$this->task->title.'» خلال يوم واحد'.$dueLabel,
            'url' => \App\Support\RecordUrl::task($this->task->id),
            'task_id' => $this->task->id,
        ];
    }
}
