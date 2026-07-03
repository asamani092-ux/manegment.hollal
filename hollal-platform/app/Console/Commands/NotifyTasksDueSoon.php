<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueSoon;
use App\Support\TaskNotificationHelper;
use Illuminate\Console\Command;

class NotifyTasksDueSoon extends Command
{
    protected $signature = 'tasks:notify-due-soon';

    protected $description = 'Notify assignees when a task is due within one day';

    public function handle(): int
    {
        $windowStart = now()->addDay()->startOfDay();
        $windowEnd = now()->addDay()->endOfDay();

        $tasks = Task::query()
            ->select(['id', 'title', 'assigned_to', 'due_date', 'status'])
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['completed'])
            ->whereBetween('due_date', [$windowStart, $windowEnd])
            ->with('assignee:id')
            ->get();

        $sent = 0;

        foreach ($tasks as $task) {
            $assignee = $task->assignee;

            if (! $assignee) {
                continue;
            }

            if (TaskNotificationHelper::alreadyNotified($assignee, TaskDueSoon::class, $task->id)) {
                continue;
            }

            $assignee->notify(new TaskDueSoon($task));
            $sent++;
        }

        $this->info("Sent {$sent} due-soon notification(s).");

        return self::SUCCESS;
    }
}
