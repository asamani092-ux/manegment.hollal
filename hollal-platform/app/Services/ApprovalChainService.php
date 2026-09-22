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
            ->first();

        if (! $rule) {
            return [];
        }

        $steps = [];
        foreach ($rule->approval_steps ?? [] as $step) {
            $role = is_array($step) ? (string) ($step['role'] ?? '') : (string) $step;
            $mapped = $this->mapRoleToStage($role);
            if ($mapped !== null) {
                $steps[] = $mapped;
            }
        }

        return array_values(array_unique($steps));
    }

    private function mapRoleToStage(string $role): ?string
    {
        return match ($role) {
            'department_manager', ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER => ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER,
            'finance_manager', 'finance', ExpenseApprovalService::STAGE_FINANCE => ExpenseApprovalService::STAGE_FINANCE,
            'executive_director', 'executive', ExpenseApprovalService::STAGE_EXECUTIVE => ExpenseApprovalService::STAGE_EXECUTIVE,
            default => null,
        };
    }
}
