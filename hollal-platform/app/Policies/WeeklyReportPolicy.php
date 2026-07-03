<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyReport;

class WeeklyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function view(User $user, WeeklyReport $weeklyReport): bool
    {
        return $user->can('reports.view');
    }
}
