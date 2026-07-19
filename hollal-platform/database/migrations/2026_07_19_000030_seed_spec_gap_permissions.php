<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec gap fill — insert hierarchical permissions required by
 * spec-07-08 / spec-09 without editing prior migrations.
 *
 * Time: O(P) | Space: O(1)
 */
return new class extends Migration
{
    /** @var list<string> */
    private const NEW_PERMISSIONS = [
        'structure.view',
        'structure.manage',
        'structure.positions.manage',
        'structure.committees.manage',
        'documents.manage-versions',
        'documents.templates.manage',
        'documents.policies.manage',
        'reports.weekly.view',
        'reports.monthly.view',
        'reports.projects.view',
        'reports.impact.view',
        'reports.kpis.view',
        'reports.audit-log.view',
        'reports.export',
        'settings.general.manage',
        'settings.finance.manage',
        'settings.backup.manage',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::NEW_PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
