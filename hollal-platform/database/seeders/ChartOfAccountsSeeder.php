<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\RevenueCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FIN-ACC-1 — seed a starter chart for an association and bridge existing categories.
 * Time: O(n) | Space: O(1)
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $cash = $this->ensure('1100', 'الصندوق', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT);
            $bank = $this->ensure('1200', 'البنك', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT);
            $employeeAdvances = $this->ensure('1300', 'عهد الموظفين', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT);
            $fixedAssets = $this->ensure('1400', 'أصول ثابتة', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT);

            $this->ensure('2100', 'رواتب مستحقة', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT);
            $vatPayable = $this->ensure('2200', 'ضريبة قيمة مضافة مستحقة', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT);

            $this->ensure('3100', 'صافي الأصول', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::NATURE_CREDIT);

            $revenueRoot = $this->ensure('4100', 'إيرادات', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT);
            $expenseRoot = $this->ensure('5100', 'مصروفات تشغيلية', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT);
            $salaries = $this->ensure('5200', 'مصروف الرواتب', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT);

            unset($cash, $bank, $employeeAdvances, $fixedAssets, $vatPayable, $salaries);

            ExpenseCategory::query()->whereNull('account_id')->each(function (ExpenseCategory $category) use ($expenseRoot) {
                $category->forceFill(['account_id' => $expenseRoot->id])->save();
            });

            RevenueCategory::query()->whereNull('account_id')->each(function (RevenueCategory $category) use ($revenueRoot) {
                $category->forceFill(['account_id' => $revenueRoot->id])->save();
            });
        });
    }

    private function ensure(string $code, string $nameAr, string $type, string $nature, ?int $parentId = null): ChartOfAccount
    {
        return ChartOfAccount::withTrashed()->updateOrCreate(
            ['code' => $code],
            [
                'name_ar' => $nameAr,
                'type' => $type,
                'nature' => $nature,
                'parent_id' => $parentId,
                'is_active' => true,
                'deleted_at' => null,
            ],
        );
    }
}
