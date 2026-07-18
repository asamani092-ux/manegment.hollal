<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('partnerships.contracts.view');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->can('partnerships.contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('partnerships.contracts.create');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->can('partnerships.contracts.manage');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->can('partnerships.contracts.manage');
    }

    public function viewValue(User $user): bool
    {
        return $user->can('finance.expenses.view');
    }

    public function downloadFile(User $user, Contract $contract): bool
    {
        return $this->view($user, $contract);
    }
}
