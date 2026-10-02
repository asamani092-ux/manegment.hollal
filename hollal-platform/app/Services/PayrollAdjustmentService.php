<?php

namespace App\Services;

use App\Models\PayrollAdjustment;

/**
 * Proposed payroll lines. Time: O(1) per line | Space: O(1).
 */
class PayrollAdjustmentService
{
    public function assertNoProposed(string $month): void
    {
        $exists = PayrollAdjustment::query()
            ->where('month', $month)
            ->where('status', 'proposed')
            ->exists();
        if ($exists) {
            throw new \RuntimeException('لا يُعتمد المسير وفي الشهر تسويات مقترحة');
        }
    }

    public function modify(PayrollAdjustment $line, float $amount, string $reason): PayrollAdjustment
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب التعديل مطلوب');
        }
        $line->update([
            'amount' => $amount,
            'status' => 'modified',
            'reason' => $reason,
        ]);

        return $line->fresh();
    }
}
