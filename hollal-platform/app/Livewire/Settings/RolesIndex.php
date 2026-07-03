<?php

namespace App\Livewire\Settings;

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
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
        } else {
            $this->authorize('roles.create');
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

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ الدور بنجاح');
    }

    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->authorize('delete', $role);
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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
            'allPermissions' => Permission::orderBy('name')
                ->get(['id', 'name']),
            'permissionGroups' => collect(PermissionSeeder::PERMISSIONS),
        ])->layout('layouts.app', ['title' => 'الأدوار والصلاحيات']);
    }
}
