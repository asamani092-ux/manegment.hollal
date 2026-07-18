<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Task (إسناد) — assigner edits; assignee is read-only except notes.
 */
class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task) || $user->can('esnad.tasks.view');
    }

    public function update(User $user, Task $task): bool
    {
        if ($this->isAssigneeOnly($user, $task)) {
            return false;
        }

        return $user->id === $task->assigned_by || $user->can('esnad.tasks.update');
    }

    public function delete(User $user, Task $task): bool
    {
        if ($this->isAssigneeOnly($user, $task)) {
            return false;
        }

        return $user->id === $task->assigned_by || $user->can('esnad.tasks.delete');
    }

    public function addNote(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task);
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

    protected function isAssigneeOnly(User $user, Task $task): bool
    {
        return $user->id === $task->assigned_to
            && $user->id !== $task->assigned_by;
    }
}
