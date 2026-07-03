<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Task file access — assigner, assignee, or tasks.view permission.
 */
class TaskPolicy
{
    public function downloadFile(User $user, Task $task, string $type): bool
    {
        if (! in_array($type, ['attachment', 'submitted'], true)) {
            return false;
        }

        if ($user->can('tasks.view')) {
            return true;
        }

        return $user->id === $task->assigned_by
            || $user->id === $task->assigned_to;
    }
}
