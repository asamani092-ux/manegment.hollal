<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\Custody;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PayrollRun;
use App\Models\Revenue;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Support\CoaCodes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * FIN-ACC-2 — double-entry engine. Auto-posts from finalized movements; manual balanced entries for accountants.
 * Time: O(lines) | Space: O(lines)
 */
class JournalService
{
    public function accountByCode(string $code): ChartOfAccount
    {
        $account = ChartOfAccount::query()->where('code', $code)->where('is_active', true)->first();
        if (! $account) {
            throw new \RuntimeException('حساب غير موجود في الدليل: '.$code);
        }

        return $account;
    }

    /**
     * @param  list<array{account_id: int, debit?: float, credit?: float, description?: string}>  $lines
     */
    public function postManual(
        string $description,
        string $entryDate,
        array $lines,
        User $actor,
        string $status = JournalEntry::STATUS_POSTED,
    ): JournalEntry {
        return $this->createEntry(
            description: $description,
            entryDate: $entryDate,
            lines: $lines,
            source: null,
            actor: $actor,
            automatic: false,
            status: $status,
        );
    }

    public function postExpensePaid(ExpenseRequest $expense, ?User $actor = null): ?JournalEntry
    {
        if ($this->existingFor($expense)) {
            return $this->existingFor($expense);
        }

        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $expenseAccount = $expense->category?->account
            ?? $this->accountByCode(CoaCodes::EXP_MISC);
        $cashAccount = $this->cashOrBank($expense->payment_method ?? 'transfer');

        return $this->createEntry(
            description: 'صرف طلب #'.$expense->id,
            entryDate: now()->toDateString(),
            lines: [
                ['account_id' => $expenseAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => $expenseAccount->name_ar],
                ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => $cashAccount->name_ar],
            ],
            source: $expense,
            actor: $actor,
            automatic: true,
        );
    }

    public function postRevenueConfirmed(Revenue $revenue, ?User $actor = null): ?JournalEntry
    {
        if ($this->existingFor($revenue)) {
            return $this->existingFor($revenue);
        }

        $amount = round((float) $revenue->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $revenueAccount = $revenue->category?->account
            ?? $this->accountByCode(CoaCodes::UNRESTRICTED_PARTNERSHIP_REVENUE);
        $cash = $this->accountByCode(CoaCodes::CASH);

        return $this->createEntry(
            description: 'إيراد #'.$revenue->id,
            entryDate: ($revenue->received_at?->toDateString()) ?? now()->toDateString(),
            lines: [
                ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $amount],
            ],
            source: $revenue,
            actor: $actor,
            automatic: true,
        );
    }

    public function postCustodyDisbursed(Custody $custody, ?User $actor = null): ?JournalEntry
    {
        if ($this->existingFor($custody, 'D')) {
            return $this->existingFor($custody, 'D');
        }

        $amount = round((float) ($custody->disbursed_amount ?? $custody->amount), 2);
        if ($amount <= 0) {
            return null;
        }

        $advances = $this->accountByCode(CoaCodes::EMPLOYEE_ADVANCES);
        $cash = $this->accountByCode(CoaCodes::CASH);

        return $this->createEntry(
            description: 'صرف عهدة #'.$custody->id,
            entryDate: now()->toDateString(),
            lines: [
                ['account_id' => $advances->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount],
            ],
            source: $custody,
            actor: $actor,
            automatic: true,
            numberSuffix: 'D',
        );
    }

    public function postCustodySettled(Custody $custody, ?User $actor = null): ?JournalEntry
    {
        if ($this->existingFor($custody, 'S')) {
            return $this->existingFor($custody, 'S');
        }

        $custody->loadMissing(['settlementItems.category.account']);
        $items = $custody->settlementItems;
        $disbursed = round((float) ($custody->disbursed_amount ?? $custody->amount), 2);
        $itemsTotal = round((float) $items->sum(fn ($i) => (float) ($i->total_amount ?: $i->amount)), 2);
        $returned = round((float) ($custody->returned_amount ?? 0), 2);
        $claim = max(0.0, round($itemsTotal - $disbursed, 2));

        $advances = $this->accountByCode(CoaCodes::EMPLOYEE_ADVANCES);
        $vatAccount = $this->accountByCode(CoaCodes::VAT_PAYABLE);
        $cash = $this->accountByCode(CoaCodes::CASH);
        $fallbackExpense = $this->accountByCode(CoaCodes::EXP_MISC);

        $lines = [];
        $vatTotal = 0.0;

        foreach ($items as $item) {
            $net = round((float) $item->amount, 2);
            $vat = round((float) ($item->vat_amount ?? 0), 2);
            if ($net <= 0 && $vat <= 0) {
                continue;
            }
            $expenseAccount = $item->category?->account ?? $custody->category?->account ?? $fallbackExpense;
            if ($net > 0) {
                $lines[] = [
                    'account_id' => $expenseAccount->id,
                    'debit' => $net,
                    'credit' => 0,
                    'description' => $item->vendor_name ?: $item->description,
                ];
            }
            $vatTotal += $vat;
        }

        if ($vatTotal > 0) {
            $lines[] = [
                'account_id' => $vatAccount->id,
                'debit' => round($vatTotal, 2),
                'credit' => 0,
                'description' => 'ضريبة مدخلات — عهدة #'.$custody->id,
            ];
        }

        // إغلاق العهدة بالكامل
        if ($disbursed > 0) {
            $lines[] = [
                'account_id' => $advances->id,
                'debit' => 0,
                'credit' => $disbursed,
                'description' => 'إقفال عهد الموظفين',
            ];
        }

        // فرق زيادة المصروف يُصرف للموظف (دائن نقد)
        if ($claim > 0) {
            $lines[] = [
                'account_id' => $cash->id,
                'debit' => 0,
                'credit' => $claim,
                'description' => 'مطالبة فرق تسوية',
            ];
        }

        // مرتجع نقدي إن كان إجمالي الفواتير أقل من المصروف
        if ($returned > 0) {
            $lines[] = [
                'account_id' => $cash->id,
                'debit' => $returned,
                'credit' => 0,
                'description' => 'مرتجع عهدة',
            ];
        }

        if ($lines === []) {
            return null;
        }

        return $this->createEntry(
            description: 'تسوية عهدة #'.$custody->id,
            entryDate: now()->toDateString(),
            lines: $lines,
            source: $custody,
            actor: $actor,
            automatic: true,
            numberSuffix: 'S',
        );
    }

    public function postPayrollExecuted(PayrollRun $run, ?User $actor = null): ?JournalEntry
    {
        if ($this->existingFor($run)) {
            return $this->existingFor($run);
        }

        $run->loadMissing('items.employee');
        $monthEnd = \Illuminate\Support\Carbon::createFromFormat('Y-m', $run->month)->endOfMonth();

        $delegationTotal = 0.0;
        foreach ($run->items as $item) {
            $delegationTotal += (float) \App\Models\SalaryComponent::query()
                ->where('employee_id', $item->employee_id)
                ->where('type', \App\Models\SalaryComponent::TYPE_ALLOWANCE)
                ->where('label_ar', 'بدل انتداب')
                ->effectiveOn($monthEnd)
                ->sum('amount');
        }
        $delegationTotal = round($delegationTotal, 2);

        $netTotal = round((float) $run->items()->sum('net'), 2);
        if ($netTotal <= 0) {
            $netTotal = round((float) $run->items()->sum('gross'), 2);
        }
        if ($netTotal <= 0) {
            return null;
        }

        $salaries = $this->accountByCode(CoaCodes::EXP_SALARIES);
        $delegation = $this->accountByCode(CoaCodes::EXP_DELEGATION);
        $cash = $this->accountByCode(CoaCodes::CASH);

        $salaryExpense = round(max(0, $netTotal - $delegationTotal), 2);
        $lines = [];
        if ($salaryExpense > 0) {
            $lines[] = ['account_id' => $salaries->id, 'debit' => $salaryExpense, 'credit' => 0, 'description' => 'مصروف الرواتب'];
        }
        if ($delegationTotal > 0) {
            $lines[] = ['account_id' => $delegation->id, 'debit' => $delegationTotal, 'credit' => 0, 'description' => 'بدل انتداب'];
        }
        $debitSum = round(collect($lines)->sum('debit'), 2);
        $lines[] = ['account_id' => $cash->id, 'debit' => 0, 'credit' => $debitSum, 'description' => 'صرف مسير'];

        return $this->createEntry(
            description: 'مسير رواتب #'.$run->id,
            entryDate: now()->toDateString(),
            lines: $lines,
            source: $run,
            actor: $actor,
            automatic: true,
        );
    }

    public function postAssetPurchased(Asset $asset, ?User $actor = null): ?JournalEntry
    {
        if ($this->existingFor($asset)) {
            return $this->existingFor($asset);
        }

        $amount = round((float) ($asset->purchase_amount ?? 0), 2);
        if ($amount <= 0) {
            return null;
        }

        $fixed = $this->accountByCode(CoaCodes::FURNITURE);
        $cash = $this->accountByCode(CoaCodes::CASH);

        return $this->createEntry(
            description: 'شراء أصل '.$asset->code,
            entryDate: ($asset->purchase_date?->toDateString()) ?? now()->toDateString(),
            lines: [
                ['account_id' => $fixed->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount],
            ],
            source: $asset,
            actor: $actor,
            automatic: true,
        );
    }

    public function postTaxInvoice(TaxInvoice $invoice, ?User $actor = null): ?JournalEntry
    {
        if ($this->existingFor($invoice)) {
            return $this->existingFor($invoice);
        }

        $subtotal = round((float) $invoice->subtotal, 2);
        $vat = round((float) $invoice->vat_total, 2);
        $total = round((float) $invoice->total, 2);
        if ($total <= 0) {
            return null;
        }

        $cash = $this->accountByCode(CoaCodes::CASH);
        $revenue = $this->accountByCode(CoaCodes::UNRESTRICTED_PARTNERSHIP_REVENUE);
        $vatPayable = $this->accountByCode(CoaCodes::VAT_PAYABLE);

        $lines = [
            ['account_id' => $cash->id, 'debit' => $total, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $subtotal],
        ];
        if ($vat > 0) {
            $lines[] = ['account_id' => $vatPayable->id, 'debit' => 0, 'credit' => $vat];
        }

        return $this->createEntry(
            description: 'فاتورة ضريبية '.$invoice->number,
            entryDate: ($invoice->issued_at?->toDateString()) ?? now()->toDateString(),
            lines: $lines,
            source: $invoice,
            actor: $actor,
            automatic: true,
        );
    }

    public function reverse(JournalEntry $entry, ?User $actor = null): JournalEntry
    {
        if (! $entry->is_automatic) {
            throw new \RuntimeException('يُعكس القيد الآلي فقط عبر هذه الدالة؛ القيد اليدوي يُعدَّل بقيد تسوية');
        }

        $lines = $entry->lines->map(fn (JournalLine $line) => [
            'account_id' => $line->account_id,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'description' => 'عكس: '.($line->description ?? ''),
        ])->all();

        return $this->createEntry(
            description: 'عكس قيد '.$entry->number,
            entryDate: now()->toDateString(),
            lines: $lines,
            source: $entry->source,
            actor: $actor,
            automatic: true,
            numberSuffix: 'R',
        );
    }

    /**
     * @param  list<array{account_id: int, debit?: float, credit?: float, description?: ?string}>  $lines
     */
    private function createEntry(
        string $description,
        string $entryDate,
        array $lines,
        ?Model $source,
        ?User $actor,
        bool $automatic,
        string $status = JournalEntry::STATUS_POSTED,
        string $numberSuffix = '',
    ): JournalEntry {
        $normalized = [];
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException('لا يجتمع مدين ودائن في نفس السطر');
            }
            $debitSum += $debit;
            $creditSum += $credit;
            $normalized[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('القيد بلا بنود');
        }

        if (abs($debitSum - $creditSum) >= 0.005) {
            throw new \InvalidArgumentException('القيد غير متوازن: مدين '.$debitSum.' ≠ دائن '.$creditSum);
        }

        return DB::transaction(function () use ($description, $entryDate, $normalized, $source, $actor, $automatic, $status, $numberSuffix, $debitSum) {
            if ($source) {
                $existingQuery = JournalEntry::query()
                    ->where('source_type', $source->getMorphClass())
                    ->where('source_id', $source->getKey())
                    ->where('is_automatic', true);
                if ($numberSuffix !== '') {
                    $existingQuery->where('number', 'like', '%-'.$numberSuffix);
                } else {
                    $existingQuery->where('number', 'not like', '%-D')->where('number', 'not like', '%-S')->where('number', 'not like', '%-R');
                }
                if ($existing = $existingQuery->first()) {
                    return $existing;
                }
            }

            $entry = JournalEntry::create([
                'number' => $this->nextNumber($numberSuffix),
                'entry_date' => $entryDate,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'status' => $status,
                'is_automatic' => $automatic,
                'created_by' => $actor?->id,
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'journal.posted',
                'target_type' => JournalEntry::class,
                'target_id' => $entry->id,
                'ip_address' => request()->ip(),
                'metadata' => [
                    'number' => $entry->number,
                    'total' => $debitSum,
                    'automatic' => $automatic,
                ],
                'created_at' => now(),
            ]);

            return $entry->fresh(['lines']);
        });
    }

    private function cashOrBank(?string $paymentMethod): ChartOfAccount
    {
        return $this->accountByCode(
            in_array($paymentMethod, ['cash', 'نقد', 'نقدي'], true) ? CoaCodes::CASH : CoaCodes::BANK_RAJHI
        );
    }

    private function existingFor(Model $source, string $suffix = ''): ?JournalEntry
    {
        $query = JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('is_automatic', true);

        if ($suffix !== '') {
            $query->where('number', 'like', '%-'.$suffix);
        }

        return $query->first();
    }

    private function nextNumber(string $suffix = ''): string
    {
        $seq = (int) JournalEntry::withTrashed()->count() + 1;
        $base = 'JE-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        return $suffix !== '' ? $base.'-'.$suffix : $base;
    }
}
