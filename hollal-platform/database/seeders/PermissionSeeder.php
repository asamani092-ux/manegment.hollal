<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /** @var list<string> hierarchical tab.section.action names (00-B2) */
    public const PERMISSIONS = [
        'dashboard.view',
        'hr.employees.view',
        'hr.employees.create',
        'hr.employees.update',
        'hr.employees.delete',
        'hr.salaries.view',
        'hr.salaries.manage',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'structure.departments.view',
        'structure.departments.create',
        'structure.departments.update',
        'structure.departments.delete',
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'projects.programs.view',
        'projects.programs.manage',
        'projects.templates.manage',
        'projects.generate',
        'projects.visits.view',
        'projects.visits.manage',
        'projects.measurement.view',
        'projects.measurement.manage',
        'projects.close',
        'partnerships.view',
        'partnerships.create',
        'partnerships.update',
        'partnerships.delete',
        'partnerships.contracts.view',
        'partnerships.contracts.create',
        'partnerships.contracts.manage',
        'partnerships.contracts.confirm',
        'partnerships.organizations.view',
        'partnerships.organizations.manage',
        'partnerships.pipeline.view',
        'partnerships.pipeline.manage',
        'partnerships.quotes.view',
        'partnerships.quotes.create',
        'partnerships.quotes.approve',
        'partnerships.payments.view',
        'partnerships.payments.record',
        'partnerships.payments.confirm',
        'partnerships.links.manage',
        'partnerships.company-profile.manage',
        'partnerships.generate',
        'esnad.tasks.view',
        'esnad.tasks.team.view',
        'esnad.tasks.create',
        'esnad.tasks.update',
        'esnad.tasks.delete',
        'meetings.view',
        'meetings.create',
        'meetings.update',
        'meetings.delete',
        'finance.expenses.view',
        'finance.expenses.create',
        'finance.expenses.approve',
        'finance.expenses.pay',
        'finance.payroll.view',
        'finance.payroll.approve',
        'finance.custodies.view',
        'finance.custodies.approve',
        'finance.custodies.disburse',
        'finance.revenues.view',
        'finance.revenues.manage',
        'finance.assets.view',
        'finance.assets.manage',
        'finance.budgets.view',
        'finance.reports.view',
        'finance.tax_invoices.view',
        'finance.tax_invoices.issue',
        'documents.view',
        'documents.create',
        'reports.view',
        'settings.manage',
        'settings.notifications.manage',
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
