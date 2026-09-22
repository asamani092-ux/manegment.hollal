<?php

namespace App\Services;

use App\Support\CoaCodes;
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
     * قائمة الأنشطة (غير ربحية): إيرادات مقيّدة/غير مقيّدة − مصروفات.
     *
     * @return array{
     *   unrestricted_revenue: float,
     *   restricted_revenue: float,
     *   total_revenue: float,
     *   expenses: float,
     *   change_in_net_assets: float,
     *   revenues: float,
     *   surplus: float
     * }
     */
    public function incomeStatement(?string $from = null, ?string $to = null, ?int $costCenterId = null): array
    {
        $unrestrictedRevenue = $this->movementByPrefix('41', $from, $to, creditNature: true, costCenterId: $costCenterId);
        $restrictedRevenue = $this->movementByPrefix('42', $from, $to, creditNature: true, costCenterId: $costCenterId);
        $expenses = $this->typeMovementTotal(ChartOfAccount::TYPE_EXPENSE, $from, $to, creditNature: false, costCenterId: $costCenterId);
        $totalRevenue = round($unrestrictedRevenue + $restrictedRevenue, 2);
        $change = round($totalRevenue - $expenses, 2);

        return [
            'unrestricted_revenue' => $unrestrictedRevenue,
            'restricted_revenue' => $restrictedRevenue,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenses,
            'change_in_net_assets' => $change,
            // توافق خلفي مع الواجهات القديمة
            'revenues' => $totalRevenue,
            'surplus' => $change,
        ];
    }

    /**
     * قائمة المركز المالي: أصول = خصوم + صافي أصول (غير مقيّدة + مقيّدة).
     *
     * @return array{
     *   assets: float,
     *   liabilities: float,
     *   unrestricted_net_assets: float,
     *   restricted_net_assets: float,
     *   total_net_assets: float,
     *   equity: float,
     *   balanced: bool
     * }
     */
    public function balanceSheet(?string $asOf = null, ?int $costCenterId = null): array
    {
        $assets = $this->typeBalance(ChartOfAccount::TYPE_ASSETS, $asOf, debitNature: true, costCenterId: $costCenterId);
        $liabilities = $this->typeBalance(ChartOfAccount::TYPE_LIABILITIES, $asOf, debitNature: false, costCenterId: $costCenterId);
        $unrestrictedNetAssets = $this->balanceByPrefix('311', $asOf, debitNature: false, costCenterId: $costCenterId);
        $restrictedNetAssets = $this->balanceByPrefix('321', $asOf, debitNature: false, costCenterId: $costCenterId)
            + $this->balanceByPrefix('322', $asOf, debitNature: false, costCenterId: $costCenterId);
        $income = $this->incomeStatement(null, $asOf, $costCenterId);
        $unrestrictedNetAssets += $income['change_in_net_assets'];

        $totalNetAssets = round($unrestrictedNetAssets + $restrictedNetAssets, 2);
        $assets = round($assets, 2);
        $liabilities = round($liabilities, 2);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'unrestricted_net_assets' => round($unrestrictedNetAssets, 2),
            'restricted_net_assets' => round($restrictedNetAssets, 2),
            'total_net_assets' => $totalNetAssets,
            'equity' => $totalNetAssets,
            'balanced' => abs($assets - ($liabilities + $totalNetAssets)) < 0.005,
        ];
    }

    /**
     * قائمة التدفقات النقدية — تشغيلي / استثماري / تمويلي.
     *
     * @return array{
     *   operating: float,
     *   investing: float,
     *   financing: float,
     *   net_change: float,
     *   opening_cash: float,
     *   closing_cash: float
     * }
     */
    /** اسم بديل للمواصفة. */
    public function cashFlowStatement(?string $from = null, ?string $to = null, ?int $costCenterId = null): array
    {
        return $this->cashFlow($from, $to, $costCenterId);
    }

    public function cashFlow(?string $from = null, ?string $to = null, ?int $costCenterId = null): array
    {
        $income = $this->incomeStatement($from, $to, $costCenterId);
        $depreciation = $this->movementByCode(CoaCodes::EXP_DEPRECIATION, $from, $to, costCenterId: $costCenterId);
        $changeReceivables = $this->periodChangeByCode(CoaCodes::RECEIVABLES, $from, $to, costCenterId: $costCenterId);
        $changePayables = $this->periodChangeByCode(CoaCodes::SALARIES_PAYABLE, $from, $to, costCenterId: $costCenterId);
        $changeDeferred = $this->periodChangeByCode(CoaCodes::DEFERRED_REVENUE, $from, $to, costCenterId: $costCenterId);

        $operatingCash = $income['change_in_net_assets']
            + $depreciation
            - $changeReceivables
            + $changePayables
            + $changeDeferred;

        $assetPurchases = $this->movementByCodeRange(CoaCodes::FURNITURE, CoaCodes::COMPUTERS, $from, $to, debitSide: true, costCenterId: $costCenterId);
        $investingCash = -$assetPurchases;

        $changeRestricted = $this->periodChangeByCode(CoaCodes::TEMP_RESTRICTED_NET_ASSETS, $from, $to, costCenterId: $costCenterId)
            + $this->periodChangeByCode(CoaCodes::PERM_RESTRICTED_NET_ASSETS, $from, $to, costCenterId: $costCenterId);
        $financingCash = $changeRestricted;

        $netChange = round($operatingCash + $investingCash + $financingCash, 2);
        $openingAsOf = $from
            ? (new \DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d')
            : null;
        $openingCash = $this->balanceByCodeRange(CoaCodes::CASH, CoaCodes::BANK_INMA, $openingAsOf, debitNature: true, costCenterId: $costCenterId);
        $closingCash = round($openingCash + $netChange, 2);

        return [
            'operating' => round($operatingCash, 2),
            'investing' => round($investingCash, 2),
            'financing' => round($financingCash, 2),
            'net_change' => $netChange,
            'opening_cash' => round($openingCash, 2),
            'closing_cash' => $closingCash,
        ];
    }

    public function trialBalancePdf(?string $from = null, ?string $to = null): string
    {
        $tb = $this->trialBalance($from, $to);
        $rows = '';
        foreach ($tb['rows'] as $row) {
            $rows .= '<tr><td class="num">'.e($row['code']).'</td><td>'.e($row['name_ar']).'</td>'
                .'<td class="num">'.number_format($row['debit'], 2).'</td>'
                .'<td class="num">'.number_format($row['credit'], 2).'</td></tr>';
        }
        $body = '<h3>ميزان المراجعة</h3>'
            .'<p>من '.e((string) $from).' إلى '.e((string) $to).'</p>'
            .'<table border="1" cellpadding="4" width="100%"><thead><tr><th>الرقم</th><th>الحساب</th><th>مدين</th><th>دائن</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'<tfoot><tr><th colspan="2">المجموع</th><th class="num">'.number_format($tb['total_debit'], 2).'</th>'
            .'<th class="num">'.number_format($tb['total_credit'], 2).'</th></tr></tfoot></table>';

        return PdfArabic::render('ميزان المراجعة', $body, includeCr: true);
    }

    public function cashFlowPdf(?string $from = null, ?string $to = null): string
    {
        $cf = $this->cashFlow($from, $to);
        $body = '<h3>قائمة التدفقات النقدية</h3>'
            .'<p>من '.e((string) $from).' إلى '.e((string) $to).'</p>'
            .'<table border="1" cellpadding="4" width="100%">'
            .'<tr><td>صافي النقد من التشغيل</td><td class="num">'.number_format($cf['operating'], 2).'</td></tr>'
            .'<tr><td>صافي النقد من الاستثمار</td><td class="num">'.number_format($cf['investing'], 2).'</td></tr>'
            .'<tr><td>صافي النقد من التمويل</td><td class="num">'.number_format($cf['financing'], 2).'</td></tr>'
            .'<tr><th>صافي التغيّر</th><th class="num">'.number_format($cf['net_change'], 2).'</th></tr>'
            .'<tr><td>رصيد أول المدة</td><td class="num">'.number_format($cf['opening_cash'], 2).'</td></tr>'
            .'<tr><td>رصيد آخر المدة</td><td class="num">'.number_format($cf['closing_cash'], 2).'</td></tr>'
            .'</table>';

        return PdfArabic::render('التدفقات النقدية', $body, includeCr: true);
    }

    private function typeMovementTotal(string $type, ?string $from, ?string $to, bool $creditNature, ?int $costCenterId = null): float
    {
        $ids = ChartOfAccount::query()
            ->where('type', $type)
            ->where(function ($q) {
                $q->where('is_postable', true)->orWhereRaw('LENGTH(code) = 3');
            })
            ->pluck('id');

        return $this->netMovementForIds($ids, $from, $to, $creditNature, $costCenterId);
    }

    private function typeBalance(string $type, ?string $asOf, bool $debitNature, ?int $costCenterId = null): float
    {
        return $this->typeMovementTotal($type, null, $asOf, creditNature: ! $debitNature, costCenterId: $costCenterId);
    }

    private function movementByPrefix(string $prefix, ?string $from, ?string $to, bool $creditNature, ?int $costCenterId = null): float
    {
        $ids = ChartOfAccount::query()
            ->where('code', 'like', $prefix.'%')
            ->whereRaw('LENGTH(code) = 3')
            ->pluck('id');

        return $this->netMovementForIds($ids, $from, $to, $creditNature, $costCenterId);
    }

    private function balanceByPrefix(string $prefix, ?string $asOf, bool $debitNature, ?int $costCenterId = null): float
    {
        return $this->movementByPrefix($prefix, null, $asOf, creditNature: ! $debitNature, costCenterId: $costCenterId);
    }

    private function movementByCode(string $code, ?string $from, ?string $to, ?int $costCenterId = null): float
    {
        $id = ChartOfAccount::query()->where('code', $code)->value('id');
        if (! $id) {
            return 0.0;
        }

        return $this->netMovementForIds(collect([$id]), $from, $to, creditNature: false, costCenterId: $costCenterId);
    }

    private function movementByCodeRange(string $fromCode, string $toCode, ?string $from, ?string $to, bool $debitSide = true, ?int $costCenterId = null): float
    {
        $ids = ChartOfAccount::query()
            ->where('code', '>=', $fromCode)
            ->where('code', '<=', $toCode)
            ->whereRaw('LENGTH(code) = 3')
            ->pluck('id');

        return $this->netMovementForIds($ids, $from, $to, creditNature: ! $debitSide, costCenterId: $costCenterId);
    }

    private function balanceByCodeRange(string $fromCode, string $toCode, ?string $asOf, bool $debitNature = true, ?int $costCenterId = null): float
    {
        return $this->movementByCodeRange($fromCode, $toCode, null, $asOf, debitSide: $debitNature, costCenterId: $costCenterId);
    }

    private function periodChangeByCode(string $code, ?string $from, ?string $to, ?int $costCenterId = null): float
    {
        $openingAsOf = $from
            ? (new \DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d')
            : null;
        $open = $this->balanceByPrefix($code, $openingAsOf, debitNature: true, costCenterId: $costCenterId);
        // للحسابات الدائنة بطبيعتها نستخدم نفس صافي الحركة في الفترة
        $id = ChartOfAccount::query()->where('code', $code)->first();
        if (! $id) {
            return 0.0;
        }
        $isDebit = $id->nature === ChartOfAccount::NATURE_DEBIT;
        $close = $this->balanceByPrefix($code, $to, debitNature: $isDebit, costCenterId: $costCenterId);
        $openBal = $this->balanceByPrefix($code, $openingAsOf, debitNature: $isDebit, costCenterId: $costCenterId);

        return round($close - $openBal, 2);
    }

    /** @param \Illuminate\Support\Collection<int, int>|list<int> $ids */
    private function netMovementForIds($ids, ?string $from, ?string $to, bool $creditNature, ?int $costCenterId = null): float
    {
        $ids = collect($ids)->filter()->values();
        if ($ids->isEmpty()) {
            return 0.0;
        }

        $query = JournalLine::query()->whereIn('account_id', $ids)
            ->whereHas('entry', fn ($q) => $this->periodFilter($q, $from, $to));
        if ($costCenterId) {
            $query->where('cost_center_id', $costCenterId);
        }
        $debit = (float) (clone $query)->sum('debit');
        $credit = (float) (clone $query)->sum('credit');

        return round($creditNature ? ($credit - $debit) : ($debit - $credit), 2);
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
