<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            PlatformSettingsSeeder::class,
            PlanTemplateSeeder::class,
            ChartOfAccountsSeeder::class,
            ApprovalRulesSeeder::class,
            AdminUserSeeder::class,
        ]);

        Artisan::call('hr:seed-reference');

        $this->call([
            DemoTrialSeeder::class,
        ]);

        app(\App\Services\Approval\ApprovalChainDeriver::class)->syncAll();
    }
}
