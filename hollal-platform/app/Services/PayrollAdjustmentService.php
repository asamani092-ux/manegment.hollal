<?php

namespace App\Services;

use App\Models\AttendanceCycle;
use App\Models\LeavePayImpact;
use App\Models\LeaveRequest;
use App\Models\PayrollAdjustment;
use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Proposed payroll lines.
 * Time: O(n) lines per source | Space: O(1) per line.
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
        $computed = $line->computed_amount;
        $line->update([
            'amount' => $amount,
            'status' => 'modified',
            'reason' => $reason,
            'decided_by' => auth()->id(),
        ]);
        app(AuditLogService::class)->record('payroll_adjustment.modified', $line, [
            'computed_amount' => $computed,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        return $line->fresh();
    }

    public function approve(PayrollAdjustment $line, User $actor, ?string $reason = null): PayrollAdjustment
    {
        $line->update([
            'status' => 'approved',
            'reason' => $reason ?: $line->reason,
            'decided_by' => $actor->id,
        ]);

        return $line->fresh();
    }

    public function cancel(PayrollAdjustment $line, User $actor, string $reason): PayrollAdjustment
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب الإلغاء مطلوب');
        }
        $line->update([
            'status' => 'cancelled',
            'reason' => $reason,
            'decided_by' => $actor->id,
        ]);
        app(AuditLogService::class)->record('payroll_adjustment.cancelled', $line, [
            'computed_amount' => $line->computed_amount,
            'amount' => $line->amount,
            'reason' => $reason,
        ], $actor);

        return $line->fresh();
    }

    public function defer(PayrollAdjustment $line, User $actor, string $reason): PayrollAdjustment
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب التأجيل مطلوب');
        }
        $next = Carbon::createFromFormat('Y-m', $line->month)->addMonth()->format('Y-m');
        $line->update([
            'status' => 'deferred',
            'reason' => $reason,
            'deferred_to' => $next,
            'decided_by' => $actor->id,
        ]);
        $this->propose(
            $line->employee_id,
            $next,
            $line->reference_item_id,
            (float) $line->amount,
            (float) $line->computed_amount,
            'payroll_adjustment',
            $line->id,
        );

        return $line->fresh();
    }

    public function manualEarning(User $employee, ReferenceItem $item, string $month, float $amount, string $reference): PayrollAdjustment
    {
        if (($item->attributes['kind'] ?? '') !== 'earning') {
            throw new \InvalidArgumentException('البند يجب أن يكون إضافة من القائمة');
        }
        if (trim($reference) === '') {
            throw new \InvalidArgumentException('المرجع إلزامي');
        }

        return $this->propose(
            $employee->id,
            $month,
            $item->id,
            round($amount, 2),
            round($amount, 2),
            'manual',
            $employee->id,
            $reference,
        );
    }

    public function proposeFromLeave(LeaveRequest $leave): void
    {
        if (! Schema::hasTable('payroll_adjustments') || ! Schema::hasTable('leave_pay_impacts')) {
            return;
        }
        $impacts = LeavePayImpact::query()->where('leave_request_id', $leave->id)->get();
        $salary = $this->baseSalary($leave->employee_id);
        $day = app(AttendanceDeductionService::class)->dayValue($salary);
        foreach ($impacts as $impact) {
            if ((float) $impact->unpaid_days > 0) {
                $item = $this->itemBySource('leave_unpaid');
                if ($item) {
                    $amount = round(-1 * (float) $impact->unpaid_days * $day, 2);
                    if ($amount != 0.0) {
                        $this->propose($leave->employee_id, $impact->month, $item->id, $amount, $amount, LeavePayImpact::class, $impact->id);
                    }
                }
            }
            if ((float) $impact->partial_days > 0) {
                $item = $this->itemBySource('leave_partial');
                if ($item) {
                    $share = (100 - (float) $impact->partial_pct) / 100;
                    $amount = round(-1 * (float) $impact->partial_days * $day * $share, 2);
                    if ($amount != 0.0) {
                        $this->propose($leave->employee_id, $impact->month, $item->id, $amount, $amount, LeavePayImpact::class, $impact->id);
                    }
                }
            }
        }
    }

    public function proposeFromViolation(Violation $violation): void
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return;
        }
        $penalty = $violation->decided_penalty ?? [];
        $type = $penalty['type'] ?? '';
        if (! in_array($type, ['deduct_days', 'deduct_pct_daily'], true)) {
            return;
        }
        $item = $this->itemBySource('violation');
        if (! $item) {
            return;
        }
        $day = app(AttendanceDeductionService::class)->dayValue($this->baseSalary($violation->employee_id));
        $value = (float) ($penalty['value'] ?? 0);
        $amount = $type === 'deduct_days'
            ? round(-1 * $value * $day, 2)
            : round(-1 * $day * ($value / 100), 2);
        if ($amount == 0.0) {
            return;
        }
        $month = Carbon::parse($violation->occurred_on)->format('Y-m');
        $this->propose($violation->employee_id, $month, $item->id, $amount, $amount, Violation::class, $violation->id);
    }

    public function proposeFromCycle(AttendanceCycle $cycle): void
    {
        if (! Schema::hasTable('payroll_adjustments') || ! $this->listExists('payroll_adjustment_items')) {
            return;
        }
        $absenceItem = $this->itemBySource('absence');
        $overtimeItem = $this->itemBySource('overtime');
        $cycle->loadMissing('days');
        $absenceDays = [];
        $overtimeHours = [];
        foreach ($cycle->days as $day) {
            if ($absenceItem && in_array((string) $day->status, ['غياب', 'absent', 'غياب غير مبرر'], true)) {
                $absenceDays[$day->employee_id] = ($absenceDays[$day->employee_id] ?? 0) + 1;
            }
            if ($overtimeItem && (float) $day->overtime_hours > 0) {
                $overtimeHours[$day->employee_id] = ($overtimeHours[$day->employee_id] ?? 0) + (float) $day->overtime_hours;
            }
        }
        foreach ($absenceDays as $employeeId => $days) {
            $dayValue = app(AttendanceDeductionService::class)->dayValue($this->baseSalary((int) $employeeId));
            $multiplier = (float) \App\Support\Setting::get('attendance.absence_multiplier', 1.5);
            $amount = round(-1 * $days * $dayValue * $multiplier, 2);
            $this->propose((int) $employeeId, $cycle->month, $absenceItem->id, $amount, $amount, AttendanceCycle::class, $cycle->id);
        }
        foreach ($overtimeHours as $employeeId => $hours) {
            $hour = app(AttendanceDeductionService::class)->hourValue($this->baseSalary((int) $employeeId));
            $amount = app(AttendanceCycleService::class)->overtimeAmount($hour, (float) $hours, true);
            if ($amount <= 0) {
                continue;
            }
            $this->propose((int) $employeeId, $cycle->month, $overtimeItem->id, $amount, $amount, AttendanceCycle::class, $cycle->id);
        }
    }

    /**
     * @return array{by_item: array<string, float>, by_source: array<string, float>}
     */
    public function totals(string $fromMonth, string $toMonth): array
    {
        $rows = PayrollAdjustment::query()
            ->with('referenceItem:id,name_ar')
            ->whereBetween('month', [$fromMonth, $toMonth])
            ->whereNotIn('status', ['cancelled', 'deferred'])
            ->get();
        $byItem = [];
        $bySource = [];
        foreach ($rows as $row) {
            $label = $row->referenceItem?->name_ar ?? 'بدون بند';
            $source = (string) ($row->source_type ?? 'manual');
            $byItem[$label] = round(($byItem[$label] ?? 0) + (float) $row->amount, 2);
            $bySource[$source] = round(($bySource[$source] ?? 0) + (float) $row->amount, 2);
        }

        return ['by_item' => $byItem, 'by_source' => $bySource];
    }

    private function propose(
        int $employeeId,
        string $month,
        ?int $itemId,
        float $amount,
        float $computed,
        ?string $sourceType,
        ?int $sourceId,
        ?string $reason = null,
    ): PayrollAdjustment {
        $existing = PayrollAdjustment::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('reference_item_id', $itemId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
        if ($existing) {
            return $existing;
        }

        return PayrollAdjustment::query()->create([
            'employee_id' => $employeeId,
            'month' => $month,
            'reference_item_id' => $itemId,
            'amount' => $amount,
            'computed_amount' => $computed,
            'status' => 'proposed',
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    private function itemBySource(string $source): ?ReferenceItem
    {
        if (! $this->listExists('payroll_adjustment_items')) {
            return null;
        }

        return app(ReferenceListService::class)->activeItems('payroll_adjustment_items')
            ->first(fn (ReferenceItem $item) => ($item->attributes['auto_source'] ?? '') === $source);
    }

    private function listExists(string $key): bool
    {
        return Schema::hasTable('reference_lists')
            && ReferenceList::query()->where('key', $key)->exists();
    }

    private function baseSalary(int $employeeId): float
    {
        return (float) SalaryComponent::query()
            ->where('employee_id', $employeeId)
            ->where('type', SalaryComponent::TYPE_BASE)
            ->where('is_active', true)
            ->sum('amount');
    }
}
