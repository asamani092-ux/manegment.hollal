<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Add executive final-approve for quotes without rewriting prior seeds.
 * Time: O(1) | Space: O(1)
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $finalize = Permission::firstOrCreate([
            'name' => 'partnerships.quotes.finalize',
            'guard_name' => 'web',
        ]);

        foreach (['Super Admin', 'Executive Manager'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($finalize);
        }

        $general = Role::query()->where('name', 'General Manager')->where('guard_name', 'web')->first();
        $general?->givePermissionTo([
            'partnerships.pipeline.view',
            'partnerships.quotes.view',
            'partnerships.quotes.approve',
            $finalize,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'partnerships.quotes.finalize')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
