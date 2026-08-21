<?php

namespace App\Services;

use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\FiscalYearClose;
use App\Models\JournalLine;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FIN-ACC-5 — cost centers, bank reconciliation, fiscal year close.
 * Time: O(n) | Space: O(n)
 */
class AccountingCloseService
{
    public function syncCostCentersFromStructure(): int
    {
        $count = 0;
        foreach (Department::query()->orderBy('id')->get(['id', 'name']) as $department) {
            CostCenter::updateOrCreate(
                ['source_type' => 'department', 'source_id' => $department->id],
                ['code' => 'D'.$department->id, 'name_ar' => $department->name, 'is_active' => true],
            );
            $count++;
        }
        foreach (Project::query()->orderBy('id')->get(['id', 'name']) as $project) {
            CostCenter::updateOrCreate(
                ['source_type' => 'project', 'source_id' => $project->id],
                ['code' => 'P'.$project->id, 'name_ar' => $project->name, 'is_active' => true],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, array{cost_center: string, expenses: float, revenues: float}>
     */
    public function costCenterReport(?string $from = null, ?string $to = null): Collection
    {
        return CostCenter::query()->where('is_active', true)->orderBy('code')->get()->map(function (CostCenter $center) use ($from, $to) {
            $lines = JournalLine::query()
                ->where('cost_center_id', $center->id)
                ->whereHas('entry', function ($q) use ($from, $to) {
                    $q->where('status', \App\Models\JournalEntry::STATUS_POSTED);
                    if ($from) {
                        $q->whereDate('entry_date', '>=', $from);
                    }
                    if ($to) {
                        $q->whereDate('entry_date', '<=', $to);
                    }
                })
                ->with('account')
                ->get();

            $expenses = 0.0;
            $revenues = 0.0;
            foreach ($lines as $line) {
                if ($line->account?->type === ChartOfAccount::TYPE_EXPENSE) {
                    $expenses += (float) $line->debit - (float) $line->credit;
                }
                if ($line->account?->type === ChartOfAccount::TYPE_REVENUE) {
                    $revenues += (float) $line->credit - (float) $line->debit;
                }
            }

            return [
                'cost_center' => $center->code.' — '.$center->name_ar,
                'expenses' => round($expenses, 2),
                'revenues' => round($revenues, 2),
            ];
        });
    }

    public function reconcileBank(
        int $accountId,
        string $from,
        string $to,
        float $statementBalance,
        User $actor,
        ?string $notes = null,
    ): BankReconciliation {
        $debit = (float) JournalLine::query()->where('account_id', $accountId)
            ->whereHas('entry', fn ($q) => $q->where('status', 'مرحّل')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
            ->sum('debit');
        $credit = (float) JournalLine::query()->where('account_id', $accountId)
            ->whereHas('entry', fn ($q) => $q->where('status', 'مرحّل')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
            ->sum('credit');
        $book = round($debit - $credit, 2);

        return BankReconciliation::create([
            'account_id' => $accountId,
            'period_from' => $from,
            'period_to' => $to,
            'statement_balance' => $statementBalance,
            'book_balance' => $book,
            'difference' => round($statementBalance - $book, 2),
            'status' => abs($statementBalance - $book) < 0.005 ? 'مكتمل' : 'مسودة',
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);
    }

    public function postOpeningBalance(float $cashAmount, User $actor, ?string $date = null): \App\Models\JournalEntry
    {
        $cash = app(JournalService::class)->accountByCode('1100');
        $equity = app(JournalService::class)->accountByCode('3100');

        return app(JournalService::class)->postManual(
            'رصيد افتتاحي',
            $date ?? now()->startOfYear()->toDateString(),
            [
                ['account_id' => $cash->id, 'debit' => $cashAmount, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => $cashAmount],
            ],
            $actor,
        );
    }

    public function closeFiscalYear(int $year, User $actor): FiscalYearClose
    {
        if (FiscalYearClose::query()->where('year', $year)->exists()) {
            throw new \RuntimeException('السنة مُقفلة مسبقًا');
        }

        return DB::transaction(function () use ($year, $actor) {
            $from = sprintf('%d-01-01', $year);
            $to = sprintf('%d-12-31', $year);
            $income = app(AccountingReportService::class)->incomeStatement($from, $to);
            $surplus = $income['surplus'];
            $equity = app(JournalService::class)->accountByCode('3100');
            $revenue = app(JournalService::class)->accountByCode('4100');
            $expense = app(JournalService::class)->accountByCode('5100');

            $lines = [];
            if ($surplus >= 0) {
                $lines[] = ['account_id' => $revenue->id, 'debit' => $income['revenues'], 'credit' => 0];
                $lines[] = ['account_id' => $expense->id, 'debit' => 0, 'credit' => $income['expenses']];
                $lines[] = ['account_id' => $equity->id, 'debit' => 0, 'credit' => $surplus];
            } else {
                $lines[] = ['account_id' => $revenue->id, 'debit' => $income['revenues'], 'credit' => 0];
                $lines[] = ['account_id' => $equity->id, 'debit' => abs($surplus), 'credit' => 0];
                $lines[] = ['account_id' => $expense->id, 'debit' => 0, 'credit' => $income['expenses']];
            }

            // Only post closing if there is movement
            $entry = null;
            if ($income['revenues'] > 0 || $income['expenses'] > 0) {
                $entry = app(JournalService::class)->postManual(
                    'إقفال سنة '.$year,
                    $to,
                    $lines,
                    $actor,
                );
            }

            return FiscalYearClose::create([
                'year' => $year,
                'closing_entry_id' => $entry?->id,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);
        });
    }
}
