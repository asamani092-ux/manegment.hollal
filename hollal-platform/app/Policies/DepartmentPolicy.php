<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

/**
 * Department — module permissions (global admin scope).
 */
class DepartmentPolicy
{
    public function view(User $user, Department $department): bool
    {
        return $user->can('departments.view');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('departments.update');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('departments.delete');
    }
}
