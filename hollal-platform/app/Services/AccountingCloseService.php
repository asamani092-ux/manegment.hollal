<?php

namespace App\Services;

use App\Support\CoaCodes;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\FiscalYearClose;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\OrgUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
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
        foreach (OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_ADMINISTRATION)
            ->orderBy('id')
            ->get(['id', 'name']) as $admin) {
            CostCenter::updateOrCreate(
                ['source_type' => 'administration', 'source_id' => $admin->id],
                ['code' => 'A'.$admin->id, 'name_ar' => $admin->name, 'is_active' => true],
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

    /**
     * استيراد كشف بنك CSV/TSV وربطه بتسوية + مطابقة تلقائية (±3 أيام).
     * Time: O(rows × journal_lines) | Space: O(rows)
     *
     * @return array{reconciliation: BankReconciliation, imported: int, matched: int}
     */
    public function importBankStatement(
        int $accountId,
        string $from,
        string $to,
        string $filePath,
        User $actor,
        ?float $statementBalance = null,
    ): array {
        $rows = $this->parseStatementFile($filePath);
        if ($rows === []) {
            throw new \InvalidArgumentException('الملف فارغ أو غير قابل للقراءة');
        }

        $lastBalance = $statementBalance;
        if ($lastBalance === null) {
            $last = end($rows);
            $lastBalance = (float) ($last['balance'] ?? 0);
        }

        $rec = $this->reconcileBank($accountId, $from, $to, (float) $lastBalance, $actor, 'استيراد كشف');

        $matched = 0;
        foreach ($rows as $row) {
            $line = BankStatementLine::create([
                'bank_reconciliation_id' => $rec->id,
                'transaction_date' => $row['transaction_date'],
                'description' => $row['description'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'balance' => $row['balance'],
                'reference' => $row['reference'],
                'match_status' => BankStatementLine::MATCH_UNMATCHED,
            ]);

            $journalLineId = $this->findMatchingJournalLine($accountId, $line);
            if ($journalLineId) {
                $line->update([
                    'match_status' => BankStatementLine::MATCH_MATCHED,
                    'matched_journal_line_id' => $journalLineId,
                ]);
                $matched++;
            }
        }

        return ['reconciliation' => $rec, 'imported' => count($rows), 'matched' => $matched];
    }

    /**
     * @return list<array{transaction_date: string, description: ?string, debit: float, credit: float, balance: float, reference: ?string}>
     */
    private function parseStatementFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $out = [];
        $headerSkipped = false;

        foreach ($lines as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $parts = str_contains($raw, "\t") ? explode("\t", $raw) : str_getcsv($raw);
            $parts = array_map(fn ($p) => trim((string) $p), $parts);

            if (! $headerSkipped && preg_match('/تاريخ|date|الوصف|description/i', implode(',', $parts))) {
                $headerSkipped = true;

                continue;
            }
            $headerSkipped = true;

            if (count($parts) < 3) {
                continue;
            }

            $dateRaw = $parts[0];
            try {
                $date = Carbon::parse($dateRaw)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            $description = $parts[1] ?? null;
            $debit = 0.0;
            $credit = 0.0;
            $balance = 0.0;
            $reference = null;

            if (count($parts) >= 5) {
                $debit = abs((float) str_replace(',', '', $parts[2] ?? '0'));
                $credit = abs((float) str_replace(',', '', $parts[3] ?? '0'));
                $balance = (float) str_replace(',', '', $parts[4] ?? '0');
                $reference = $parts[5] ?? null;
            } else {
                $amount = (float) str_replace(',', '', $parts[2] ?? '0');
                if ($amount >= 0) {
                    $debit = $amount;
                } else {
                    $credit = abs($amount);
                }
                $balance = (float) str_replace(',', '', $parts[3] ?? '0');
            }

            $out[] = [
                'transaction_date' => $date,
                'description' => $description,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($balance, 2),
                'reference' => $reference,
            ];
        }

        return $out;
    }

    private function findMatchingJournalLine(int $accountId, BankStatementLine $line): ?int
    {
        $amount = round(max((float) $line->debit, (float) $line->credit), 2);
        if ($amount <= 0) {
            return null;
        }

        $from = Carbon::parse($line->transaction_date)->subDays(3)->toDateString();
        $to = Carbon::parse($line->transaction_date)->addDays(3)->toDateString();
        $preferDebit = (float) $line->debit > 0;

        $query = JournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                    ->whereDate('entry_date', '>=', $from)
                    ->whereDate('entry_date', '<=', $to);
            })
            ->whereDoesntHave('matchedStatementLines')
            ->when($preferDebit, fn ($q) => $q->where('debit', $amount), fn ($q) => $q->where('credit', $amount));

        return $query->value('id');
    }

    public function postOpeningBalance(float $cashAmount, User $actor, ?string $date = null): \App\Models\JournalEntry
    {
        $cash = app(JournalService::class)->accountByCode(CoaCodes::CASH);
        $equity = app(JournalService::class)->accountByCode(CoaCodes::UNRESTRICTED_NET_ASSETS);

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
            $equity = app(JournalService::class)->accountByCode(CoaCodes::UNRESTRICTED_NET_ASSETS);
            $revenue = app(JournalService::class)->accountByCode(CoaCodes::UNRESTRICTED_PARTNERSHIP_REVENUE);
            $expense = app(JournalService::class)->accountByCode(CoaCodes::EXP_MISC);

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
