<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * HR-8 — renew extends the same contract row and appends renewal_history.
 * Spec-01 §3 asked for a new row; the round-1 order overrides (logged).
 * Time: O(1) | Space: O(h) history length.
 */
class ContractService
{
    public function renew(Contract $contract, string $newEndDate, User $actor): Contract
    {
        $newEnd = Carbon::parse($newEndDate)->startOfDay();
        $currentEnd = $contract->end_date->copy()->startOfDay();

        if ($newEnd->lte($currentEnd)) {
            throw new \InvalidArgumentException('تاريخ التجديد يجب أن يكون بعد نهاية العقد الحالية.');
        }

        $history = $contract->renewal_history ?? [];
        $history[] = [
            'previous_end' => $currentEnd->toDateString(),
            'new_end' => $newEnd->toDateString(),
            'renewed_at' => now()->toIso8601String(),
            'renewed_by' => $actor->id,
        ];

        $contract->update([
            'end_date' => $newEnd,
            'status' => 'active',
            'renewal_history' => $history,
        ]);

        return $contract;
    }
}
