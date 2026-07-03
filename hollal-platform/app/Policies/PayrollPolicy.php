<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

/**
 * Payroll — salaries.view for listing; salaries.manage for CRUD.
 */
class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('salaries.view');
    }

    public function view(User $user, Payroll $payroll): bool
    {
        return $user->can('salaries.view');
    }

    public function create(User $user): bool
    {
        return $user->can('salaries.manage');
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return $user->can('salaries.manage');
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->can('salaries.manage');
    }
}
