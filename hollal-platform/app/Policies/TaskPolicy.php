<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Task (إسناد) — assigner, assignee, or elevated module permission.
 */
class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task) || $user->can('tasks.view');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task) || $user->can('tasks.update');
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task) || $user->can('tasks.delete');
    }

    public function downloadFile(User $user, Task $task, string $type): bool
    {
        if (! in_array($type, ['attachment', 'submitted'], true)) {
            return false;
        }

        return $this->view($user, $task);
    }

    protected function isParticipant(User $user, Task $task): bool
    {
        return $user->id === $task->assigned_by
            || $user->id === $task->assigned_to;
    }
}
