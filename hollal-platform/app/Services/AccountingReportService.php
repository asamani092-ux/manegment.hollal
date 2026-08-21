<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Support\PdfArabic;
use Illuminate\Support\Collection;

/**
 * FIN-ACC-3 / FIN-ACC-4 — ledger, trial balance, and financial statements from journals.
 * Time: O(lines) | Space: O(accounts)
 */
class AccountingReportService
{
    /**
     * @return Collection<int, array{date: string, number: string, description: string, debit: float, credit: float, balance: float}>
     */
    public function generalLedger(int $accountId, ?string $from = null, ?string $to = null): Collection
    {
        $query = JournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->where('status', \App\Models\JournalEntry::STATUS_POSTED);
                if ($from) {
                    $q->whereDate('entry_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('entry_date', '<=', $to);
                }
            })
            ->with('entry')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select('journal_lines.*');

        $running = 0.0;
        $account = ChartOfAccount::findOrFail($accountId);
        $isDebitNature = $account->nature === ChartOfAccount::NATURE_DEBIT;

        return $query->get()->map(function (JournalLine $line) use (&$running, $isDebitNature) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $running += $isDebitNature ? ($debit - $credit) : ($credit - $debit);

            return [
                'date' => $line->entry->entry_date?->format('Y-m-d') ?? '',
                'number' => $line->entry->number,
                'description' => $line->description ?: $line->entry->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($running, 2),
            ];
        });
    }

    /**
     * @return array{rows: list<array{account_id: int, code: string, name_ar: string, debit: float, credit: float}>, total_debit: float, total_credit: float, balanced: bool}
     */
    public function trialBalance(?string $from = null, ?string $to = null): array
    {
        $accounts = ChartOfAccount::query()->active()->orderBy('code')->get();
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $sums = JournalLine::query()
                ->where('account_id', $account->id)
                ->whereHas('entry', function ($q) use ($from, $to) {
                    $q->where('status', \App\Models\JournalEntry::STATUS_POSTED);
                    if ($from) {
                        $q->whereDate('entry_date', '>=', $from);
                    }
                    if ($to) {
                        $q->whereDate('entry_date', '<=', $to);
                    }
                })
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();

            $d = round((float) ($sums->d ?? 0), 2);
            $c = round((float) ($sums->c ?? 0), 2);
            if ($d == 0.0 && $c == 0.0) {
                continue;
            }

            $netDebit = 0.0;
            $netCredit = 0.0;
            if ($account->nature === ChartOfAccount::NATURE_DEBIT) {
                $bal = round($d - $c, 2);
                if ($bal >= 0) {
                    $netDebit = $bal;
                } else {
                    $netCredit = abs($bal);
                }
            } else {
                $bal = round($c - $d, 2);
                if ($bal >= 0) {
                    $netCredit = $bal;
                } else {
                    $netDebit = abs($bal);
                }
            }

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name_ar' => $account->name_ar,
                'debit' => $netDebit,
                'credit' => $netCredit,
            ];
            $totalDebit += $netDebit;
            $totalCredit += $netCredit;
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) < 0.005,
        ];
    }

    /**
     * @return array{revenues: float, expenses: float, surplus: float}
     */
    public function incomeStatement(?string $from = null, ?string $to = null): array
    {
        $revenues = $this->typeMovementTotal(ChartOfAccount::TYPE_REVENUE, $from, $to, creditNature: true);
        $expenses = $this->typeMovementTotal(ChartOfAccount::TYPE_EXPENSE, $from, $to, creditNature: false);

        return [
            'revenues' => $revenues,
            'expenses' => $expenses,
            'surplus' => round($revenues - $expenses, 2),
        ];
    }

    /**
     * @return array{assets: float, liabilities: float, equity: float, balanced: bool}
     */
    public function balanceSheet(?string $asOf = null): array
    {
        $assets = $this->typeBalance(ChartOfAccount::TYPE_ASSETS, $asOf, debitNature: true);
        $liabilities = $this->typeBalance(ChartOfAccount::TYPE_LIABILITIES, $asOf, debitNature: false);
        $equity = $this->typeBalance(ChartOfAccount::TYPE_EQUITY, $asOf, debitNature: false);
        $income = $this->incomeStatement(null, $asOf);
        $equity += $income['surplus'];

        $assets = round($assets, 2);
        $liabilities = round($liabilities, 2);
        $equity = round($equity, 2);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'balanced' => abs($assets - ($liabilities + $equity)) < 0.005,
        ];
    }

    /**
     * Simplified operating cash flow: net cash/bank movement in period.
     *
     * @return array{operating: float}
     */
    public function cashFlow(?string $from = null, ?string $to = null): array
    {
        $cashIds = ChartOfAccount::query()->whereIn('code', ['1100', '1200'])->pluck('id');
        $debit = (float) JournalLine::query()
            ->whereIn('account_id', $cashIds)
            ->whereHas('entry', fn ($q) => $this->periodFilter($q, $from, $to))
            ->sum('debit');
        $credit = (float) JournalLine::query()
            ->whereIn('account_id', $cashIds)
            ->whereHas('entry', fn ($q) => $this->periodFilter($q, $from, $to))
            ->sum('credit');

        return ['operating' => round($debit - $credit, 2)];
    }

    public function trialBalancePdf(?string $from = null, ?string $to = null): string
    {
        $tb = $this->trialBalance($from, $to);
        $rows = '';
        foreach ($tb['rows'] as $row) {
            $rows .= '<tr><td class="ltr">'.e($row['code']).'</td><td>'.e($row['name_ar']).'</td>'
                .'<td class="ltr">'.number_format($row['debit'], 2).'</td>'
                .'<td class="ltr">'.number_format($row['credit'], 2).'</td></tr>';
        }
        $body = '<h3>ميزان المراجعة</h3>'
            .'<p>من '.e((string) $from).' إلى '.e((string) $to).'</p>'
            .'<table border="1" cellpadding="4" width="100%"><thead><tr><th>الرقم</th><th>الحساب</th><th>مدين</th><th>دائن</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'<tfoot><tr><th colspan="2">المجموع</th><th class="ltr">'.number_format($tb['total_debit'], 2).'</th>'
            .'<th class="ltr">'.number_format($tb['total_credit'], 2).'</th></tr></tfoot></table>';

        return PdfArabic::render('ميزان المراجعة', $body, includeCr: true);
    }

    private function typeMovementTotal(string $type, ?string $from, ?string $to, bool $creditNature): float
    {
        $ids = ChartOfAccount::query()->where('type', $type)->pluck('id');
        $debit = (float) JournalLine::query()->whereIn('account_id', $ids)
            ->whereHas('entry', fn ($q) => $this->periodFilter($q, $from, $to))->sum('debit');
        $credit = (float) JournalLine::query()->whereIn('account_id', $ids)
            ->whereHas('entry', fn ($q) => $this->periodFilter($q, $from, $to))->sum('credit');

        return round($creditNature ? ($credit - $debit) : ($debit - $credit), 2);
    }

    private function typeBalance(string $type, ?string $asOf, bool $debitNature): float
    {
        return $this->typeMovementTotal($type, null, $asOf, creditNature: ! $debitNature);
    }

    private function periodFilter($q, ?string $from, ?string $to): void
    {
        $q->where('status', \App\Models\JournalEntry::STATUS_POSTED);
        if ($from) {
            $q->whereDate('entry_date', '>=', $from);
        }
        if ($to) {
            $q->whereDate('entry_date', '<=', $to);
        }
    }
}
