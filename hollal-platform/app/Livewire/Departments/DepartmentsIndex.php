<?php

namespace App\Livewire\Departments;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Departments CRUD — name only, paginated table.
 */
class DepartmentsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $viewOnly = false;

    public ?int $departmentId = null;

    public string $name = '';

    protected $queryString = ['search' => ['except' => '']];

    public function mount(): void
    {
        $this->authorize('structure.departments.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('structure.departments.create');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $department = Department::findOrFail($id);
        $this->authorize('update', $department);
        $this->departmentId = $department->id;
        $this->name = $department->name;
        $this->viewOnly = false;
        $this->showModal = true;
    }

    public function openView(int $id): void
    {
        $department = Department::findOrFail($id);
        $this->authorize('view', $department);
        $this->departmentId = $department->id;
        $this->name = $department->name;
        $this->viewOnly = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        if ($this->viewOnly) {
            return;
        }

        $isEdit = (bool) $this->departmentId;

        if ($isEdit) {
            $department = Department::findOrFail($this->departmentId);
            $this->authorize('update', $department);
        } else {
            $this->authorize('structure.departments.create');
        }

        $this->validate([
            'name' => 'required|string|max:255|unique:departments,name,'.($this->departmentId ?? 'NULL'),
        ]);

        Department::updateOrCreate(['id' => $this->departmentId], ['name' => $this->name]);

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث القسم' : 'تم إنشاء القسم');
    }

    public function delete(int $id): void
    {
        $department = Department::findOrFail($id);
        $this->authorize('delete', $department);
        $department->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف القسم');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->departmentId = null;
        $this->name = '';
        $this->viewOnly = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.departments.departments-index', [
            'departments' => Department::query()
                ->select(['id', 'name', 'created_at'])
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->orderBy('name')
                ->paginate(10),
        ])->layout('layouts.app', ['title' => 'الأقسام']);
    }
}
