<?php

namespace App\Livewire\Settings;

use App\Models\ApprovalChain;
use App\Models\ApprovalChainStep;
use App\Models\Delegation;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * شاشة السلسلة الواحدة لكل نوع طلب.
 * Time: O(steps + employees) | Space: O(steps)
 */
class ApprovalChainsIndex extends Component
{
    public string $selectedType = 'expense';

    /** @var list<array<string, mixed>> */
    public array $steps = [];

    public string $picker = '';

    public ?int $previewRequesterId = null;

    public string $previewValue = '0';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.approval-chains.manage') || auth()->user()->can('settings.manage'), 403);
        $this->loadChain();
    }

    public function selectType(string $type): void
    {
        if (! in_array($type, ApprovalChain::TYPES, true)) {
            return;
        }
        $this->selectedType = $type;
        $this->loadChain();
    }

    public function addStep(): void
    {
        $this->steps[] = $this->blankStep();
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function moveStep(int $from, int $to): void
    {
        if (! isset($this->steps[$from], $this->steps[$to]) || $from === $to) {
            return;
        }
        $item = $this->steps[$from];
        array_splice($this->steps, $from, 1);
        array_splice($this->steps, $to, 0, [$item]);
        $this->steps = array_values($this->steps);
    }

    public function addEmployee(int $index, int $userId): void
    {
        if (! isset($this->steps[$index])) {
            return;
        }
        $ids = array_map('intval', $this->steps[$index]['user_ids'] ?? []);
        if (! in_array($userId, $ids, true)) {
            $ids[] = $userId;
        }
        $this->steps[$index]['approver_type'] = 'any_of_users';
        $this->steps[$index]['user_ids'] = $ids;
        $this->steps[$index]['user_id'] = null;
    }

    public function chooseEmployee(int $index, int $userId): void
    {
        if (! isset($this->steps[$index])) {
            return;
        }
        $this->steps[$index]['approver_type'] = 'user';
        $this->steps[$index]['user_id'] = $userId;
        $this->steps[$index]['user_ids'] = [];
    }

    /**
     * إزالة موظف من رقائق الخطوة. Time: O(k) | Space: O(k)
     */
    public function removeEmployee(int $index, int $userId): void
    {
        if (! isset($this->steps[$index])) {
            return;
        }
        if (($this->steps[$index]['approver_type'] ?? '') === 'user') {
            if ((int) ($this->steps[$index]['user_id'] ?? 0) === $userId) {
                $this->steps[$index]['user_id'] = null;
            }

            return;
        }
        $this->steps[$index]['user_ids'] = array_values(array_filter(
            array_map('intval', $this->steps[$index]['user_ids'] ?? []),
            fn (int $id) => $id !== $userId,
        ));
    }

    /**
     * يحفظ الحد رقماً بعد إزالة فواصل العرض. Time: O(1) | Space: O(1)
     */
    public function setThreshold(int $index, string $raw): void
    {
        if (! isset($this->steps[$index])) {
            return;
        }
        $this->steps[$index]['condition_value'] = $this->numericText($raw);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.approval-chains.manage') || auth()->user()->can('settings.manage'), 403);

        $before = $this->snapshot($this->selectedType);
        $chain = ApprovalChain::query()->updateOrCreate(
            ['request_type' => $this->selectedType],
            ['is_active' => true, 'updated_by' => auth()->id()],
        );
        $chain->steps()->delete();
        foreach (array_values($this->steps) as $position => $step) {
            $operator = $step['condition_operator'] ?? '';
            $chain->steps()->create([
                'position' => $position + 1,
                'approver_type' => $step['approver_type'] ?? 'direct_manager',
                'user_id' => ($step['approver_type'] ?? '') === 'user' ? ($step['user_id'] ?: null) : null,
                'user_ids' => ($step['approver_type'] ?? '') === 'any_of_users' ? array_values(array_map('intval', $step['user_ids'] ?? [])) : null,
                'condition_operator' => $operator !== '' ? $operator : null,
                'condition_value' => $operator !== '' && ($step['condition_value'] ?? '') !== '' ? $this->numericText((string) $step['condition_value']) : null,
                'on_unresolved' => ($step['on_unresolved'] ?? 'skip') === 'block' ? 'block' : 'skip',
                'label_ar' => $step['label_ar'] ?: null,
            ]);
        }

        app(AuditLogService::class)->record('approval_chain.updated', $chain, [
            'before' => $before,
            'after' => $this->snapshot($this->selectedType),
        ]);

        $this->loadChain();
        $this->dispatch('toast', type: 'success', message: 'حُفظت السلسلة');
    }

    public function render(): View
    {
        $chains = ApprovalChain::query()->with('steps')->get()->keyBy('request_type');
        $names = User::query()->orderBy('name')->get(['id', 'name']);
        $filtered = $this->picker === ''
            ? $names
            : $names->filter(fn (User $user) => str_contains($user->name, $this->picker))->values();

        return view('livewire.settings.approval-chains-index', [
            'typeLabels' => ApprovalChain::TYPE_LABELS,
            'chains' => $chains,
            'employees' => $filtered,
            'allEmployees' => $names,
            'previewLine' => $this->previewLine($names),
            'unit' => $this->unit(),
            'valueLabel' => $this->valueLabel(),
        ])->layout('layouts.app', ['title' => 'سلاسل الطلبات']);
    }

    private function loadChain(): void
    {
        $chain = ApprovalChain::query()->where('request_type', $this->selectedType)->first();
        $this->steps = $chain
            ? $chain->steps->map(fn (ApprovalChainStep $step) => [
                'approver_type' => $step->approver_type,
                'user_id' => $step->user_id,
                'user_ids' => array_map('intval', $step->user_ids ?? []),
                'condition_operator' => $step->condition_operator ?? '',
                'condition_value' => $step->condition_value !== null ? (string) $step->condition_value : '',
                'on_unresolved' => $step->on_unresolved ?: 'skip',
                'label_ar' => $step->label_ar ?? '',
            ])->all()
            : [];

        $this->previewRequesterId = auth()->id();
        $threshold = null;
        foreach ($this->steps as $step) {
            $raw = (string) ($step['condition_value'] ?? '');
            if ($raw !== '') {
                $threshold = (float) $raw;
                break;
            }
        }
        $this->previewValue = (string) ($threshold === null ? 0 : $threshold + 1);
    }

    /**
     * Time: O(n) | Space: O(1)
     */
    private function numericText(string $raw): string
    {
        $clean = str_replace([',', '٬', ' ', '٫'], ['', '', '', '.'], trim($raw));
        if ($clean === '' || ! is_numeric($clean)) {
            return '';
        }

        return (string) (0 + $clean);
    }

    /** @return array<string, mixed> */
    private function blankStep(): array
    {
        return [
            'approver_type' => 'direct_manager',
            'user_id' => null,
            'user_ids' => [],
            'condition_operator' => '',
            'condition_value' => '',
            'on_unresolved' => 'skip',
            'label_ar' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(string $type): array
    {
        $chain = ApprovalChain::query()->with('steps')->where('request_type', $type)->first();
        if (! $chain) {
            return ['steps' => []];
        }

        return [
            'steps' => $chain->steps->map(fn (ApprovalChainStep $step) => $step->only([
                'position', 'approver_type', 'user_id', 'user_ids', 'condition_operator', 'condition_value', 'on_unresolved', 'label_ar',
            ]))->all(),
        ];
    }

    /** @param \Illuminate\Support\Collection<int, User> $names */
    private function previewLine($names): string
    {
        $byId = $names->keyBy('id');
        $requester = $this->previewRequesterId ? $byId->get($this->previewRequesterId) : null;
        $value = (float) $this->previewValue;
        $parts = [];
        foreach ($this->steps as $step) {
            if (! $this->stepMatches($step, $value)) {
                continue;
            }
            $parts[] = $this->approverPhrase($step, $requester, $byId);
        }
        $who = $requester?->name ?? 'مقدّم الطلب';
        $kind = ApprovalChain::TYPE_LABELS[$this->selectedType] ?? 'الطلب';
        $amount = $this->selectedType === 'delegation'
            ? ''
            : ' بقيمة '.number_format($value, 0).' '.$this->unit();
        $path = $parts === [] ? 'لا توجد خطوة تنطبق' : implode(' ← ', $parts);

        return 'طلب '.$kind.$amount.' من '.$who.' يمر على: '.$path;
    }

    /** @param array<string, mixed> $step */
    private function stepMatches(array $step, float $value): bool
    {
        $operator = $step['condition_operator'] ?? '';
        if ($operator === '') {
            return true;
        }
        $threshold = (float) ($step['condition_value'] ?? 0);

        return match ($operator) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  \Illuminate\Support\Collection<int, User>  $byId
     */
    private function approverPhrase(array $step, ?User $requester, $byId): string
    {
        $type = $step['approver_type'] ?? 'direct_manager';
        if ($type === 'direct_manager') {
            $manager = $requester?->effectiveManager();

            if ($manager) {
                return $this->withDelegation($manager);
            }
            $name = $requester?->name ?? 'مقدّم الطلب';

            return 'لا يوجد مدير مباشر لـ '.$name.' — ستُتخطى الخطوة';
        }
        if ($type === 'department_head') {
            return 'رئيس القسم';
        }
        if ($type === 'user') {
            $user = $byId->get((int) ($step['user_id'] ?? 0));

            return $user ? $this->withDelegation($user) : 'موظف غير محدد';
        }
        $labels = [];
        foreach ($step['user_ids'] ?? [] as $id) {
            $user = $byId->get((int) $id);
            if ($user) {
                $labels[] = $this->withDelegation($user);
            }
        }

        return $labels === [] ? 'خطوة بلا معتمد' : implode(' أو ', $labels);
    }

    private function withDelegation(User $user): string
    {
        $active = Delegation::query()
            ->where('delegator_id', $user->id)
            ->where('status', Delegation::STATUS_ACTIVE)
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->with('delegate:id,name')
            ->first();
        if ($active?->delegate) {
            return $active->delegate->name.' (إنابة عن '.$user->name.')';
        }

        return $user->name;
    }

    private function unit(): string
    {
        return match ($this->selectedType) {
            'leave' => 'يوم',
            'overtime', 'excuse' => 'ساعة',
            'delegation' => '',
            default => 'ريال',
        };
    }

    private function valueLabel(): string
    {
        return match ($this->selectedType) {
            'leave' => 'الأيام',
            'overtime', 'excuse' => 'الساعات',
            'delegation' => '',
            default => 'المبلغ',
        };
    }
}
