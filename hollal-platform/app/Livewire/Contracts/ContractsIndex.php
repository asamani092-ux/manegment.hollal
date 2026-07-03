<?php

namespace App\Livewire\Contracts;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ContractsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public bool $viewOnly = false;

    public ?int $contractId = null;

    public ?int $employee_id = null;

    public string $start_date = '';

    public string $end_date = '';

    public string $value = '';

    public string $status = 'active';

    public ?TemporaryUploadedFile $contractFile = null;

    public ?string $existingContractFile = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Contract::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Contract::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $contract = Contract::findOrFail($id);
        $this->authorize('update', $contract);
        $this->fillForm($contract);
        $this->viewOnly = false;
        $this->showModal = true;
    }

    public function openView(int $id): void
    {
        $contract = Contract::findOrFail($id);
        $this->authorize('view', $contract);
        $this->fillForm($contract);
        $this->viewOnly = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        if ($this->viewOnly) {
            return;
        }

        $isEdit = (bool) $this->contractId;

        if ($isEdit) {
            $this->authorize('update', Contract::findOrFail($this->contractId));
        } else {
            $this->authorize('create', Contract::class);
        }

        $rules = [
            'employee_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:'.implode(',', Contract::STATUSES),
            'contractFile' => 'nullable|file|max:10240|mimes:pdf,doc,docx',
        ];

        if ($this->canViewValue()) {
            $rules['value'] = 'nullable|numeric|min:0';
        }

        $this->validate($rules);

        $data = [
            'employee_id' => $this->employee_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
        ];

        if ($this->canViewValue() && $this->value !== '') {
            $data['value'] = $this->value;
        }

        if ($this->contractFile) {
            if ($this->existingContractFile) {
                Storage::disk('local')->delete($this->existingContractFile);
            }
            $data['contract_file'] = $this->contractFile->store('contracts', 'local');
        }

        Contract::updateOrCreate(['id' => $this->contractId], $data);

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث العقد' : 'تم إنشاء العقد');
    }

    public function delete(int $id): void
    {
        $contract = Contract::findOrFail($id);
        $this->authorize('delete', $contract);
        $contract->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف العقد');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function canViewValue(): bool
    {
        return auth()->user()->can('viewValue', Contract::class);
    }

    public function getCanViewValueProperty(): bool
    {
        return $this->canViewValue();
    }

    public function maskedValue(?Contract $contract): string
    {
        if (! $contract) {
            return '—';
        }

        if ($this->canViewValue()) {
            return $contract->value !== null ? number_format((float) $contract->value, 2) : '—';
        }

        return '****';
    }

    protected function fillForm(Contract $contract): void
    {
        $this->contractId = $contract->id;
        $this->employee_id = $contract->employee_id;
        $this->start_date = $contract->start_date->format('Y-m-d');
        $this->end_date = $contract->end_date->format('Y-m-d');
        $this->value = $contract->value !== null ? (string) $contract->value : '';
        $this->status = $contract->status;
        $this->existingContractFile = $contract->contract_file;
        $this->contractFile = null;
    }

    protected function resetForm(): void
    {
        $this->contractId = null;
        $this->employee_id = null;
        $this->start_date = '';
        $this->end_date = '';
        $this->value = '';
        $this->status = 'active';
        $this->contractFile = null;
        $this->existingContractFile = null;
        $this->viewOnly = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.contracts.contracts-index', [
            'contracts' => Contract::query()
                ->select(['id', 'employee_id', 'start_date', 'end_date', 'value', 'contract_file', 'status', 'created_at'])
                ->with('employee:id,name')
                ->when($this->search, fn ($q) => $q->whereHas(
                    'employee',
                    fn ($eq) => $eq->where('name', 'like', '%'.$this->search.'%')
                ))
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->orderByDesc('end_date')
                ->paginate(10),
            'employees' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
            'statusOptions' => Contract::STATUSES,
            'canViewValue' => $this->canViewValue(),
        ])->layout('layouts.app', ['title' => 'العقود']);
    }
}
