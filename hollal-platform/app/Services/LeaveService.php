<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Delegation;
use App\Models\EmployeeProfile;
use App\Models\LeavePayImpact;
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
                ->whereIn('type', ['سنوية', 'annual'])
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

        $manager = $employee->effectiveManager();
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

        $done = app(\App\Services\Approval\ApprovalEngine::class)->gate(
            'leave',
            $leave->id,
            $leave->employee_id,
            (float) $leave->days_count,
            $approver,
        );
        if ($done === false) {
            return $leave->fresh();
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

            $fresh = $leave->fresh(['referenceItem']);
            $this->writeAttendance($fresh, $approver);
            app(LeaveBalanceService::class)->recordPayImpacts($fresh);
            $leave->employee?->notify(new LeaveDecision($fresh));

            return $fresh;
        });
    }

    public function extend(LeaveRequest $leave, Carbon|string $newTo): LeaveRequest
    {
        $newToDate = Carbon::parse($newTo)->startOfDay();
        if ($newToDate->lte(Carbon::parse($leave->to_date))) {
            throw new \InvalidArgumentException('تاريخ التمديد يجب أن يكون بعد نهاية الإجازة.');
        }

        return LeaveRequest::create([
            'employee_id' => $leave->employee_id,
            'type' => $leave->type,
            'reference_item_id' => $leave->reference_item_id,
            'parent_leave_id' => $leave->id,
            'from_date' => Carbon::parse($leave->to_date)->addDay()->toDateString(),
            'to_date' => $newToDate->toDateString(),
            'days_count' => (int) Carbon::parse($leave->to_date)->addDay()->diffInDays($newToDate) + 1,
            'reason' => 'تمديد',
            'status' => LeaveRequest::STATUS_SUBMITTED,
        ]);
    }

    public function cut(LeaveRequest $leave, Carbon|string $returnDate): LeaveRequest
    {
        $return = Carbon::parse($returnDate)->startOfDay();
        $originalEnd = Carbon::parse($leave->to_date)->startOfDay();
        $unused = $return->lte($originalEnd) ? ((int) $return->diffInDays($originalEnd) + 1) : 0;
        $leave->update([
            'cut_on' => $return->toDateString(),
            'to_date' => $return->copy()->subDay()->toDateString(),
            'days_count' => max(0, (int) $leave->days_count - $unused),
        ]);

        if ($unused > 0 && ($leave->type === 'سنوية' || $leave->type === 'annual')) {
            EmployeeProfile::query()->where('user_id', $leave->employee_id)->increment('annual_leave_balance', $unused);
        }

        Delegation::query()
            ->where('source_type', $leave->getMorphClass())
            ->where('source_id', $leave->id)
            ->where('status', Delegation::STATUS_ACTIVE)
            ->update(['status' => Delegation::STATUS_ENDED, 'ended_at' => now(), 'ends_on' => $return->toDateString()]);

        LeavePayImpact::query()->where('leave_request_id', $leave->id)->delete();
        app(LeaveBalanceService::class)->recordPayImpacts($leave->fresh(['referenceItem']));

        return $leave->fresh();
    }

    public function acceptExtension(LeaveRequest $child): LeaveRequest
    {
        $child->update(['status' => LeaveRequest::STATUS_APPROVED, 'approved_at' => now()]);
        $parent = $child->parent_leave_id ? LeaveRequest::find($child->parent_leave_id) : null;
        if ($parent) {
            Delegation::query()
                ->where('source_type', $parent->getMorphClass())
                ->where('source_id', $parent->id)
                ->whereIn('status', [Delegation::STATUS_ACTIVE, Delegation::STATUS_SCHEDULED])
                ->update(['ends_on' => $child->to_date]);
            $parent->update(['to_date' => $child->to_date]);
        }

        return $child->fresh();
    }

    private function writeAttendance(LeaveRequest $leave, User $approver): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('attendance_records')) {
            return;
        }
        $cursor = Carbon::parse($leave->from_date)->startOfDay();
        $end = Carbon::parse($leave->cut_on ?? $leave->to_date)->startOfDay();
        while ($cursor->lte($end)) {
            AttendanceRecord::query()->updateOrCreate(
                ['employee_id' => $leave->employee_id, 'date' => $cursor->toDateString()],
                ['type' => 'إجازة', 'declared_by' => $approver->id, 'source' => 'leave']
            );
            $cursor->addDay();
        }
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
