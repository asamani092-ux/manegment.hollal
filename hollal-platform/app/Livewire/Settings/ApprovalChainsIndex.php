<?php

namespace App\Livewire\Settings;

use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Ordered approval chains for every request type.
 * Time: O(rules + steps) | Space: O(rules)
 */
class ApprovalChainsIndex extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $transaction_type = ApprovalRule::TYPE_EXPENSE;

    public string $min_amount = '0';

    public string $max_amount = '';

    /** @var list<array{kind: string, user_id: ?int, role: string}> */
    public array $steps = [];

    public string $draftKind = 'direct_manager';

    public ?int $draftUserId = null;

    public string $draftRole = 'Employee';

    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.approval-chains.manage') || auth()->user()->can('settings.manage'), 403);
        $this->steps = [['kind' => 'direct_manager', 'user_id' => null, 'role' => '']];
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->transaction_type = ApprovalRule::TYPE_EXPENSE;
        $this->min_amount = '0';
        $this->max_amount = '';
        $this->steps = [['kind' => 'direct_manager', 'user_id' => null, 'role' => '']];
        $this->is_active = true;
        $this->showModal = true;
    }

    public function addStep(): void
    {
        $this->steps[] = [
            'kind' => $this->draftKind,
            'user_id' => $this->draftKind === 'user' ? $this->draftUserId : null,
            'role' => $this->draftKind === 'role' ? $this->draftRole : '',
        ];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.approval-chains.manage') || auth()->user()->can('settings.manage'), 403);
        $this->validate([
            'transaction_type' => 'required|string',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric',
            'steps' => 'required|array|min:1',
        ]);

        $payload = [
            'transaction_type' => $this->transaction_type,
            'min_amount' => (float) $this->min_amount,
            'max_amount' => $this->max_amount === '' ? null : (float) $this->max_amount,
            'approval_steps' => $this->storedSteps(),
            'is_active' => $this->is_active,
            'metric' => 'amount',
        ];

        if ($this->editingId) {
            ApprovalRule::query()->findOrFail($this->editingId)->update($payload);
        } else {
            ApprovalRule::query()->create($payload);
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'حُفظت سلسلة الاعتماد');
    }

    /** @return list<array<string, mixed>> */
    private function storedSteps(): array
    {
        $out = [];
        foreach ($this->steps as $step) {
            $kind = $step['kind'] ?? 'direct_manager';
            $out[] = match ($kind) {
                'user' => ['type' => 'user', 'user_id' => (int) ($step['user_id'] ?? 0)],
                'role' => ['type' => 'role', 'role' => (string) ($step['role'] ?? '')],
                'finance' => ['role' => 'finance_manager'],
                'executive' => ['role' => 'executive_director'],
                default => ['type' => 'direct_manager'],
            };
        }

        return $out;
    }

    public function render(): View
    {
        return view('livewire.settings.approval-chains-index', [
            'rules' => ApprovalRule::query()->orderBy('transaction_type')->orderBy('min_amount')->get(),
            'typeLabels' => [
                'expense' => 'مصروف',
                'custody' => 'عهدة',
                'quote' => 'عرض سعر',
                'contract' => 'عقد',
                'leave' => 'إجازة',
                'overtime' => 'عمل إضافي',
                'excuse' => 'استئذان',
                'delegation' => 'إنابة',
            ],
            'employees' => User::query()->orderBy('name')->limit(200)->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'سلاسل الطلبات']);
    }
}
