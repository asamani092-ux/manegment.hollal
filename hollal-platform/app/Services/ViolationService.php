<?php

namespace App\Services;

use App\Events\ViolationApplied;
use App\Models\AttendanceCycle;
use App\Models\EmployeeStatement;
use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Models\User;
use App\Models\Violation;
use App\Notifications\ViolationStatementRequested;
use App\Support\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Violation window, statements, and decisions.
 * Time: O(n) history or cycle days | Space: O(1) per row.
 */
class ViolationService
{
    /** @var array<string, int> */
    private const SEVERITY = [
        'warning' => 1,
        'deduct_pct_daily' => 2,
        'deduct_days' => 3,
        'withhold_raise' => 4,
        'withhold_promotion' => 5,
        'termination_with_award' => 6,
        'termination_without_award' => 7,
    ];

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

    public function suggestFromCycle(AttendanceCycle $cycle): int
    {
        if (! Schema::hasTable('violations') || ! $this->listExists('violations')) {
            return 0;
        }

        $items = app(ReferenceListService::class)->activeItems('violations');
        $absence = $items->first(fn (ReferenceItem $item) => ($item->attributes['auto_detectable'] ?? 'none') === 'absence');
        $late = $items->first(fn (ReferenceItem $item) => ($item->attributes['auto_detectable'] ?? 'none') === 'late');
        if (! $absence && ! $late) {
            return 0;
        }

        $created = 0;
        $cycle->loadMissing('days');
        foreach ($cycle->days as $day) {
            $status = (string) $day->status;
            if ($absence && in_array($status, ['غياب', 'absent', 'غياب غير مبرر'], true)) {
                $created += $this->suggestDay($day->employee_id, $absence, $day->date, 'day:'.$day->id.':absence');
            }
            $threshold = (int) ($late->attributes['late_threshold_minutes'] ?? 0);
            if ($late && (int) $day->chargeable_late_minutes >= $threshold && $threshold > 0) {
                $created += $this->suggestDay($day->employee_id, $late, $day->date, 'day:'.$day->id.':late');
            }
        }

        return $created;
    }

    public function exclude(Violation $violation, string $reason, ?User $actor = null): Violation
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب الاستبعاد مطلوب');
        }
        $violation->update([
            'status' => 'excluded',
            'decision_reason' => $reason,
            'decided_by' => ($actor ?? auth()->user())?->id,
        ]);
        app(AuditLogService::class)->record('violation.excluded', $violation, ['reason' => $reason], $actor);

        return $violation->fresh();
    }

    public function confirm(Violation $violation, User $actor): Violation
    {
        if ($violation->status !== 'suggested') {
            throw new \RuntimeException('التأكيد للمخالفات المقترحة فقط');
        }
        $this->guardDiscovery($violation->discovered_on ?? $violation->occurred_on);
        $index = $this->occurrenceIndex($violation->employee_id, $violation->reference_item_id, Carbon::parse($violation->occurred_on));
        $deadline = $this->addWorkdays(now(), (int) Setting::get('hr.violations.statement_deadline_workdays', 3));
        $violation->update([
            'status' => 'awaiting_statement',
            'occurrence_index' => $index,
            'statement_deadline_on' => $deadline->toDateString(),
            'decided_by' => $actor->id,
        ]);
        $employee = User::query()->find($violation->employee_id);
        $employee?->notify(new ViolationStatementRequested($violation));

        return $violation->fresh();
    }

    public function recordManual(User $employee, ReferenceItem $item, string $facts, Carbon $occurredOn, User $actor, ?Carbon $discoveredOn = null): Violation
    {
        $discovered = $discoveredOn ?? now();
        $this->guardDiscovery($discovered);
        $index = $this->occurrenceIndex($employee->id, $item->id, $occurredOn);
        $deadline = $this->addWorkdays(now(), (int) Setting::get('hr.violations.statement_deadline_workdays', 3));
        $violation = Violation::query()->create([
            'employee_id' => $employee->id,
            'reference_item_id' => $item->id,
            'occurrence_index' => $index,
            'source' => 'manual',
            'occurred_on' => $occurredOn->toDateString(),
            'discovered_on' => $discovered->toDateString(),
            'facts' => $facts,
            'status' => 'awaiting_statement',
            'statement_deadline_on' => $deadline->toDateString(),
            'decided_by' => $actor->id,
        ]);
        $employee->notify(new ViolationStatementRequested($violation));

        return $violation;
    }

    public function submitStatement(Violation $violation, User $employee, string $body): EmployeeStatement
    {
        if ((int) $violation->employee_id !== (int) $employee->id) {
            throw new \RuntimeException('الإفادة لصاحب المخالفة فقط');
        }
        if (trim($body) === '') {
            throw new \InvalidArgumentException('نص الإفادة مطلوب');
        }
        $statement = EmployeeStatement::query()->create([
            'violation_id' => $violation->id,
            'employee_id' => $employee->id,
            'body' => $body,
            'submitted_at' => now(),
        ]);
        $violation->update([
            'statement_submitted_at' => now(),
            'status' => 'pending_decision',
        ]);

        return $statement;
    }

    public function processDeadlines(): int
    {
        $rows = Violation::query()
            ->where('status', 'awaiting_statement')
            ->whereNull('statement_submitted_at')
            ->whereNotNull('statement_deadline_on')
            ->whereDate('statement_deadline_on', '<', now()->toDateString())
            ->get();
        foreach ($rows as $row) {
            $row->update(['status' => 'pending_decision']);
        }

        return $rows->count();
    }

    /**
     * @param  array{type: string, value?: float|int}|null  $lighter
     */
    public function decide(Violation $violation, string $action, User $actor, string $reason, ?array $lighter = null): Violation
    {
        if (! in_array($action, ['apply', 'reduce', 'cancel'], true)) {
            throw new \InvalidArgumentException('قرار غير معروف');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب القرار مطلوب');
        }
        $this->assertCanDecide($violation);
        if ($violation->status === 'awaiting_statement' && $violation->statement_submitted_at === null) {
            $violation->status = 'pending_decision';
        }
        if (! in_array($violation->status, ['pending_decision', 'awaiting_statement', 'recorded'], true)) {
            throw new \RuntimeException('لا يمكن إصدار القرار في هذه الحالة');
        }

        if ($action === 'cancel') {
            $violation->update([
                'status' => 'cancelled',
                'decision_reason' => $reason,
                'decided_by' => $actor->id,
            ]);
            app(AuditLogService::class)->record('violation.cancelled', $violation, ['reason' => $reason], $actor);

            return $violation->fresh();
        }

        $violation->loadMissing('referenceItem');
        $baseline = $this->penaltyForIndex($violation->referenceItem, (int) $violation->occurrence_index);
        $chosen = $action === 'apply' ? $baseline : $lighter;
        if (! is_array($chosen) || ! isset($chosen['type'])) {
            throw new \RuntimeException('لا يوجد جزاء لهذه الواقعة');
        }
        if ($action === 'reduce' && ! $this->isLighter($chosen, $baseline)) {
            throw new \RuntimeException('التخفيف يجب أن يكون أخف من جزاء الواقعة');
        }

        $flagged = $this->exceedsMonthlyCap($violation, $chosen);
        $violation->update([
            'status' => $action === 'apply' ? 'applied' : 'reduced',
            'decided_penalty' => $chosen,
            'decision_reason' => $reason,
            'decided_by' => $actor->id,
            'cap_flagged' => $flagged,
        ]);
        app(AuditLogService::class)->record('violation.decided', $violation, [
            'action' => $action,
            'penalty' => $chosen,
            'cap_flagged' => $flagged,
            'reason' => $reason,
        ], $actor);
        event(new ViolationApplied($violation->fresh()));

        return $violation->fresh();
    }

    public function statementHtml(Violation $violation): string
    {
        $violation->loadMissing(['referenceItem', 'statements']);
        $body = e((string) ($violation->statements->sortByDesc('id')->first()?->body ?? ''));
        $name = e((string) ($violation->referenceItem?->name_ar ?? ''));

        return '<html dir="rtl"><body><h1>إفادة مخالفة</h1><p>'.$name.'</p><p>'.$body.'</p><p>توقيع الموظف: ________</p><p>توقيع الموارد البشرية: ________</p></body></html>';
    }

    private function suggestDay(int $employeeId, ReferenceItem $item, mixed $date, string $ref): int
    {
        $exists = Violation::query()->where('source_ref', $ref)->exists();
        if ($exists) {
            return 0;
        }
        Violation::query()->create([
            'employee_id' => $employeeId,
            'reference_item_id' => $item->id,
            'occurrence_index' => 1,
            'source' => 'auto',
            'source_ref' => $ref,
            'occurred_on' => Carbon::parse($date)->toDateString(),
            'discovered_on' => Carbon::parse($date)->toDateString(),
            'facts' => $item->name_ar,
            'status' => 'suggested',
        ]);

        return 1;
    }

    private function guardDiscovery(mixed $discovered): void
    {
        $limit = (int) Setting::get('hr.violations.detection_limit_days', 30);
        if (Carbon::parse($discovered)->startOfDay()->lt(now()->startOfDay()->subDays($limit))) {
            throw new \RuntimeException('تجاوزت المخالفة مهلة الاكتشاف');
        }
    }

    private function addWorkdays(Carbon $from, int $days): Carbon
    {
        $cursor = $from->copy()->startOfDay();
        $added = 0;
        while ($added < $days) {
            $cursor->addDay();
            if (! in_array($cursor->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $added++;
            }
        }

        return $cursor;
    }

    /** @return array{type: string, value?: int|float}|null */
    private function penaltyForIndex(?ReferenceItem $item, int $index): ?array
    {
        $penalties = $item?->attributes['penalties'] ?? [];
        if (! is_array($penalties) || $penalties === []) {
            return null;
        }
        if (isset($penalties[$index]) && is_array($penalties[$index])) {
            return $penalties[$index];
        }
        if (isset($penalties[$index - 1]) && is_array($penalties[$index - 1])) {
            return $penalties[$index - 1];
        }

        return null;
    }

    /** @param  array{type: string, value?: int|float}  $chosen
     * @param  array{type: string, value?: int|float}|null  $baseline
     */
    private function isLighter(array $chosen, ?array $baseline): bool
    {
        if ($baseline === null) {
            return false;
        }
        $left = self::SEVERITY[$chosen['type']] ?? 99;
        $right = self::SEVERITY[$baseline['type']] ?? 99;
        if ($left < $right) {
            return true;
        }
        if ($left === $right) {
            return (float) ($chosen['value'] ?? 0) < (float) ($baseline['value'] ?? 0);
        }

        return false;
    }

    /** @param  array{type: string, value?: int|float}  $penalty */
    private function exceedsMonthlyCap(Violation $violation, array $penalty): bool
    {
        if (($penalty['type'] ?? '') !== 'deduct_days') {
            return false;
        }
        $cap = (int) Setting::get('hr.violations.max_deduction_days_per_month', 5);
        $month = Carbon::parse($violation->occurred_on)->format('Y-m');
        $used = Violation::query()
            ->where('employee_id', $violation->employee_id)
            ->where('id', '!=', $violation->id)
            ->whereIn('status', ['applied', 'reduced'])
            ->where('occurred_on', 'like', $month.'%')
            ->get()
            ->sum(function (Violation $row) {
                $decided = $row->decided_penalty ?? [];

                return ($decided['type'] ?? '') === 'deduct_days' ? (float) ($decided['value'] ?? 0) : 0;
            });

        return ($used + (float) ($penalty['value'] ?? 0)) > $cap;
    }

    /**
     * @param  list<array{type?: string, value?: int|float}>  $company
     * @param  list<array{type?: string, value?: int|float}>  $baseline
     */
    public function assertCompanyRevision(array $company, array $baseline, string $reason): void
    {
        foreach ($company as $index => $penalty) {
            $origin = $baseline[$index] ?? null;
            if (! is_array($penalty) || ! is_array($origin)) {
                continue;
            }
            if ($this->isHarsherThanBaseline($penalty, $origin) && ! str_contains($reason, 'أؤكد')) {
                throw new \RuntimeException('الجزاء أشد من النموذج المعتمد من الوزارة');
            }
        }
    }

    /** @param  array{type?: string, value?: int|float}  $company
     * @param  array{type?: string, value?: int|float}  $baseline
     */
    public function isHarsherThanBaseline(array $company, array $baseline): bool
    {
        $left = self::SEVERITY[$company['type'] ?? ''] ?? 0;
        $right = self::SEVERITY[$baseline['type'] ?? ''] ?? 0;
        if ($left > $right) {
            return true;
        }

        return $left === $right && (float) ($company['value'] ?? 0) > (float) ($baseline['value'] ?? 0);
    }

    private function listExists(string $key): bool
    {
        return Schema::hasTable('reference_lists')
            && ReferenceList::query()->where('key', $key)->exists();
    }
}
