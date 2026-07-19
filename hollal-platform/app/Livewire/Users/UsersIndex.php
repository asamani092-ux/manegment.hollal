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

    // 01-B1 — directory search / filters / view toggle.
    public string $search = '';

    public ?int $filterDepartment = null;

    public string $filterStatus = '';

    public string $filterType = '';

    public string $viewMode = 'cards';

    public function mount(): void
    {
        $this->authorize('hr.employees.view');
    }

    public function toggleView(): void
    {
        $this->viewMode = $this->viewMode === 'cards' ? 'table' : 'cards';
    }

    public function openCreateModal(): void
    {
        $this->authorize('hr.employees.create');
        $this->viewOnly = false;
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $user = User::with('roles:id,name')->findOrFail($id);
        $this->authorize('update', $user);
        $this->viewOnly = false;
        $this->fillForm($user);
        $this->showModal = true;
    }

    public function openViewModal(int $id): void
    {
        $user = User::with('roles:id,name')->findOrFail($id);
        $this->authorize('view', $user);
        $this->viewOnly = true;
        $this->fillForm($user);
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
            $user = User::findOrFail($this->userId);
            $this->authorize('update', $user);
        } else {
            $this->authorize('hr.employees.create');
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

        // 01-B5 — auto-generate onboarding tasks for a newly added employee.
        if ($user->wasRecentlyCreated) {
            app(\App\Services\OnboardingService::class)->generateTasks($user, auth()->user());
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ المستخدم بنجاح');
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);
        $user->delete();
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
                ->select(['id', 'name', 'email', 'department_id', 'is_active', 'employment_status'])
                ->with([
                    'roles:id,name',
                    'department:id,name',
                    'profile:id,user_id,job_title,employment_type',
                ])
                ->when($this->search !== '', function ($query) {
                    $query->where(function ($inner) {
                        $inner->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
                })
                ->when($this->filterDepartment, fn ($query) => $query->where('department_id', $this->filterDepartment))
                ->when($this->filterStatus !== '', fn ($query) => $query->where('employment_status', $this->filterStatus))
                ->when($this->filterType !== '', fn ($query) => $query->whereHas('profile', fn ($p) => $p->where('employment_type', $this->filterType)))
                ->orderBy('name')
                ->get(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'managers' => User::orderBy('name')->get(['id', 'name']),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'الفريق']);
    }
}
