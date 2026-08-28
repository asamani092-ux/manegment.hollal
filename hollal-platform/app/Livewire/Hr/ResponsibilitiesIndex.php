<?php

namespace App\Livewire\Hr;

use App\Models\Responsibility;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\UsesDsPagination;

/**
 * Employee responsibilities CRUD with multi-add and employee floating panel.
 * Time: O(n) | Space: O(page).
 */
class ResponsibilitiesIndex extends Component
{
    use WithPagination;
    use UsesDsPagination;

    public bool $showForm = false;

    public bool $showEmployeePanel = false;

    public ?int $panelEmployeeId = null;

    public ?int $editingId = null;

    public ?int $employee_id = null;

    public string $body = '';

    /** Extra bodies for multi-add (one per line). */
    public string $extraBodies = '';

    public int $order = 1;

    public string $search = '';

    public bool $activeOnly = false;

    /** @var array<string, array<string, string|bool>> */
    protected $queryString = [
        'search' => ['except' => ''],
        'activeOnly' => ['except' => false],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActiveOnly(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $this->reset(['body', 'extraBodies', 'editingId']);
        $this->order = 1;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        $item = Responsibility::findOrFail($id);
        $this->editingId = $item->id;
        $this->employee_id = $item->employee_id;
        $this->body = $item->body;
        $this->order = (int) $item->order;
        $this->extraBodies = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $this->validate([
            'employee_id' => 'required|exists:users,id',
            'body' => 'required|string|min:3|max:500',
            'order' => 'required|integer|min:1|max:20',
            'extraBodies' => 'nullable|string|max:4000',
        ]);

        if ($this->editingId) {
            $item = Responsibility::findOrFail($this->editingId);
            $item->update([
                'employee_id' => $this->employee_id,
                'body' => $this->body,
                'order' => $this->order,
            ]);
            $this->showForm = false;
            $this->dispatch('toast', type: 'success', message: 'تم تعديل بند المسؤولية');

            return;
        }

        $bodies = collect([$this->body])
            ->merge(collect(preg_split('/\r\n|\r|\n/', $this->extraBodies) ?: [])->map(fn ($l) => trim((string) $l))->filter())
            ->unique()
            ->values();

        $order = $this->order;
        foreach ($bodies as $body) {
            Responsibility::create([
                'employee_id' => $this->employee_id,
                'body' => $body,
                'order' => min(20, $order),
                'is_active' => true,
            ]);
            $order++;
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'أُضيفت '. $bodies->count().' مسؤولية');
    }

    public function deactivate(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);

        $item = Responsibility::findOrFail($id);
        $item->update(['is_active' => false]);
        $this->dispatch('toast', type: 'success', message: 'تم إيقاف البند');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('hr.employees.update'), 403);
        Responsibility::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'حُذف بند المسؤولية');
    }

    public function openEmployeePanel(int $employeeId): void
    {
        $this->panelEmployeeId = $employeeId;
        $this->showEmployeePanel = true;
    }

    public function closeEmployeePanel(): void
    {
        $this->showEmployeePanel = false;
        $this->panelEmployeeId = null;
    }

    public function render(): View
    {
        $panelItems = collect();
        $panelEmployee = null;
        if ($this->panelEmployeeId) {
            $panelEmployee = User::query()->find($this->panelEmployeeId, ['id', 'name']);
            $panelItems = Responsibility::query()
                ->where('employee_id', $this->panelEmployeeId)
                ->orderBy('order')
                ->get(['id', 'employee_id', 'body', 'order', 'is_active']);
        }

        return view('livewire.hr.responsibilities-index', [
            'items' => Responsibility::query()
                ->select(['id', 'employee_id', 'body', 'order', 'is_active'])
                ->with('employee:id,name')
                ->when($this->activeOnly, fn ($q) => $q->where('is_active', true))
                ->when($this->search, fn ($q) => $q->whereHas(
                    'employee',
                    fn ($e) => $e->where('name', 'like', '%'.$this->search.'%')
                ))
                ->orderBy('employee_id')
                ->orderBy('order')
                ->paginate(20),
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employeeOptions' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'label' => $u->name])
                ->all(),
            'panelEmployee' => $panelEmployee,
            'panelItems' => $panelItems,
        ]);
    }
}
