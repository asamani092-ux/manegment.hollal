<?php

namespace App\Livewire\Settings;

use App\Models\ApprovalRule;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * إدارة قواعد سلسلة الاعتماد حسب النوع والمبلغ.
 * Time: O(n) | Space: O(n)
 */
class ApprovalRulesIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $transaction_type = ApprovalRule::TYPE_EXPENSE;

    public string $min_amount = '0';

    public string $max_amount = '';

    /** @var list<string> */
    public array $selectedRoles = [];

    public bool $is_active = true;

    /** @var list<string> */
    public array $availableRoles = [
        'department_manager',
        'finance_manager',
        'executive_director',
    ];

    public function mount(): void
    {
        $this->authorize('settings.manage');
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $rule = ApprovalRule::findOrFail($id);
        $this->editingId = $rule->id;
        $this->transaction_type = $rule->transaction_type;
        $this->min_amount = (string) $rule->min_amount;
        $this->max_amount = $rule->max_amount !== null ? (string) $rule->max_amount : '';
        $this->selectedRoles = collect($rule->approval_steps ?? [])
            ->map(fn ($s) => is_array($s) ? (string) ($s['role'] ?? '') : (string) $s)
            ->filter()
            ->values()
            ->all();
        $this->is_active = (bool) $rule->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->validate([
            'transaction_type' => 'required|in:expense,custody,quote,contract,leave',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => 'in:department_manager,finance_manager,executive_director',
            'is_active' => 'boolean',
        ]);

        $steps = array_map(fn (string $r) => ['role' => $r], $this->selectedRoles);
        $payload = [
            'transaction_type' => $this->transaction_type,
            'min_amount' => (float) $this->min_amount,
            'max_amount' => $this->max_amount === '' ? null : (float) $this->max_amount,
            'approval_steps' => $steps,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            ApprovalRule::findOrFail($this->editingId)->update($payload);
        } else {
            ApprovalRule::create($payload);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'تم حفظ قاعدة الاعتماد');
    }

    public function deleteRule(int $id): void
    {
        $this->authorize('settings.manage');
        ApprovalRule::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'حُذفت القاعدة');
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('settings.manage');
        $rule = ApprovalRule::findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->transaction_type = ApprovalRule::TYPE_EXPENSE;
        $this->min_amount = '0';
        $this->max_amount = '';
        $this->selectedRoles = ['department_manager'];
        $this->is_active = true;
    }

    public function render(): View
    {
        return view('livewire.settings.approval-rules-index', [
            'rules' => ApprovalRule::query()->orderBy('transaction_type')->orderBy('min_amount')->get(),
            'roleLabels' => [
                'department_manager' => 'مدير القسم',
                'finance_manager' => 'المدير المالي',
                'executive_director' => 'المدير التنفيذي',
            ],
            'typeLabels' => [
                'expense' => 'مصروف',
                'custody' => 'عهدة',
                'quote' => 'عرض سعر',
                'contract' => 'عقد',
                'leave' => 'إجازة',
            ],
        ])->layout('layouts.app', ['title' => 'سلسلة الاعتماد']);
    }
}
