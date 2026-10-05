<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Custody;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\Revenue;
use App\Services\JournalService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * ينشئ قيود الحركات المرحّلة التي بلا قيد. لا يُستدعى من نقطة الدخول.
 * Time: O(n) | Space: O(n)
 */
class BackfillJournal extends Command
{
    protected $signature = 'finance:backfill-journal {--dry-run : اعرض الأعداد والمجاميع دون كتابة}';

    protected $description = 'تعبئة قيود المصروف المدفوع والعهدة والمسير والإيراد والأصل';

    public function handle(JournalService $journal): int
    {
        $dry = (bool) $this->option('dry-run');
        $rows = [
            $this->lineFor('مصروف مدفوع', ExpenseRequest::query()->where('status', 'paid')->get(), function (ExpenseRequest $row) use ($journal, $dry) {
                return $this->apply($row, (float) $row->amount, $dry, fn () => $journal->postExpensePaid($row->fresh(['category.account'])));
            }),
            $this->lineFor('عهدة مصروفة', Custody::query()->whereIn('status', [Custody::STATUS_DISBURSED, Custody::STATUS_SETTLING, Custody::STATUS_CLOSED])->get(), function (Custody $row) use ($journal, $dry) {
                return $this->apply($row, (float) ($row->disbursed_amount ?? $row->amount), $dry, fn () => $journal->postCustodyDisbursed($row), 'D');
            }),
            $this->lineFor('عهدة مغلقة', Custody::query()->where('status', Custody::STATUS_CLOSED)->get(), function (Custody $row) use ($journal, $dry) {
                return $this->apply($row, (float) $row->amount, $dry, fn () => $journal->postCustodySettled($row->fresh(['settlementItems.category.account'])), 'S');
            }),
            $this->lineFor('مسير منفذ', PayrollRun::query()->where('status', PayrollRun::STATUS_EXECUTED)->get(), function (PayrollRun $row) use ($journal, $dry) {
                $total = (float) $row->items()->sum('net');

                return $this->apply($row, $total, $dry, fn () => $journal->postPayrollExecuted($row));
            }),
            $this->lineFor('إيراد مؤكد', Revenue::query()->where('status', Revenue::STATUS_CONFIRMED)->get(), function (Revenue $row) use ($journal, $dry) {
                return $this->apply($row, (float) $row->amount, $dry, fn () => $journal->postRevenueConfirmed($row->fresh(['category.account'])));
            }),
            $this->lineFor('شراء أصل', Asset::query()->where('purchase_amount', '>', 0)->get(), function (Asset $row) use ($journal, $dry) {
                return $this->apply($row, (float) $row->purchase_amount, $dry, fn () => $journal->postAssetPurchased($row->fresh('category')));
            }),
        ];

        $this->table(['النوع', 'عدد بلا قيد', 'المجموع'], array_map(fn (array $row) => [
            $row['label'],
            $row['count'],
            number_format($row['total'], 2),
        ], $rows));

        if ($dry) {
            $this->info('تشغيل جاف: لم يُكتب أي قيد.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Model>  $rows
     * @param  callable(Model): array{posted: bool, amount: float}  $each
     * @return array{label: string, count: int, total: float}
     */
    private function lineFor(string $label, $rows, callable $each): array
    {
        $count = 0;
        $total = 0.0;
        foreach ($rows as $row) {
            $result = $each($row);
            if ($result['posted']) {
                $count++;
                $total += $result['amount'];
            }
        }

        return ['label' => $label, 'count' => $count, 'total' => $total];
    }

    /** @param  callable(): mixed  $post */
    private function apply(Model $row, float $amount, bool $dry, callable $post, string $suffix = ''): array
    {
        if ($this->hasEntry($row, $suffix)) {
            return ['posted' => false, 'amount' => 0.0];
        }
        if (! $dry) {
            $post();
        }

        return ['posted' => true, 'amount' => $amount];
    }

    private function hasEntry(Model $row, string $suffix): bool
    {
        $query = JournalEntry::query()
            ->where('source_type', $row->getMorphClass())
            ->where('source_id', $row->getKey())
            ->where('is_automatic', true);
        if ($suffix !== '') {
            $query->where('number', 'like', '%-'.$suffix);
        }

        return $query->exists();
    }
}
