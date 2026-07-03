<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

class ContractNotificationHelper
{
    /**
     * HR recipients for contract expiry alerts:
     * 1. Users with the "HR" Spatie role
     * 2. Users with contracts.manage or users.view (HR oversight permissions)
     * Departments have no head_id in schema, so department heads are not used.
     * Time: O(u) users | Space: O(u).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function hrManagers()
    {
        $byRole = Role::where('name', 'HR')->where('guard_name', 'web')->exists()
            ? User::role('HR')->get(['id', 'name', 'email'])
            : collect();

        $byPermission = User::permission(['contracts.manage', 'users.view'])
            ->get(['id', 'name', 'email']);

        return $byRole->merge($byPermission)->unique('id')->values();
    }

    public static function alreadyNotified(User $user, string $notificationClass, int $contractId, int $daysRemaining): bool
    {
        return $user->notifications()
            ->where('type', $notificationClass)
            ->where('data->contract_id', $contractId)
            ->where('data->days_remaining', $daysRemaining)
            ->exists();
    }
}
