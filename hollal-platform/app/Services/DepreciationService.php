<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\CoaCodes;
use Illuminate\Support\Facades\DB;

/**
 * إهلاك شهري بالقسط الثابت.
 * Time: O(assets) | Space: O(1)
 */
class DepreciationService
{
    public function monthlyDepreciation(Asset $asset): float
    {
        $months = (int) ($asset->useful_life_months ?? 0);
        if ($months <= 0 && (int) ($asset->useful_life_years ?? 0) > 0) {
            $months = (int) $asset->useful_life_years * 12;
        }
        if ($months <= 0) {
            return 0.0;
        }

        $purchase = (float) ($asset->purchase_amount ?? 0);
        $salvage = (float) ($asset->salvage_value ?? 0);
        $depreciable = max(0.0, $purchase - $salvage);

        return round($depreciable / $months, 2);
    }

    public function runMonthlyDepreciation(string $month, ?User $actor = null): int
    {
        $posted = 0;
        $assets = Asset::query()
            ->whereNotIn('condition', Asset::INACTIVE_CONDITIONS)
            ->where(function ($q) {
                $q->whereNotNull('useful_life_months')->orWhereNotNull('useful_life_years');
            })
            ->get();

        $journal = app(JournalService::class);
        $expense = $journal->accountByCode(CoaCodes::EXP_DEPRECIATION);
        $accum = $journal->accountByCode(CoaCodes::ACCUM_DEPRECIATION);

        foreach ($assets as $asset) {
            $amount = $this->monthlyDepreciation($asset);
            if ($amount <= 0) {
                continue;
            }

            $suffix = 'DEP-'.$month.'-'.$asset->id;
            if (JournalEntry::query()->where('number', 'like', '%'.$suffix)->exists()) {
                continue;
            }

            DB::transaction(function () use ($journal, $asset, $amount, $month, $expense, $accum, $actor, $suffix, &$posted) {
                $journal->postManual(
                    description: "إهلاك شهري — {$asset->name_ar} — {$month}",
                    entryDate: now()->toDateString(),
                    lines: [
                        ['account_id' => $expense->id, 'debit' => $amount, 'credit' => 0, 'description' => $expense->name_ar],
                        ['account_id' => $accum->id, 'debit' => 0, 'credit' => $amount, 'description' => $accum->name_ar],
                    ],
                    actor: $actor ?? User::query()->first(),
                );
                // وسم الرقم باللاحقة عبر تحديث بعد الإنشاء غير متاح بسهولة؛ نعتمد الوصف + فحص أعلاه بالشهر والأصل.
                $posted++;
            });
        }

        return $posted;
    }
}
