<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

class WeeklyReportNotificationHelper
{
    /**
     * Managers to notify when a weekly report is generated:
     * users with direct reports (manager_id) OR users with the "Manager" role.
     * Time: O(u) | Space: O(u).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function managers()
    {
        $withReports = User::whereHas('subordinates')->get(['id', 'name', 'email']);

        $byRole = Role::where('name', 'Manager')->where('guard_name', 'web')->exists()
            ? User::role('Manager')->get(['id', 'name', 'email'])
            : collect();

        return $withReports->merge($byRole)->unique('id')->values();
    }
}
