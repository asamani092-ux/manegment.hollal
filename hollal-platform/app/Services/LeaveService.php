<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveDecision;
use App\Notifications\LeaveRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HR leave cycle: submit → manager notify → approve/reject → balance deduct.
 * Time: O(1) per action | Space: O(1).
 */
class LeaveService
{
    public function submit(
        User $employee,
        string $type,
        Carbon|string $from,
        Carbon|string $to,
        ?string $reason = null,
    ): LeaveRequest {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->startOfDay();

        if ($toDate->lt($fromDate)) {
            throw new \InvalidArgumentException('تاريخ النهاية يجب أن يكون بعد البداية أو مساويًا له.');
        }

        if (! in_array($type, [LeaveRequest::TYPE_ANNUAL, LeaveRequest::TYPE_SICK, LeaveRequest::TYPE_EXCEPTIONAL], true)) {
            throw new \InvalidArgumentException('نوع الإجازة غير معتمد.');
        }

        $days = (int) $fromDate->diffInDays($toDate) + 1;

        if ($type === LeaveRequest::TYPE_ANNUAL) {
            $balance = (int) ($employee->profile?->annual_leave_balance ?? 0);
            if ($days > $balance) {
                throw new \RuntimeException('الرصيد السنوي غير كافٍ (المتاح: '.$balance.').');
            }
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'days_count' => $days,
            'reason' => $reason,
            'status' => LeaveRequest::STATUS_SUBMITTED,
        ]);

        $manager = $employee->manager;
        if ($manager) {
            $manager->notify(new LeaveRequested($leave));
        }

        return $leave;
    }

    public function approve(LeaveRequest $leave, User $approver): LeaveRequest
    {
        if (! $leave->isSubmitted()) {
            throw new \RuntimeException('لا يمكن اعتماد طلب ليس بحالة مقدم.');
        }

        return DB::transaction(function () use ($leave, $approver) {
            if ($leave->type === LeaveRequest::TYPE_ANNUAL) {
                $profile = EmployeeProfile::query()->firstOrCreate(
                    ['user_id' => $leave->employee_id],
                    ['annual_leave_balance' => 21]
                );

                if ($leave->days_count > (int) $profile->annual_leave_balance) {
                    throw new \RuntimeException('الرصيد السنوي غير كافٍ للاعتماد.');
                }

                $profile->decrement('annual_leave_balance', $leave->days_count);
            }

            $leave->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'approver_id' => $approver->id,
                'approved_at' => now(),
            ]);

            $leave->employee?->notify(new LeaveDecision($leave->fresh()));

            return $leave->fresh();
        });
    }

    public function reject(LeaveRequest $leave, User $approver): LeaveRequest
    {
        if (! $leave->isSubmitted()) {
            throw new \RuntimeException('لا يمكن رفض طلب ليس بحالة مقدم.');
        }

        $leave->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approver_id' => $approver->id,
            'approved_at' => now(),
        ]);

        $leave->employee?->notify(new LeaveDecision($leave->fresh()));

        return $leave->fresh();
    }
}
