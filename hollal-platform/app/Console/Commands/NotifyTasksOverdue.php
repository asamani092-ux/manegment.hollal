<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskOverdue;
use App\Support\TaskNotificationHelper;
use Illuminate\Console\Command;

class NotifyTasksOverdue extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'Notify assignees of overdue tasks and escalate to managers after 48 hours';

    public function handle(): int
    {
        $assigneeSent = 0;
        $managerSent = 0;

        $overdueTasks = Task::query()
            ->select(['id', 'title', 'assigned_to', 'due_date', 'status'])
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed'])
            ->with(['assignee:id,name,manager_id', 'assignee.manager:id'])
            ->get();

        foreach ($overdueTasks as $task) {
            $assignee = $task->assignee;

            if (! $assignee) {
                continue;
            }

            if (! TaskNotificationHelper::alreadyNotified($assignee, TaskOverdue::class, $task->id, false)) {
                $assignee->notify(new TaskOverdue($task));
                $assigneeSent++;
            }

            if ($task->due_date->lte(now()->subHours(48))) {
                $manager = $assignee->manager;

                if ($manager instanceof User
                    && ! TaskNotificationHelper::alreadyNotified($manager, TaskOverdue::class, $task->id, true)) {
                    $manager->notify(new TaskOverdue($task, forManager: true));
                    $managerSent++;
                }
            }
        }

        $this->info("Sent {$assigneeSent} overdue and {$managerSent} escalation notification(s).");

        return self::SUCCESS;
    }
}
