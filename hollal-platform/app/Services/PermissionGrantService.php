<?php

namespace App\Services;

use App\Models\ExceptionalGrant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 10-B1 — permission grants.
 *
 * Role permissions are the norm; anything granted to one person directly is an
 * exception and must carry a reason and a date. Every change is audited.
 */
class PermissionGrantService
{
    /**
     * Grant a permission to one user outside their role.
     */
    public function grantException(
        User $user,
        string $permission,
        string $reason,
        ?User $actor = null,
        ?string $expiresOn = null,
    ): ExceptionalGrant {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('الاستثناء يتطلب سببًا مكتوبًا');
        }

        if (! in_array($permission, PermissionSeeder::PERMISSIONS, true)) {
            throw new \InvalidArgumentException('صلاحية غير معروفة: '.$permission);
        }

        return DB::transaction(function () use ($user, $permission, $reason, $actor, $expiresOn) {
            $user->givePermissionTo($permission);

            $grant = ExceptionalGrant::create([
                'user_id' => $user->id,
                'permission' => $permission,
                'reason' => $reason,
                'granted_on' => now()->toDateString(),
                'expires_on' => $expiresOn,
                'granted_by' => $actor?->id,
            ]);

            app(AuditLogService::class)->record(
                action: 'permissions.exceptional_granted',
                target: $user,
                metadata: ['permission' => $permission, 'reason' => $reason, 'expires_on' => $expiresOn],
                actor: $actor,
            );

            return $grant;
        });
    }

    public function revokeException(ExceptionalGrant $grant, ?User $actor = null): ExceptionalGrant
    {
        return DB::transaction(function () use ($grant, $actor) {
            $grant->user?->revokePermissionTo($grant->permission);
            $grant->forceFill(['revoked_at' => now()])->save();

            app(AuditLogService::class)->record(
                action: 'permissions.exceptional_revoked',
                target: $grant->user,
                metadata: ['permission' => $grant->permission],
                actor: $actor,
            );

            return $grant;
        });
    }

    /**
     * Sync a role's permissions and audit the delta.
     *
     * @param  list<string>  $permissions
     */
    public function syncRolePermissions(Role $role, array $permissions, ?User $actor = null): Role
    {
        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        $role->syncPermissions($permissions);

        app(AuditLogService::class)->record(
            action: 'permissions.role_synced',
            target: $role,
            metadata: [
                'role' => $role->name,
                'before' => $before,
                'after' => collect($permissions)->sort()->values()->all(),
            ],
            actor: $actor,
        );

        return $role->fresh();
    }

    /**
     * «من يملك ماذا» — the review matrix: user × permission, marking whether the
     * permission arrives through a role or as an exception.
     *
     * @return Collection<int, array{user: User, permissions: array<string, string>}>
     */
    public function matrix(): Collection
    {
        $exceptions = ExceptionalGrant::query()
            ->whereNull('revoked_at')
            ->get()
            ->groupBy('user_id');

        return User::query()->with(['roles.permissions', 'permissions'])->orderBy('name')->get()
            ->map(function (User $user) use ($exceptions) {
                $rolePermissions = $user->roles->flatMap->permissions->pluck('name')->unique();
                $exceptional = ($exceptions[$user->id] ?? collect())->pluck('permission')->unique();

                $map = [];
                foreach ($rolePermissions as $permission) {
                    $map[$permission] = 'دور';
                }
                foreach ($exceptional as $permission) {
                    $map[$permission] = 'استثناء';
                }

                ksort($map);

                return ['user' => $user, 'permissions' => $map];
            });
    }

    /** CSV of the matrix, for the review export. */
    public function matrixCsv(): string
    {
        $lines = ["\xEF\xBB\xBF".'الموظف,الصلاحية,المصدر'];

        foreach ($this->matrix() as $row) {
            foreach ($row['permissions'] as $permission => $source) {
                $lines[] = '"'.str_replace('"', '""', $row['user']->name).'","'.$permission.'","'.$source.'"';
            }
        }

        return implode("\n", $lines);
    }
}
