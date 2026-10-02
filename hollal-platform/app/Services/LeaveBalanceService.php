<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeavePayImpact;
use App\Models\LeaveRequest;
use App\Models\ReferenceItem;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Carbon;

/**
 * Leave entitlement and payroll impact. Time: O(days) per leave | Space: O(months).
 */
class LeaveBalanceService
{
    public function entitlementDays(User $user, ReferenceItem $type, Carbon $on): int
    {
        $attrs = $type->attributes ?? [];
        $base = (int) ($attrs['annual_entitlement_days'] ?? 0);
        $after = (int) ($attrs['entitlement_after_5y_days'] ?? $base);
        $hire = $user->profile?->hire_date;
        if ($hire && $after > 0 && $hire->copy()->addYears(5)->lte($on->copy()->startOfDay())) {
            return $after;
        }

        return $base > 0 ? $base : $after;
    }

    /** Day number is 1-based inside the rolling sick year. */
    public function sickPayPercent(int $dayNumber): int
    {
        if ($dayNumber <= 30) {
            return 100;
        }
        if ($dayNumber <= 90) {
            return 75;
        }

        return 0;
    }

    public function available(LeaveBalance $balance): float
    {
        return round((float) $balance->entitled + (float) $balance->adjusted - (float) $balance->used - (float) $balance->reserved, 2);
    }

    /**
     * @return array<string, int> month Y-m => day count
     */
    public function splitByMonth(Carbon $from, Carbon $to): array
    {
        $counts = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $cursor->addDay();
        }

        return $counts;
    }

    public function recordPayImpacts(LeaveRequest $leave): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('leave_pay_impacts')) {
            return;
        }

        $code = $leave->referenceItem?->code;
        $name = (string) $leave->type;
        $fullPay = in_array($code, ['annual', 'emergency', 'marriage', 'bereavement', 'newborn', 'hajj'], true)
            || in_array($name, ['سنوية', 'طارئة', 'زواج', 'وفاة', 'مولود', 'حج'], true);
        if ($fullPay) {
            return;
        }

        $from = Carbon::parse($leave->from_date);
        $to = Carbon::parse($leave->cut_on ?? $leave->to_date);
        foreach ($this->splitByMonth($from, $to) as $month => $days) {
            $unpaid = ($code === 'exceptional' || $name === 'استثنائية') ? $days : 0;
            $partial = $unpaid > 0 ? 0 : $days;
            $pct = $partial > 0 ? 75 : 0;
            if ($unpaid === 0 && $partial === 0) {
                continue;
            }
            LeavePayImpact::query()->updateOrCreate(
                [
                    'leave_request_id' => $leave->id,
                    'month' => $month,
                    'partial_pct' => $pct,
                ],
                [
                    'unpaid_days' => $unpaid,
                    'partial_days' => $partial,
                ]
            );
        }
    }

    public function carryoverCap(): ?int
    {
        $value = Setting::get('hr.leave.carryover_max_days', null);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
