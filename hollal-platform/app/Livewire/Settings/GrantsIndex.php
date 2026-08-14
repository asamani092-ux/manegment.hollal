<?php

namespace App\Livewire\Settings;

use App\Models\ExceptionalGrant;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PermissionGrantService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

/**
 * Merged roles + grants: أدوار | صلاحيات الأدوار | استثناءات | من يملك ماذا.
 * Time: O(p) permissions | Space: O(p + roles).
 */
class GrantsIndex extends Component
{
    use AuthorizesRequests;

    /** entities|perms|exceptions|matrix */
    public string $tab = 'entities';

    public ?int $roleId = null;

    public string $roleQuery = '';

    /** @var list<string> */
    public array $selected = [];

    public bool $showRoleModal = false;

    public ?int $editingRoleId = null;

    public string $roleName = '';

    public ?int $grantUserId = null;

    public ?string $grantPermission = null;

    public string $grantReason = '';

    public ?string $grantExpiresOn = null;

    /** بحث قائمة الصلاحيات في تبويب الاستثناءات */
    public string $permissionQuery = '';

    /** بحث قائمة الموظفين في تبويب الاستثناءات */
    public string $userQuery = '';

    /** @var array<string, array<string, mixed>> */
    protected $queryString = [
        'tab' => ['except' => 'entities'],
        'roleId' => ['except' => null, 'as' => 'role'],
    ];

    public function mount(): void
    {
        $this->authorize('roles.view');

        if (! in_array($this->tab, ['entities', 'perms', 'exceptions', 'matrix'], true)) {
            $this->tab = 'entities';
        }

        if ($this->roleId === null) {
            $this->roleId = Role::orderBy('id')->value('id');
        }

        $this->loadRole();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['entities', 'perms', 'exceptions', 'matrix'], true)) {
            return;
        }
        $this->tab = $tab;
    }

    public function selectRole(int $roleId): void
    {
        $this->roleId = $roleId;
        $this->loadRole();
    }

    public function updatedRoleId(): void
    {
        $this->loadRole();
    }

    public function manageRolePermissions(int $roleId): void
    {
        $this->roleId = $roleId;
        $this->loadRole();
        $this->tab = 'perms';
    }

    public function openCreateRole(): void
    {
        $this->authorize('roles.create');
        $this->editingRoleId = null;
        $this->roleName = '';
        $this->showRoleModal = true;
        $this->resetValidation();
    }

    public function openEditRole(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->authorize('update', $role);
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->showRoleModal = true;
        $this->resetValidation();
    }

    public function closeRoleModal(): void
    {
        $this->showRoleModal = false;
        $this->editingRoleId = null;
        $this->roleName = '';
        $this->resetValidation();
    }

    public function saveRoleEntity(): void
    {
        if ($this->editingRoleId) {
            $role = Role::findOrFail($this->editingRoleId);
            $this->authorize('update', $role);
        } else {
            $this->authorize('roles.create');
        }

        $this->validate([
            'roleName' => 'required|string|max:255|unique:roles,name,'.($this->editingRoleId ?? 'NULL'),
        ], [], ['roleName' => 'اسم الدور']);

        $role = Role::updateOrCreate(
            ['id' => $this->editingRoleId],
            ['name' => $this->roleName, 'guard_name' => 'web']
        );

        app(AuditLogService::class)->record(
            $this->editingRoleId ? 'role.updated' : 'role.created',
            $role,
            ['name' => $role->name]
        );

        $this->closeRoleModal();
        $this->roleId = $role->id;
        $this->dispatch('ds-toast', message: 'تم حفظ الدور');
    }

    public function deleteRole(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->authorize('delete', $role);
        $roleName = $role->name;
        $permissions = $role->permissions->pluck('name')->all();
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(AuditLogService::class)->record('role.deleted', metadata: [
            'role_name' => $roleName,
            'permissions' => $permissions,
        ]);

        if ($this->roleId === $id) {
            $this->roleId = Role::orderBy('id')->value('id');
            $this->loadRole();
        }

        $this->dispatch('ds-toast', message: 'تم حذف الدور');
    }

    public function toggleAll(bool $on): void
    {
        $this->selected = $on ? PermissionSeeder::PERMISSIONS : [];
    }

    public function toggleSection(string $section, bool $on): void
    {
        $inSection = collect(PermissionSeeder::PERMISSIONS)
            ->filter(fn (string $p) => str_starts_with($p, $section.'.'))
            ->values();

        $this->selected = $on
            ? collect($this->selected)->merge($inSection)->unique()->values()->all()
            : collect($this->selected)->reject(fn (string $p) => $inSection->contains($p))->values()->all();
    }

    public function saveRole(): void
    {
        $this->authorize('roles.update');

        app(PermissionGrantService::class)->syncRolePermissions(
            Role::findOrFail($this->roleId),
            array_values(array_intersect($this->selected, PermissionSeeder::PERMISSIONS)),
            auth()->user(),
        );

        $this->dispatch('ds-toast', message: 'تم حفظ صلاحيات الدور');
    }

    public function grantException(): void
    {
        $this->authorize('roles.update');

        $this->validate([
            'grantUserId' => 'required|exists:users,id',
            'grantPermission' => 'required|string',
            'grantReason' => 'required|string|min:3|max:255',
            'grantExpiresOn' => 'nullable|date',
        ], [
            'grantReason.required' => 'الاستثناء يتطلب سببًا مكتوبًا',
        ], ['grantUserId' => 'الموظف', 'grantPermission' => 'الصلاحية']);

        try {
            app(PermissionGrantService::class)->grantException(
                User::findOrFail($this->grantUserId),
                $this->grantPermission,
                $this->grantReason,
                auth()->user(),
                $this->grantExpiresOn,
            );

            $this->grantReason = '';
            $this->grantPermission = null;
            $this->permissionQuery = '';
            $this->grantUserId = null;
            $this->userQuery = '';
            $this->dispatch('ds-toast', message: 'تم منح الاستثناء');
        } catch (\InvalidArgumentException $e) {
            $this->addError('grantPermission', $e->getMessage());
        }
    }

    /** اختيار صلاحية من نتائج البحث. O(1). */
    public function selectGrantPermission(string $permission): void
    {
        if (! in_array($permission, PermissionSeeder::PERMISSIONS, true)) {
            $this->addError('grantPermission', 'صلاحية غير معروفة');

            return;
        }

        $this->grantPermission = $permission;
        $this->resetErrorBag('grantPermission');
    }

    public function clearGrantPermission(): void
    {
        $this->grantPermission = null;
        $this->permissionQuery = '';
    }

    /** اختيار موظف من نتائج البحث. O(1). */
    public function selectGrantUser(int $userId): void
    {
        if (! User::query()->whereKey($userId)->exists()) {
            $this->addError('grantUserId', 'موظف غير موجود');

            return;
        }

        $this->grantUserId = $userId;
        $this->resetErrorBag('grantUserId');
    }

    public function clearGrantUser(): void
    {
        $this->grantUserId = null;
        $this->userQuery = '';
    }

    public function revokeException(int $grantId): void
    {
        $this->authorize('roles.update');

        app(PermissionGrantService::class)->revokeException(ExceptionalGrant::findOrFail($grantId), auth()->user());

        $this->dispatch('ds-toast', message: 'تم سحب الاستثناء');
    }

    public function exportMatrix()
    {
        $this->authorize('roles.view');

        $csv = app(PermissionGrantService::class)->matrixCsv();

        return response()->streamDownload(
            fn () => print ($csv),
            'permissions-matrix-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function render(): View
    {
        $labels = config('permission_labels.labels', []);
        $groups = config('permission_labels.groups', []);

        $permissionChoices = collect(PermissionSeeder::PERMISSIONS)
            ->mapWithKeys(fn (string $p) => [$p => $labels[$p] ?? $p])
            ->when($this->permissionQuery !== '', function ($collection) {
                $term = mb_strtolower(trim($this->permissionQuery));

                return $collection->filter(function (string $label, string $key) use ($term) {
                    return str_contains(mb_strtolower($label), $term)
                        || str_contains(mb_strtolower($key), $term);
                });
            });

        return view('livewire.settings.grants-index', [
            'roleEntities' => Role::query()
                ->withCount('permissions')
                ->orderBy('name')
                ->get(['id', 'name']),
            'roles' => Role::query()
                ->orderBy('id')
                ->when($this->roleQuery !== '', fn ($q) => $q->where('name', 'like', '%'.$this->roleQuery.'%'))
                ->get(),
            'permissions' => collect(PermissionSeeder::PERMISSIONS)
                ->groupBy(fn (string $p) => explode('.', $p, 2)[0]),
            'permissionChoices' => $permissionChoices,
            'labels' => $labels,
            'groups' => $groups,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'userChoices' => User::query()
                ->select(['id', 'name', 'phone'])
                ->orderBy('name')
                ->when($this->userQuery !== '', function ($q) {
                    $term = '%'.trim($this->userQuery).'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                    });
                })
                ->limit(40)
                ->get(),
            'selectedGrantUser' => $this->grantUserId
                ? User::query()->select(['id', 'name', 'phone'])->find($this->grantUserId)
                : null,
            'exceptions' => ExceptionalGrant::with('user')->orderByDesc('id')->get(),
            'matrix' => $this->tab === 'matrix' ? app(PermissionGrantService::class)->matrix() : collect(),
        ])->layout('layouts.app', ['title' => 'الأدوار والصلاحيات']);
    }

    private function loadRole(): void
    {
        $this->selected = $this->roleId
            ? Role::findOrFail($this->roleId)->permissions()->pluck('name')->all()
            : [];
    }
}
