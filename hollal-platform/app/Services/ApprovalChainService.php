<?php

namespace App\Services;

use App\Models\ApprovalRule;
use App\Services\ExpenseApprovalService;

/**
 * محرك سلسلة الاعتماد حسب نوع العملية والمبلغ.
 * Time: O(rules) | Space: O(steps)
 */
class ApprovalChainService
{
    /**
     * أرجع خطوات الاعتماد (أسماء المراحل المستخدمة في ExpenseApprovalService).
     *
     * @return list<string>
     */
    public function stepsFor(string $type, float $amount): array
    {
        $rule = ApprovalRule::query()
            ->where('transaction_type', $type)
            ->where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->orderByDesc('min_amount')
            // عند تساوي الحد الأدنى فضّل النطاق الأضيق قبل قاعدة catch-all
            ->orderByRaw('CASE WHEN max_amount IS NULL THEN 1 ELSE 0 END')
            ->orderBy('max_amount')
            ->first();

        if (! $rule) {
            return [];
        }

        $steps = [];
        foreach ($rule->approval_steps ?? [] as $step) {
            $token = $this->tokenForStep(is_array($step) ? $step : ['role' => (string) $step]);
            if ($token !== null) {
                $steps[] = $token;
            }
        }

        return array_values(array_unique($steps));
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function tokenForStep(array $step): ?string
    {
        $type = $step['type'] ?? null;
        if ($type === null) {
            $role = (string) ($step['role'] ?? '');

            return match ($role) {
                'department_manager' => ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER,
                'finance', 'finance_manager' => ExpenseApprovalService::STAGE_FINANCE,
                'executive', 'executive_director' => ExpenseApprovalService::STAGE_EXECUTIVE,
                '' => null,
                default => 'role:'.$role,
            };
        }

        return match ($type) {
            'direct_manager', 'department_head' => ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER,
            'user' => 'user:'.(int) ($step['user_id'] ?? 0),
            'any_of_users' => 'users:'.implode(',', array_map('intval', $step['user_ids'] ?? [])),
            'role' => match ((string) ($step['role'] ?? '')) {
                'Finance', 'finance', 'finance_manager' => ExpenseApprovalService::STAGE_FINANCE,
                'Executive Manager', 'executive', 'executive_director' => ExpenseApprovalService::STAGE_EXECUTIVE,
                '' => null,
                default => 'role:'.(string) $step['role'],
            },
            default => null,
        };
    }
}
