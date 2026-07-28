<?php

namespace App\Livewire\Hr;

use App\Models\Responsibility;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Employee responsibilities CRUD.
 * Time: O(n) | Space: O(page).
 */
class ResponsibilitiesIndex extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $employee_id = null;

    public string $body = '';

    public int $order = 1;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
    }

    public function openForm(): void
    {
        $this->reset(['body']);
        $this->order = 1;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'employee_id' => 'required|exists:users,id',
            'body' => 'required|string|min:3|max:500',
            'order' => 'required|integer|min:1|max:20',
        ]);

        Responsibility::create([
            'employee_id' => $this->employee_id,
            'body' => $this->body,
            'order' => $this->order,
            'is_active' => true,
        ]);

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'تمت إضافة بند المسؤولية');
    }

    public function deactivate(int $id): void
    {
        $item = Responsibility::findOrFail($id);
        $item->update(['is_active' => false]);
        $this->dispatch('toast', type: 'success', message: 'تم إيقاف البند');
    }

    public function render(): View
    {
        return view('livewire.hr.responsibilities-index', [
            'items' => Responsibility::query()
                ->select(['id', 'employee_id', 'body', 'order', 'is_active'])
                ->with('employee:id,name')
                ->orderBy('employee_id')
                ->orderBy('order')
                ->paginate(20),
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
