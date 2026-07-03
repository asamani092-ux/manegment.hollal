<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custom Role model with SoftDeletes and automatic Spatie cache invalidation.
 */
class Role extends SpatieRole
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saved(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        static::deleted(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        static::restored(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
    }
}
