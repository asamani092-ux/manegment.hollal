<?php

namespace App\Console\Commands;

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent HR reference data for deploy. Never overwrites owner-edited items.
 */
class HrSeedReference extends Command
{
    protected $signature = 'hr:seed-reference';

    protected $description = 'Seed missing HR reference lists and permissions without overwriting edits';

    public function handle(): int
    {
        $this->call(PermissionSeeder::class);
        $this->call(ReferenceListsSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::query()->where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($role) {
            foreach (PermissionSeeder::PERMISSIONS as $name) {
                $permission = Permission::findByName($name, 'web');
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        $this->info('hr:seed-reference done');

        return self::SUCCESS;
    }
}
