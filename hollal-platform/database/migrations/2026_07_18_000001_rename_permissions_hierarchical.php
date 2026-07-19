<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * 00-B2 — Hierarchical permission rename (tab.section.action).
 *
 * Renames existing permission rows in place so role_has_permissions /
 * model_has_permissions pivots (keyed by permission_id) stay intact.
 * Fresh installs seed the new names directly; on those DBs these updates
 * simply affect zero rows.
 */
return new class extends Migration
{
    /** @var array<string, string> old name => new hierarchical name */
    private const MAP = [
        'users.view' => 'hr.employees.view',
        'users.create' => 'hr.employees.create',
        'users.update' => 'hr.employees.update',
        'users.delete' => 'hr.employees.delete',
        'departments.view' => 'structure.departments.view',
        'departments.create' => 'structure.departments.create',
        'departments.update' => 'structure.departments.update',
        'departments.delete' => 'structure.departments.delete',
        'tasks.view' => 'esnad.tasks.view',
        'tasks.create' => 'esnad.tasks.create',
        'tasks.update' => 'esnad.tasks.update',
        'tasks.delete' => 'esnad.tasks.delete',
        'expenses.view' => 'finance.expenses.view',
        'expenses.create' => 'finance.expenses.create',
        'expenses.approve' => 'finance.expenses.approve',
        'expenses.pay' => 'finance.expenses.pay',
        'salaries.view' => 'hr.salaries.view',
        'salaries.manage' => 'hr.salaries.manage',
        'contracts.view' => 'partnerships.contracts.view',
        'contracts.create' => 'partnerships.contracts.create',
        'contracts.manage' => 'partnerships.contracts.manage',
    ];

    public function up(): void
    {
        $this->rename(self::MAP);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::MAP));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            $fromExists = DB::table('permissions')->where('name', $from)->where('guard_name', 'web')->exists();
            $toExists = DB::table('permissions')->where('name', $to)->where('guard_name', 'web')->exists();

            if ($fromExists && ! $toExists) {
                DB::table('permissions')
                    ->where('name', $from)
                    ->where('guard_name', 'web')
                    ->update(['name' => $to]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
