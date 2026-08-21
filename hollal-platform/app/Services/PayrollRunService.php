<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Notifications\PayrollExecuted;
use App\Notifications\PayrollReturnedToHr;
use App\Notifications\PayrollSubmittedToFinance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 01-B3 — payroll run lifecycle. All money is derived from active salary
 * components + approved overtime + monthly variables; there is no manual total.
 */
class PayrollRunService
{
    public function generate(string $month): PayrollRun
    {
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $attendanceByEmployee = AttendanceRecord::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            ->get(['employee_id', 'check_in_at', 'check_out_at'])
            ->groupBy('employee_id');
        $attendanceService = app(AttendanceService::class);

        return DB::transaction(function () use ($month, $monthEnd, $attendanceByEmployee, $attendanceService) {
            $cycle = app(AttendanceDeductionService::class)->currentCycle(
                Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            );

            $run = PayrollRun::create([
                'month' => $month,
                'status' => PayrollRun::STATUS_DRAFT,
                'cycle_from' => $cycle['from']->toDateString(),
                'cycle_to' => $cycle['to']->toDateString(),
            ]);

            $employees = User::query()
                ->where('is_active', true)
                ->where('employment_status', User::STATUS_ACTIVE)
                ->with('profile')
                ->get();

            foreach ($employees as $employee) {
                $components = SalaryComponent::query()
                    ->where('employee_id', $employee->id)
                    ->effectiveOn($monthEnd)
                    ->get();

                $isRegular = app(SalaryService::class)->isRegularEmployee($employee);
                $overtimeHours = 0.0;
                $overtimeAmount = 0.0;
                if ($employee->attendance_enabled) {
                    $overtimeHours = $attendanceService->overtimeHoursForMonth(
                        $employee,
                        $month,
                        $attendanceByEmployee->get($employee->id, collect()),
                    );
                    if ($employee->profile?->overtime_unlocked) {
                        $overtimeAmount = round($overtimeHours * (float) ($employee->profile->overtime_hour_value ?? 0), 2);
                    }
                }

                $item = new PayrollRunItem([
                    'employee_id' => $employee->id,
                    'base' => $components->where('type', SalaryComponent::TYPE_BASE)->sum('amount'),
                    'allowances' => $components->where('type', SalaryComponent::TYPE_ALLOWANCE)->sum('amount'),
                    'deductions' => $isRegular
                        ? $components->where('type', SalaryComponent::TYPE_DEDUCTION)->sum('amount')
                        : 0,
                    'overtime_hours' => $overtimeHours,
                    'overtime_amount' => $overtimeAmount,
                    'variables' => [],
                ]);
                $item->payroll_run_id = $run->id;
                $item->recalculate();
                $item->save();
            }

            return $run;
        });
    }

    public function setOvertime(PayrollRunItem $item, float $hours): PayrollRunItem
    {
        $this->assertEditable($item->run);

        if (! $item->employee->profile?->overtime_unlocked) {
            throw new \InvalidArgumentException('الساعات الإضافية مقفلة لهذا الموظف — افتحها من الملف الوظيفي أولاً');
        }

        $hourValue = (float) ($item->employee->profile?->overtime_hour_value ?? 0);

        $item->overtime_hours = $hours;
        $item->overtime_amount = round($hours * $hourValue, 2);
        $item->recalculate();
        $item->save();

        return $item;
    }

    /**
     * @param  'addition'|'deduction'  $kind
     */
    public function addVariable(PayrollRunItem $item, string $label, string $reason, float $amount, string $kind): PayrollRunItem
    {
        $this->assertEditable($item->run);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب البند المتغير إلزامي.');
        }

        $variables = $item->variables ?? [];
        $variables[] = ['label' => $label, 'reason' => $reason, 'amount' => $amount, 'kind' => $kind];
        $item->variables = $variables;
        $item->recalculate();
        $item->save();

        return $item;
    }

    /**
     * Update a VARIABLE line by index while the run is draft/returned.
     * Time: O(v) where v = variables on the item | Space: O(v).
     *
     * @param  'addition'|'deduction'  $kind
     */
    public function updateVariable(
        PayrollRunItem $item,
        int $index,
        string $label,
        string $reason,
        float $amount,
        string $kind,
    ): PayrollRunItem {
        $this->assertEditable($item->run);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب البند المتغير إلزامي.');
        }

        $variables = $item->variables ?? [];
        if (! array_key_exists($index, $variables)) {
            throw new \InvalidArgumentException('البند المتغير غير موجود.');
        }

        $variables[$index] = [
            'label' => $label,
            'reason' => $reason,
            'amount' => $amount,
            'kind' => $kind,
        ];
        $item->variables = array_values($variables);
        $item->recalculate();
        $item->save();

        return $item;
    }

    /**
     * Delete a VARIABLE line by index while the run is draft/returned.
     * Time: O(v) | Space: O(v).
     */
    public function deleteVariable(PayrollRunItem $item, int $index): PayrollRunItem
    {
        $this->assertEditable($item->run);

        $variables = $item->variables ?? [];
        if (! array_key_exists($index, $variables)) {
            throw new \InvalidArgumentException('البند المتغير غير موجود.');
        }

        unset($variables[$index]);
        $item->variables = array_values($variables);
        $item->recalculate();
        $item->save();

        return $item;
    }

    /**
     * Sync monthly Payroll rows into a draft/returned PayrollRun for the same month.
     * Matching items are updated from Payroll amounts; missing items are created when a draft run exists.
     * Big-O: Time O(p + i) for payrolls p and run items i (maps keyed by employee_id); Space O(p + i).
     *
     * @return array{updated: int, created: int, skipped: bool, message: string}
     */
    public function syncFromMonthlyPayroll(User $actor, string $month, ?int $employeeId = null): array
    {
        $run = PayrollRun::query()
            ->where('month', $month)
            ->whereIn('status', [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED])
            ->first();

        if (! $run) {
            $run = PayrollRun::create([
                'month' => $month,
                'status' => PayrollRun::STATUS_DRAFT,
            ]);
        }

        $this->assertEditable($run);

        $monthDate = $month.'-01';
        $payrollQuery = \App\Models\Payroll::query()->whereDate('month', $monthDate);
        if ($employeeId !== null) {
            $payrollQuery->where('employee_id', $employeeId);
        }
        $payrolls = $payrollQuery->get();

        if ($payrolls->isEmpty()) {
            return [
                'updated' => 0,
                'created' => 0,
                'skipped' => true,
                'message' => 'لا توجد رواتب شهرية لهذا الشهر للمزامنة.',
            ];
        }

        $itemsByEmployee = $run->items()->get()->keyBy('employee_id');
        $updated = 0;
        $created = 0;

        foreach ($payrolls as $payroll) {
            $item = $itemsByEmployee->get($payroll->employee_id);
            if ($item) {
                $item->base = (float) $payroll->base;
                $item->allowances = (float) $payroll->additions;
                $item->deductions = (float) $payroll->deductions;
                $item->recalculate();
                $item->save();
                $updated++;
            } else {
                $item = new PayrollRunItem([
                    'employee_id' => $payroll->employee_id,
                    'base' => (float) $payroll->base,
                    'allowances' => (float) $payroll->additions,
                    'deductions' => (float) $payroll->deductions,
                    'overtime_hours' => 0,
                    'overtime_amount' => 0,
                    'variables' => [],
                ]);
                $item->payroll_run_id = $run->id;
                $item->recalculate();
                $item->save();
                $created++;
            }
        }

        return [
            'updated' => $updated,
            'created' => $created,
            'skipped' => false,
            'message' => sprintf(
                'تمت مزامنة الرواتب الشهرية إلى المسيّر (%d تحديث، %d إنشاء). المسيّر هو مسار الاعتماد قبل الصرف.',
                $updated,
                $created
            ),
        ];
    }

    /**
     * Mirror monthly payroll amounts into active salary components (employee profile source).
     * Time: O(1) | Space: O(1)
     */
    public function mirrorMonthlyPayrollToProfile(\App\Models\Payroll $payroll): void
    {
        $from = $payroll->month?->copy()->startOfMonth() ?? now()->startOfMonth();

        SalaryComponent::query()
            ->where('employee_id', $payroll->employee_id)
            ->where('type', SalaryComponent::TYPE_BASE)
            ->where('is_active', true)
            ->whereNull('valid_to')
            ->whereDate('valid_from', '<', $from->toDateString())
            ->update(['valid_to' => $from->copy()->subDay()->toDateString()]);

        SalaryComponent::updateOrCreate(
            [
                'employee_id' => $payroll->employee_id,
                'type' => SalaryComponent::TYPE_BASE,
                'label_ar' => 'أساسي (من الرواتب الشهرية)',
                'valid_from' => $from->toDateString(),
            ],
            [
                'amount' => (float) $payroll->base,
                'is_active' => true,
                'valid_to' => null,
            ]
        );

        if ((float) $payroll->additions > 0) {
            SalaryComponent::updateOrCreate(
                [
                    'employee_id' => $payroll->employee_id,
                    'type' => SalaryComponent::TYPE_ALLOWANCE,
                    'label_ar' => 'بدلات (من الرواتب الشهرية)',
                    'valid_from' => $from->toDateString(),
                ],
                [
                    'amount' => (float) $payroll->additions,
                    'is_active' => true,
                    'valid_to' => null,
                ]
            );
        }

        if ((float) $payroll->deductions > 0) {
            SalaryComponent::updateOrCreate(
                [
                    'employee_id' => $payroll->employee_id,
                    'type' => SalaryComponent::TYPE_DEDUCTION,
                    'label_ar' => 'خصومات (من الرواتب الشهرية)',
                    'valid_from' => $from->toDateString(),
                ],
                [
                    'amount' => (float) $payroll->deductions,
                    'is_active' => true,
                    'valid_to' => null,
                ]
            );
        }
    }

    public function submitToFinance(PayrollRun $run, User $actor): PayrollRun
    {
        $this->assertEditable($run);

        $run->update([
            'status' => PayrollRun::STATUS_SUBMITTED,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ]);

        Notification::send(User::role('Finance')->get(), new PayrollSubmittedToFinance($run));

        return $run;
    }

    /**
     * 04-B2 — finance approves the submitted run before executing rows.
     */
    public function financeApprove(PayrollRun $run, User $financeUser): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException('لا يمكن الاعتماد المالي إلا لمسيّر مرفوع للمالية.');
        }

        $run->update([
            'finance_approved_by' => $financeUser->id,
            'finance_approved_at' => now(),
        ]);

        return $run;
    }

    /**
     * 04-B2 — execute a single row (transfer reference/date + proof). Amounts are
     * never touched. When every row is executed, the run becomes منفذ and HR is
     * notified.
     */
    public function executeItem(PayrollRunItem $item, string $reference, string $date, ?string $proofFile = null): PayrollRunItem
    {
        $run = $item->run;

        if ($run->status !== PayrollRun::STATUS_SUBMITTED || ! $run->isFinanceApproved()) {
            throw new \InvalidArgumentException('يلزم الاعتماد المالي قبل تنفيذ الرواتب.');
        }

        $item->update([
            'transfer_reference' => $reference,
            'transfer_date' => $date,
            'proof_file' => $proofFile,
            'executed_at' => now(),
        ]);

        $pending = $run->items()->whereNull('executed_at')->count();
        if ($pending === 0) {
            $run->update(['status' => PayrollRun::STATUS_EXECUTED]);
            if ($run->submitter) {
                $run->submitter->notify(new PayrollExecuted($run));
            }
            try {
                app(JournalService::class)->postPayrollExecuted($run->fresh());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $item;
    }

    public function returnForCorrection(PayrollRun $run, string $note): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException('لا يمكن إرجاع مسيّر ليس مرفوعًا للمالية.');
        }

        $run->update(['status' => PayrollRun::STATUS_RETURNED, 'notes' => $note]);

        if ($run->submitter) {
            $run->submitter->notify(new PayrollReturnedToHr($run));
        }

        return $run;
    }

    private function assertEditable(PayrollRun $run): void
    {
        if (! $run->isEditable()) {
            throw new \InvalidArgumentException('لا يمكن تعديل مبالغ مسيّر بعد رفعه للمالية.');
        }
    }
}
