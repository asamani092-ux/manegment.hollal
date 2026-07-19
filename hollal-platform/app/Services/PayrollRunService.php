<?php

namespace App\Services;

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryComponent;
use App\Models\User;
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

        return DB::transaction(function () use ($month, $monthEnd) {
            $run = PayrollRun::create(['month' => $month, 'status' => PayrollRun::STATUS_DRAFT]);

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

                $item = new PayrollRunItem([
                    'employee_id' => $employee->id,
                    'base' => $components->where('type', SalaryComponent::TYPE_BASE)->sum('amount'),
                    'allowances' => $components->where('type', SalaryComponent::TYPE_ALLOWANCE)->sum('amount'),
                    'deductions' => $components->where('type', SalaryComponent::TYPE_DEDUCTION)->sum('amount'),
                    'overtime_hours' => 0,
                    'overtime_amount' => 0,
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

        $variables = $item->variables ?? [];
        $variables[] = ['label' => $label, 'reason' => $reason, 'amount' => $amount, 'kind' => $kind];
        $item->variables = $variables;
        $item->recalculate();
        $item->save();

        return $item;
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
