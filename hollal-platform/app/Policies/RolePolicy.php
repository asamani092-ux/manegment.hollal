<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Role — settings module permissions (global admin scope).
 */
class RolePolicy
{
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete');
    }
}
