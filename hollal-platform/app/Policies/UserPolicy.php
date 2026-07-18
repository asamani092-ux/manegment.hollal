<?php

namespace App\Policies;

use App\Models\User;

/**
 * User — HR module permissions; self-view always allowed.
 */
class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->can('hr.employees.view');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('hr.employees.update');
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('hr.employees.delete') && $actor->id !== $target->id;
    }
}
