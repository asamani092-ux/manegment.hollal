<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'dashboard.view',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'departments.view',
        'departments.create',
        'departments.update',
        'departments.delete',
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'partnerships.view',
        'partnerships.create',
        'partnerships.update',
        'partnerships.delete',
        'tasks.view',
        'tasks.create',
        'tasks.update',
        'tasks.delete',
        'meetings.view',
        'meetings.create',
        'meetings.update',
        'meetings.delete',
        'expenses.view',
        'expenses.create',
        'expenses.approve',
        'expenses.pay',
        'salaries.view',
        'salaries.manage',
        'documents.view',
        'documents.create',
        'contracts.view',
        'contracts.create',
        'contracts.manage',
        'reports.view',
        'settings.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
