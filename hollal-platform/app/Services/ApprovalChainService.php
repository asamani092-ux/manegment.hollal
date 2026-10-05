<?php

namespace App\Services;

use App\Models\ApprovalChain;
use App\Models\ApprovalChainStep;
use App\Services\ExpenseApprovalService;

/**
 * يقرأ السلسلة الواحدة لنوع الطلب ويطبّق شرط القيمة.
 * Time: O(steps) | Space: O(steps)
 */
class ApprovalChainService
{
    /**
     * أرجع خطوات الاعتماد (رموز المراحل المستخدمة في محرك الاعتماد).
     *
     * @return list<string>
     */
    public function stepsFor(string $type, float $amount): array
    {
        $chain = ApprovalChain::query()
            ->where('request_type', $type)
            ->where('is_active', true)
            ->first();

        if (! $chain) {
            return [];
        }

        $tokens = [];
        foreach ($chain->steps()->orderBy('position')->get() as $step) {
            if (! $this->matches($step, $amount)) {
                continue;
            }
            $token = $this->token($step);
            if ($token === null) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    private function matches(ApprovalChainStep $step, float $amount): bool
    {
        $operator = $step->condition_operator;
        if ($operator === null || $operator === '') {
            return true;
        }
        $threshold = (float) $step->condition_value;

        return match ($operator) {
            'gt' => $amount > $threshold,
            'gte' => $amount >= $threshold,
            'lt' => $amount < $threshold,
            default => true,
        };
    }

    private function token(ApprovalChainStep $step): ?string
    {
        return match ($step->approver_type) {
            'direct_manager' => ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER,
            'department_head' => 'department_head',
            'user' => $step->user_id ? 'user:'.$step->user_id : ($step->on_unresolved === 'block' ? 'user:0' : null),
            'any_of_users' => $this->usersToken($step),
            default => null,
        };
    }

    private function usersToken(ApprovalChainStep $step): ?string
    {
        $ids = array_values(array_filter(array_map('intval', $step->user_ids ?? [])));
        if ($ids === []) {
            return $step->on_unresolved === 'block' ? 'users:' : null;
        }

        return 'users:'.implode(',', $ids);
    }
}
