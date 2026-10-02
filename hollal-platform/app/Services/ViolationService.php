<?php

namespace App\Services;

use App\Models\Violation;
use App\Support\Setting;
use Illuminate\Support\Carbon;

/**
 * Violation window and statement gate. Time: O(n) history | Space: O(1).
 */
class ViolationService
{
    public function occurrenceIndex(int $employeeId, int $referenceItemId, Carbon $on): int
    {
        $window = (int) Setting::get('hr.violations.recurrence_window_days', 180);
        $from = $on->copy()->subDays($window);

        $count = Violation::query()
            ->where('employee_id', $employeeId)
            ->where('reference_item_id', $referenceItemId)
            ->whereIn('status', ['applied', 'reduced'])
            ->whereDate('occurred_on', '>=', $from)
            ->whereDate('occurred_on', '<=', $on)
            ->count();

        return $count + 1;
    }

    public function assertCanDecide(Violation $violation): void
    {
        if ($violation->status === 'awaiting_statement' && $violation->statement_submitted_at === null) {
            $deadline = $violation->statement_deadline_on;
            if (! $deadline || Carbon::parse($deadline)->gte(now()->startOfDay())) {
                throw new \RuntimeException('لا يصدر القرار قبل الإفادة أو انتهاء مهلتها');
            }
        }
    }
}
