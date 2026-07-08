<?php

namespace App\Livewire\Settings;

use App\Models\Role;
use App\Services\AuditLogService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles & Permissions CRUD — floating modal + ds-table.
 * Time: O(n) list render | Space: O(n) for current page rows.
 */
class RolesIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public ?int $roleId = null;

    public string $name = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        $this->authorize('roles.view');
    }

    public function openCreateModal(): void
    {
        $this->authorize('roles.create');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $role = Role::with('permissions:id,name')->findOrFail($id);
        $this->authorize('update', $role);
        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
        $this->showModal = true;
    }

    public function save(): void
    {
        if ($this->roleId) {
            $role = Role::findOrFail($this->roleId);
            $this->authorize('update', $role);
            $previousPermissions = $role->permissions->pluck('name')->all();
        } else {
            $this->authorize('roles.create');
            $previousPermissions = [];
        }

        $this->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.($this->roleId ?? 'NULL'),
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'string',
        ]);

        $role = Role::updateOrCreate(
            ['id' => $this->roleId],
            ['name' => $this->name, 'guard_name' => 'web']
        );

        $role->syncPermissions($this->selectedPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(AuditLogService::class)->record(
            $this->roleId ? 'role.updated' : 'role.created',
            $role,
            [
                'permissions_before' => $previousPermissions,
                'permissions_after' => $this->selectedPermissions,
            ]
        );

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ الدور بنجاح');
    }

    public function delete(int $id): void
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
        $this->dispatch('toast', type: 'success', message: 'تم حذف الدور');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->roleId = null;
        $this->name = '';
        $this->selectedPermissions = [];
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.settings.roles-index', [
            'roles' => Role::withCount('permissions')
                ->orderBy('name')
                ->get(['id', 'name', 'created_at']),
            'groupedPermissions' => collect(PermissionSeeder::PERMISSIONS)
                ->groupBy(fn (string $permission): string => explode('.', $permission, 2)[0]),
        ])->layout('layouts.app', ['title' => 'الأدوار والصلاحيات']);
    }
}
