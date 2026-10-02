<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\ReferenceItem;
use App\Models\ReferenceList;
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

        $leaveType = $this->resolveLeaveType($type);
        if (! $leaveType) {
            throw new \InvalidArgumentException('نوع الإجازة غير معتمد.');
        }

        $days = (int) $fromDate->diffInDays($toDate) + 1;

        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_APPROVED])
            ->where('from_date', '<=', $toDate)
            ->where('to_date', '>=', $fromDate)
            ->exists();

        if ($overlaps) {
            throw new \RuntimeException('توجد إجازة أخرى متداخلة مع هذه الفترة.');
        }

        if ($leaveType->code === 'annual' || $leaveType->name_ar === 'سنوية') {
            $balance = (int) ($employee->profile?->annual_leave_balance ?? 0);

            // الطلبات المقدمة تحجز رصيدها حتى لا يتجاوزه الموظف بطلبات متتالية.
            $reserved = (int) LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('type', LeaveRequest::TYPE_ANNUAL)
                ->where('status', LeaveRequest::STATUS_SUBMITTED)
                ->sum('days_count');

            if ($days > ($balance - $reserved)) {
                throw new \RuntimeException('الرصيد السنوي غير كافٍ (المتاح: '.max(0, $balance - $reserved).').');
            }
        }

        $payload = [
            'employee_id' => $employee->id,
            'type' => $leaveType->name_ar,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'days_count' => $days,
            'reason' => $reason,
            'status' => LeaveRequest::STATUS_SUBMITTED,
        ];
        if ($this->leaveHasReferenceColumn()) {
            $payload['reference_item_id'] = $leaveType->id;
        }

        $leave = LeaveRequest::create($payload);

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
            // قفل الطلب يمنع اعتمادين متزامنين يخصمان الرصيد مرتين.
            $locked = LeaveRequest::query()->lockForUpdate()->find($leave->id);
            if (! $locked || ! $locked->isSubmitted()) {
                throw new \RuntimeException('لا يمكن اعتماد طلب ليس بحالة مقدم.');
            }

            if ($leave->type === 'سنوية' || $leave->type === 'annual') {
                EmployeeProfile::query()->firstOrCreate(
                    ['user_id' => $leave->employee_id],
                    ['annual_leave_balance' => 21]
                );

                $profile = EmployeeProfile::query()
                    ->where('user_id', $leave->employee_id)
                    ->lockForUpdate()
                    ->first();

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

    private function resolveLeaveType(string $type): ?ReferenceItem
    {
        if (! ReferenceList::query()->where('key', 'leave_types')->exists()) {
            return null;
        }

        $service = app(ReferenceListService::class);
        $byCode = $service->item('leave_types', $type);
        if ($byCode) {
            return $byCode;
        }

        return $service->activeItems('leave_types')->first(function (ReferenceItem $item) use ($type) {
            return $item->name_ar === $type || $item->code === $type;
        });
    }

    private function leaveHasReferenceColumn(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('leave_requests', 'reference_item_id');
    }
}
