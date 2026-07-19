<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskOverdue extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\SendsToPreferredChannels;

    public function __construct(
        public Task $task,
        public bool $forManager = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        if ($this->forManager) {
            $assigneeName = $this->task->assignee?->name ?? 'الموظف';

            return [
                'message' => 'تنبيه: المهمة «'.$this->task->title.'» متأخرة لأكثر من 48 ساعة للموظف '.$assigneeName,
                'url' => route('tasks.index'),
                'task_id' => $this->task->id,
                'escalation' => true,
            ];
        }

        return [
            'message' => 'المهمة «'.$this->task->title.'» متأخرة عن موعد الاستحقاق',
            'url' => route('tasks.index'),
            'task_id' => $this->task->id,
        ];
    }
}
