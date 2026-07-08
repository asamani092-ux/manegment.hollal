<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates Super Admin and operational roles with explicit least-privilege permissions.
 * Run after PermissionSeeder.
 */
class RoleSeeder extends Seeder
{
    /** @var list<string> */
    private const GENERAL_MANAGER_PERMISSIONS = [
        'dashboard.view',
        'users.view',
        'roles.view',
        'departments.view',
        'projects.view',
        'partnerships.view',
        'tasks.view',
        'meetings.view',
        'expenses.view',
        'expenses.approve',
        'expenses.pay',
        'salaries.view',
        'documents.view',
        'contracts.view',
        'contracts.manage',
        'reports.view',
    ];

    /** @var list<string> */
    private const EXECUTIVE_MANAGER_PERMISSIONS = [
        'dashboard.view',
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'tasks.view',
        'tasks.create',
        'tasks.update',
        'tasks.delete',
        'meetings.view',
        'meetings.create',
        'meetings.update',
        'meetings.delete',
        'users.view',
        'expenses.view',
        'expenses.approve',
        'reports.view',
        'documents.view',
        'documents.create',
    ];

    /** @var list<string> */
    private const PROJECT_MANAGER_PERMISSIONS = [
        'dashboard.view',
        'projects.view',
        'projects.update',
        'tasks.view',
        'tasks.create',
        'tasks.update',
        'tasks.delete',
        'meetings.view',
        'meetings.create',
        'documents.view',
        'documents.create',
        'expenses.view',
        'expenses.create',
    ];

    /** @var list<string> */
    private const FINANCE_PERMISSIONS = [
        'dashboard.view',
        'expenses.view',
        'expenses.create',
        'expenses.approve',
        'expenses.pay',
        'salaries.view',
        'salaries.manage',
        'contracts.view',
        'reports.view',
    ];

    /** @var list<string> */
    private const EMPLOYEE_PERMISSIONS = [
        'dashboard.view',
        'tasks.view',
        'tasks.create',
        'meetings.view',
        'documents.view',
        'expenses.view',
        'expenses.create',
        'projects.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(PermissionSeeder::PERMISSIONS);

        $this->syncRole('General Manager', self::GENERAL_MANAGER_PERMISSIONS);
        $this->syncRole('Executive Manager', self::EXECUTIVE_MANAGER_PERMISSIONS);
        $this->syncRole('Project Manager', self::PROJECT_MANAGER_PERMISSIONS);
        $this->syncRole('Finance', self::FINANCE_PERMISSIONS);
        $this->syncRole('Employee', self::EMPLOYEE_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncRole(string $name, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
    }
}
