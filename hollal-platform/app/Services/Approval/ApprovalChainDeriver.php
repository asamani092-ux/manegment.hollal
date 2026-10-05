<?php

namespace App\Services\Approval;

use App\Models\ApprovalChain;
use App\Models\ApprovalChainStep;
use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * يحوّل قواعد الشرائح القديمة إلى سلسلة واحدة لكل نوع.
 * Time: O(rules × steps) | Space: O(steps)
 */
class ApprovalChainDeriver
{
    /** @var list<array{type: string, from: string, to: string, people: string}> */
    public array $conversions = [];

    public function syncAll(): void
    {
        if (! Schema::hasTable('approval_chains') || ! Schema::hasTable('approval_rules')) {
            return;
        }

        $this->conversions = [];
        $types = ApprovalRule::query()->distinct()->pluck('transaction_type')
            ->merge(ApprovalChain::TYPES)
            ->unique()
            ->values();

        foreach ($types as $type) {
            $this->syncType((string) $type);
        }
    }

    public function syncType(string $type): void
    {
        if (! Schema::hasTable('approval_chains')) {
            return;
        }

        $rules = ApprovalRule::query()
            ->where('transaction_type', $type)
            ->where('is_active', true)
            ->orderBy('min_amount')
            ->get();

        $banded = $rules->filter(fn (ApprovalRule $rule) => ! $this->isCatchAll($rule))->values();
        $source = $banded->isNotEmpty() ? $banded : $rules->values();

        if ($source->isEmpty()) {
            ApprovalChain::query()->where('request_type', $type)->delete();

            return;
        }

        $order = [];
        $presence = [];
        foreach ($source as $index => $rule) {
            foreach ($rule->approval_steps ?? [] as $step) {
                $normal = $this->normalize(is_array($step) ? $step : ['role' => (string) $step]);
                if ($normal === null) {
                    continue;
                }
                $key = $normal['key'];
                if (! isset($order[$key])) {
                    $order[$key] = $normal;
                    $presence[$key] = [];
                }
                $presence[$key][] = $index;
            }
        }

        $bandCount = $source->count();
        $chain = ApprovalChain::query()->updateOrCreate(
            ['request_type' => $type],
            ['is_active' => true],
        );
        $chain->steps()->delete();

        $position = 1;
        foreach ($order as $key => $normal) {
            $mins = [];
            foreach (array_unique($presence[$key]) as $index) {
                $mins[] = (float) $source[$index]->min_amount;
            }
            $inEveryBand = count(array_unique($presence[$key])) === $bandCount;
            $operator = null;
            $value = null;
            if (! $inEveryBand && $mins !== []) {
                [$operator, $value] = $this->thresholdFromMin(min($mins));
            }

            $row = $this->toRow($normal, $position, $operator, $value);
            $chain->steps()->create($row);
            $position++;
        }
    }

    /**
     * gte بقيمة كسرية 0.01 تصبح gt على العدد الصحيح.
     * Time: O(1) | Space: O(1)
     *
     * @return array{0: string, 1: float|int}
     */
    private function thresholdFromMin(float $min): array
    {
        $scaled = (int) round($min * 100);
        if ($scaled % 100 === 1) {
            return ['gt', intdiv($scaled, 100)];
        }

        return ['gte', $min];
    }

    private function isCatchAll(ApprovalRule $rule): bool
    {
        $min = (float) $rule->min_amount;
        $max = $rule->max_amount;

        return $min <= 0.0 && ($max === null || (float) $max >= 999999999);
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{key: string, kind: string, user_id: ?int, user_ids: list<int>, role: ?string}|null
     */
    private function normalize(array $step): ?array
    {
        $type = $step['type'] ?? null;
        $role = (string) ($step['role'] ?? '');

        if ($type === 'user') {
            $id = (int) ($step['user_id'] ?? 0);

            return ['key' => 'user:'.$id, 'kind' => 'user', 'user_id' => $id ?: null, 'user_ids' => [], 'role' => null];
        }
        if ($type === 'any_of_users') {
            $ids = array_values(array_unique(array_map('intval', $step['user_ids'] ?? [])));
            sort($ids);

            return ['key' => 'users:'.implode(',', $ids), 'kind' => 'any_of_users', 'user_id' => null, 'user_ids' => $ids, 'role' => null];
        }
        if ($type === 'direct_manager' || $role === 'department_manager') {
            return ['key' => 'direct_manager', 'kind' => 'direct_manager', 'user_id' => null, 'user_ids' => [], 'role' => null];
        }
        if ($type === 'department_head') {
            return ['key' => 'department_head', 'kind' => 'department_head', 'user_id' => null, 'user_ids' => [], 'role' => null];
        }

        $roleName = $this->roleName($type === 'role' ? $role : ($role !== '' ? $role : ''));
        if ($roleName === null) {
            return null;
        }

        return ['key' => 'role:'.$roleName, 'kind' => 'role', 'user_id' => null, 'user_ids' => [], 'role' => $roleName];
    }

    private function roleName(string $role): ?string
    {
        if ($role === '') {
            return null;
        }

        return match ($role) {
            'finance', 'finance_manager', 'Finance' => 'Finance',
            'executive', 'executive_director', 'Executive Manager' => 'Executive Manager',
            'department_manager', 'direct_manager' => null,
            default => $role,
        };
    }

    /**
     * @param  array{key: string, kind: string, user_id: ?int, user_ids: list<int>, role: ?string}  $normal
     * @return array<string, mixed>
     */
    private function toRow(array $normal, int $position, ?string $operator, ?float $value): array
    {
        $kind = $normal['kind'];
        $userIds = $normal['user_ids'];
        $label = match ($kind) {
            'direct_manager' => 'المدير المباشر',
            'department_head' => 'رئيس القسم',
            'user' => User::query()->whereKey($normal['user_id'])->value('name') ?: 'موظف محدد',
            default => 'خطوة بلا معتمد',
        };

        if ($kind === 'role' && $normal['role']) {
            try {
                $userIds = User::role($normal['role'])->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            } catch (\Throwable) {
                $userIds = [];
            }
            $names = User::query()->whereIn('id', $userIds)->orderBy('name')->pluck('name')->implode('، ');
            $arabicRole = match ($normal['role']) {
                'Finance' => 'المالية',
                'Executive Manager' => 'الإدارة التنفيذية',
                default => 'دور محدد',
            };
            $label = 'حاملو '.$arabicRole;
            $this->conversions[] = [
                'type' => 'role',
                'from' => $arabicRole,
                'to' => $names !== '' ? $names : 'لا يوجد حامل حاليًا',
                'people' => $names,
            ];
            $kind = 'any_of_users';
        }

        return [
            'position' => $position,
            'approver_type' => $kind,
            'user_id' => $kind === 'user' ? $normal['user_id'] : null,
            'user_ids' => $kind === 'any_of_users' ? array_values($userIds) : null,
            'condition_operator' => $operator,
            'condition_value' => $value,
            'on_unresolved' => ($kind === 'any_of_users' && $userIds === []) ? 'block' : 'skip',
            'label_ar' => $label,
        ];
    }
}
