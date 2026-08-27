<?php

use App\Support\HollalTime;
use Carbon\CarbonInterface;

if (! function_exists('hollal_dt')) {
    /** Display datetime as 12-hour Arabic (ص/م). */
    function hollal_dt(?CarbonInterface $dt, bool $withSeconds = false): string
    {
        return HollalTime::datetime($dt, $withSeconds);
    }
}

if (! function_exists('hollal_time')) {
    function hollal_time(?CarbonInterface $dt): string
    {
        return HollalTime::time($dt);
    }
}

if (! function_exists('hollal_role_labels')) {
    /**
     * Arabic labels for Spatie role names.
     * Time: O(1) | Space: O(1)
     *
     * @return array<string, string>
     */
    function hollal_role_labels(): array
    {
        return [
            'Super Admin' => 'مدير النظام',
            'General Manager' => 'المدير العام',
            'Executive Manager' => 'المدير التنفيذي',
            'Project Manager' => 'مدير مشروع',
            'Finance' => 'المالية',
            'Employee' => 'موظف',
            'Partnerships Manager' => 'مدير الشراكات',
            'HR Manager' => 'مدير الموارد البشرية',
        ];
    }
}

if (! function_exists('hollal_role_label')) {
    function hollal_role_label(?string $name): string
    {
        if ($name === null || $name === '') {
            return '—';
        }

        return hollal_role_labels()[$name] ?? $name;
    }
}
