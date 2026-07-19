<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates Super Admin and operational roles with explicit least-privilege permissions.
 * Run after PermissionSeeder. Permission names follow the hierarchical
 * tab.section.action convention (00-B2).
 */
class RoleSeeder extends Seeder
{
    /** @var list<string> */
    private const GENERAL_MANAGER_PERMISSIONS = [
        'dashboard.view',
        'hr.employees.view',
        'roles.view',
        'structure.view',
        'structure.departments.view',
        'projects.view',
        'partnerships.view',
        'esnad.tasks.view',
        'esnad.tasks.team.view',
        'meetings.view',
        'finance.expenses.view',
        'finance.expenses.approve',
        'finance.expenses.pay',
        'hr.salaries.view',
        'documents.view',
        'documents.templates.manage',
        'documents.policies.manage',
        'partnerships.contracts.view',
        'partnerships.contracts.manage',
        'finance.budgets.view',
        'finance.reports.view',
        'reports.view',
        'reports.weekly.view',
        'reports.monthly.view',
        'reports.projects.view',
        'reports.impact.view',
        'reports.kpis.view',
        'reports.audit-log.view',
        'reports.export',
    ];

    /** @var list<string> */
    private const EXECUTIVE_MANAGER_PERMISSIONS = [
        'dashboard.view',
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
        'esnad.tasks.view',
        'esnad.tasks.team.view',
        'esnad.tasks.create',
        'esnad.tasks.update',
        'esnad.tasks.delete',
        'meetings.view',
        'meetings.create',
        'meetings.update',
        'meetings.delete',
        'hr.employees.view',
        'finance.expenses.view',
        'finance.expenses.approve',
        'finance.custodies.view',
        'finance.custodies.approve',
        'reports.view',
        'reports.weekly.view',
        'reports.monthly.view',
        'reports.projects.view',
        'reports.impact.view',
        'reports.export',
        'documents.view',
        'documents.create',
        'documents.manage-versions',
        'documents.templates.manage',
        'documents.policies.manage',
        'structure.view',
        'structure.manage',
        'structure.positions.manage',
        'structure.committees.manage',
    ];

    /** @var list<string> */
    private const PROJECT_MANAGER_PERMISSIONS = [
        'dashboard.view',
        'projects.view',
        'projects.update',
        'projects.programs.view',
        'projects.visits.view',
        'projects.visits.manage',
        'projects.measurement.view',
        'projects.measurement.manage',
        'projects.close',
        'esnad.tasks.view',
        'esnad.tasks.team.view',
        'esnad.tasks.create',
        'esnad.tasks.update',
        'esnad.tasks.delete',
        'meetings.view',
        'meetings.create',
        'documents.view',
        'documents.create',
        'finance.expenses.view',
        'finance.expenses.create',
    ];

    /** @var list<string> */
    private const FINANCE_PERMISSIONS = [
        'dashboard.view',
        'finance.expenses.view',
        'finance.expenses.create',
        'finance.expenses.approve',
        'finance.expenses.pay',
        'finance.payroll.view',
        'finance.payroll.approve',
        'finance.custodies.view',
        'finance.custodies.disburse',
        'finance.revenues.view',
        'finance.revenues.manage',
        'finance.assets.view',
        'finance.assets.manage',
        'finance.budgets.view',
        'finance.reports.view',
        'finance.tax_invoices.view',
        'finance.tax_invoices.issue',
        'partnerships.payments.view',
        'partnerships.payments.confirm',
        'hr.salaries.view',
        'hr.salaries.manage',
        'partnerships.contracts.view',
        'reports.view',
        'reports.monthly.view',
        'reports.export',
    ];

    /** @var list<string> */
    private const EMPLOYEE_PERMISSIONS = [
        'dashboard.view',
        'esnad.tasks.view',
        'esnad.tasks.create',
        'meetings.view',
        'documents.view',
        'finance.expenses.view',
        'finance.expenses.create',
        'projects.view',
    ];

    /**
     * Role #7 — مدير الشراكات (Partnerships Manager): partnerships.* + projects.view.
     *
     * @var list<string>
     */
    private const PARTNERSHIPS_MANAGER_PERMISSIONS = [
        'dashboard.view',
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
        'partnerships.links.manage',
        'partnerships.company-profile.manage',
        'partnerships.generate',
        'projects.view',
        'projects.programs.view',
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
        // مدير الشراكات (#7)
        $this->syncRole('Partnerships Manager', self::PARTNERSHIPS_MANAGER_PERMISSIONS);

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
