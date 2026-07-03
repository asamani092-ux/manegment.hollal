<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates Super Admin role and syncs all Phase 1 permissions.
 * Run after PermissionSeeder.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(PermissionSeeder::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
