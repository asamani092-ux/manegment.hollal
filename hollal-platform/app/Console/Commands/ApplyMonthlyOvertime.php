<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Console\Command;

/**
 * 01-B4 — on the 1st of each month, apply hr.overtime_monthly_days to every
 * attendance-enabled employee.
 */
class ApplyMonthlyOvertime extends Command
{
    protected $signature = 'attendance:apply-monthly-overtime';

    protected $description = 'Apply the monthly overtime days to attendance-enabled employees';

    public function handle(): int
    {
        $days = (int) Setting::get('hr.overtime_monthly_days', 0);

        $enabledUserIds = User::query()->where('attendance_enabled', true)->pluck('id');

        $updated = EmployeeProfile::query()
            ->whereIn('user_id', $enabledUserIds)
            ->update(['overtime_days_this_month' => $days]);

        $this->info("Applied {$days} overtime day(s) to {$updated} employee(s).");

        return self::SUCCESS;
    }
}
