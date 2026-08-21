<?php

namespace App\Livewire\Finance;

use App\Models\ChartOfAccount;
use App\Services\ChartOfAccountsService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * FIN-ACC-1 — collapsible chart of accounts tree with CRUD.
 * Time: O(n) | Space: O(n)
 */
class ChartOfAccountsIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name_ar = '';

    public string $type = ChartOfAccount::TYPE_ASSETS;

    public string $nature = ChartOfAccount::NATURE_DEBIT;

    public ?int $parent_id = null;

    public bool $is_active = true;

    /** @var array<int, bool> */
    public array $expanded = [];

    public function mount(): void
    {
        $this->authorize('finance.accounting.manage');
    }

    public function openCreate(?int $parentId = null): void
    {
        $this->resetForm();
        $this->parent_id = $parentId;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $account = ChartOfAccount::findOrFail($id);
        $this->editingId = $account->id;
        $this->code = $account->code;
        $this->name_ar = $account->name_ar;
        $this->type = $account->type;
        $this->nature = $account->nature;
        $this->parent_id = $account->parent_id;
        $this->is_active = (bool) $account->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('finance.accounting.manage');

        $this->validate([
            'code' => 'required|string|max:32|unique:chart_of_accounts,code,'.($this->editingId ?? 'NULL').',id,deleted_at,NULL',
            'name_ar' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', ChartOfAccount::TYPES),
            'nature' => 'required|in:مدين,دائن',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ], [], [
            'code' => 'رقم الحساب',
            'name_ar' => 'اسم الحساب',
            'type' => 'النوع',
            'nature' => 'الطبيعة',
            'parent_id' => 'الحساب الأب',
        ]);

        $payload = [
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'type' => $this->type,
            'nature' => $this->nature,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
        ];

        $service = app(ChartOfAccountsService::class);

        if ($this->editingId) {
            $service->update(ChartOfAccount::findOrFail($this->editingId), $payload, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم تحديث الحساب');
        } else {
            $service->create($payload, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم إنشاء الحساب');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function deleteAccount(int $id): void
    {
        $this->authorize('finance.accounting.manage');

        try {
            app(ChartOfAccountsService::class)->delete(ChartOfAccount::findOrFail($id), auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم حذف الحساب');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function toggle(int $id): void
    {
        $this->expanded[$id] = ! ($this->expanded[$id] ?? false);
    }

    public function render(): View
    {
        return view('livewire.finance.chart-of-accounts-index', [
            'roots' => app(ChartOfAccountsService::class)->tree(),
            'parents' => ChartOfAccount::query()->orderBy('code')->get(['id', 'code', 'name_ar']),
            'types' => ChartOfAccount::TYPES,
        ])->layout('layouts.app', ['title' => 'دليل الحسابات']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name_ar = '';
        $this->type = ChartOfAccount::TYPE_ASSETS;
        $this->nature = ChartOfAccount::NATURE_DEBIT;
        $this->parent_id = null;
        $this->is_active = true;
    }
}
