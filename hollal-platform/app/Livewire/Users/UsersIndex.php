<?php

namespace App\Livewire\Users;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Users / HR CRUD — eager-loaded relations to prevent N+1.
 * Time: O(n) | Space: O(n) for table page.
 */
class UsersIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public bool $viewOnly = false;

    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public ?int $department_id = null;

    public ?int $manager_id = null;

    public bool $is_active = true;

    public string $roleName = '';

    public function mount(): void
    {
        $this->authorize('users.view');
    }

    public function openCreateModal(): void
    {
        $this->authorize('users.create');
        $this->viewOnly = false;
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('users.update');
        $this->viewOnly = false;
        $this->fillForm(User::with('roles:id,name')->findOrFail($id));
        $this->showModal = true;
    }

    public function openViewModal(int $id): void
    {
        $this->authorize('users.view');
        $this->viewOnly = true;
        $this->fillForm(User::with('roles:id,name')->findOrFail($id));
        $this->showModal = true;
    }

    protected function fillForm(User $user): void
    {
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->password = '';
        $this->department_id = $user->department_id;
        $this->manager_id = $user->manager_id;
        $this->is_active = (bool) $user->is_active;
        $this->roleName = $user->roles->first()?->name ?? '';
    }

    public function save(): void
    {
        if ($this->viewOnly) {
            return;
        }

        if ($this->userId) {
            $this->authorize('users.update');
        } else {
            $this->authorize('users.create');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.($this->userId ?? 'NULL'),
            'phone' => 'required|string|max:50|unique:users,phone,'.($this->userId ?? 'NULL'),
            'department_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'roleName' => 'required|string|exists:roles,name',
        ];

        if (! $this->userId) {
            $rules['password'] = 'required|string|min:8';
        } else {
            $rules['password'] = 'nullable|string|min:8';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department_id' => $this->department_id,
            'manager_id' => $this->manager_id,
            'is_active' => $this->is_active,
        ];

        if ($this->password !== '') {
            $data['password'] = Hash::make($this->password);
        }

        $user = User::updateOrCreate(['id' => $this->userId], $data);
        $user->syncRoles([$this->roleName]);

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ المستخدم بنجاح');
    }

    /** Soft delete only. */
    public function delete(int $id): void
    {
        $this->authorize('users.delete');

        if ($id === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'لا يمكنك حذف حسابك');

            return;
        }

        User::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف المستخدم');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->password = '';
        $this->department_id = null;
        $this->manager_id = null;
        $this->is_active = true;
        $this->roleName = '';
        $this->viewOnly = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.users.users-index', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'department_id', 'is_active'])
                ->with([
                    'roles:id,name',
                    'department:id,name',
                ])
                ->orderBy('name')
                ->get(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'managers' => User::orderBy('name')->get(['id', 'name']),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'الفريق']);
    }
}
