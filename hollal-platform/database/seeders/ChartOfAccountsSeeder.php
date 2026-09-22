<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\RevenueCategory;
use App\Support\CoaCodes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * دليل حسابات ثلاثي الخانات — منشأة غير ربحية.
 * المستوى 1–2 تجميعي (غير قابل للقيد)، المستوى 3 قابل للقيد.
 * Time: O(n) | Space: O(1)
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // إزالة الرموز الرباعية القديمة إن وُجدت (بيئة غير fresh).
            ChartOfAccount::withTrashed()
                ->whereIn('code', ['1100', '1200', '1300', '1400', '2100', '2200', '3100', '4100', '5100', '5200'])
                ->forceDelete();

            $a1 = $this->ensure('1', 'الأصول', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, null, false);
            $a11 = $this->ensure('11', 'الأصول المتداولة', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a1->id, false);
            $cash = $this->ensure(CoaCodes::CASH, 'الصندوق', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a11->id, true);
            $this->ensure(CoaCodes::BANK_RAJHI, 'بنك الراجحي', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a11->id, true);
            $this->ensure(CoaCodes::BANK_INMA, 'بنك الإنماء', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a11->id, true);
            $this->ensure(CoaCodes::EMPLOYEE_ADVANCES, 'عهد الموظفين', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a11->id, true);
            $this->ensure(CoaCodes::RECEIVABLES, 'ذمم مدينة', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a11->id, true);

            $a12 = $this->ensure('12', 'الأصول الثابتة', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a1->id, false);
            $this->ensure(CoaCodes::FURNITURE, 'أثاث ومعدات', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a12->id, true);
            $this->ensure(CoaCodes::COMPUTERS, 'أجهزة حاسب', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_DEBIT, $a12->id, true);
            $this->ensure(CoaCodes::ACCUM_DEPRECIATION, 'مجمّع الإهلاك', ChartOfAccount::TYPE_ASSETS, ChartOfAccount::NATURE_CREDIT, $a12->id, true);

            $l2 = $this->ensure('2', 'الخصوم', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT, null, false);
            $l21 = $this->ensure('21', 'الخصوم المتداولة', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT, $l2->id, false);
            $this->ensure(CoaCodes::SALARIES_PAYABLE, 'رواتب مستحقة', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT, $l21->id, true);
            $this->ensure(CoaCodes::VAT_PAYABLE, 'ضريبة قيمة مضافة مستحقة', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT, $l21->id, true);
            $this->ensure(CoaCodes::SOCIAL_INSURANCE_PAYABLE, 'تأمينات اجتماعية مستحقة', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT, $l21->id, true);
            $this->ensure(CoaCodes::DEFERRED_REVENUE, 'إيرادات مؤجلة', ChartOfAccount::TYPE_LIABILITIES, ChartOfAccount::NATURE_CREDIT, $l21->id, true);

            $e3 = $this->ensure('3', 'حقوق الملكية (صافي الأصول)', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::NATURE_CREDIT, null, false);
            $e31 = $this->ensure('31', 'صافي الأصول غير المقيّدة', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::NATURE_CREDIT, $e3->id, false);
            $this->ensure(CoaCodes::UNRESTRICTED_NET_ASSETS, 'صافي أصول غير مقيّد', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::NATURE_CREDIT, $e31->id, true);
            $e32 = $this->ensure('32', 'صافي الأصول المقيّدة', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::NATURE_CREDIT, $e3->id, false);
            $this->ensure(CoaCodes::TEMP_RESTRICTED_NET_ASSETS, 'صافي أصول مقيّد مؤقتاً', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::NATURE_CREDIT, $e32->id, true);
            $this->ensure(CoaCodes::PERM_RESTRICTED_NET_ASSETS, 'صافي أصول مقيّد دائماً', ChartOfAccount::TYPE_EQUITY, ChartOfAccount::NATURE_CREDIT, $e32->id, true);

            $r4 = $this->ensure('4', 'الإيرادات', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, null, false);
            $r41 = $this->ensure('41', 'إيرادات غير مقيّدة', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, $r4->id, false);
            $revDefault = $this->ensure(CoaCodes::UNRESTRICTED_PARTNERSHIP_REVENUE, 'إيرادات شراكات غير مقيّدة', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, $r41->id, true);
            $this->ensure(CoaCodes::UNRESTRICTED_SERVICE_REVENUE, 'إيرادات خدمات غير مقيّدة', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, $r41->id, true);
            $this->ensure(CoaCodes::UNRESTRICTED_OTHER_REVENUE, 'إيرادات أخرى غير مقيّدة', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, $r41->id, true);
            $r42 = $this->ensure('42', 'إيرادات مقيّدة', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, $r4->id, false);
            $this->ensure(CoaCodes::RESTRICTED_PARTNERSHIP_REVENUE, 'إيرادات شراكات مقيّدة', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, $r42->id, true);
            $this->ensure(CoaCodes::RESTRICTED_GRANT_REVENUE, 'إيرادات منح مقيّدة', ChartOfAccount::TYPE_REVENUE, ChartOfAccount::NATURE_CREDIT, $r42->id, true);

            $x5 = $this->ensure('5', 'المصروفات', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, null, false);
            $x51 = $this->ensure('51', 'مصروفات تشغيلية', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x5->id, false);
            $this->ensure(CoaCodes::EXP_BAGS, 'مصروفات حقائب تعليمية', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);
            $this->ensure(CoaCodes::EXP_TRAINING, 'مصروفات تدريب', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);
            $this->ensure(CoaCodes::EXP_VISITS, 'مصروفات زيارات', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);
            $this->ensure(CoaCodes::EXP_CONSULTING, 'مصروفات استشارات', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);
            $this->ensure(CoaCodes::EXP_LOGISTICS, 'مصروفات لوجستية وشحن', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);
            $this->ensure(CoaCodes::EXP_TECH, 'مصروفات تقنية', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);
            $this->ensure(CoaCodes::EXP_MEDIA, 'مصروفات إعلام وتسويق', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);
            $expDefault = $this->ensure(CoaCodes::EXP_MISC, 'مصروفات متنوعة', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x51->id, true);

            $x52 = $this->ensure('52', 'مصروفات الرواتب والموظفين', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x5->id, false);
            $this->ensure(CoaCodes::EXP_SALARIES, 'مصروف الرواتب', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x52->id, true);
            $this->ensure(CoaCodes::EXP_INSURANCE, 'مصروف التأمينات (حصة الشركة)', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x52->id, true);
            $this->ensure(CoaCodes::EXP_DELEGATION, 'مصروف بدل انتداب', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x52->id, true);

            $x53 = $this->ensure('53', 'مصروفات إدارية', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x5->id, false);
            $this->ensure(CoaCodes::EXP_RENT, 'إيجارات', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x53->id, true);
            $this->ensure(CoaCodes::EXP_OFFICE, 'مصروفات مكتبية', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x53->id, true);
            $this->ensure(CoaCodes::EXP_DEPRECIATION, 'مصروف إهلاك', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::NATURE_DEBIT, $x53->id, true);

            unset($cash);

            ExpenseCategory::query()->whereNull('account_id')->each(function (ExpenseCategory $category) use ($expDefault) {
                $category->forceFill(['account_id' => $expDefault->id])->save();
            });

            RevenueCategory::query()->whereNull('account_id')->each(function (RevenueCategory $category) use ($revDefault) {
                $category->forceFill(['account_id' => $revDefault->id])->save();
            });
        });
    }

    private function ensure(
        string $code,
        string $nameAr,
        string $type,
        string $nature,
        ?int $parentId,
        bool $isPostable,
    ): ChartOfAccount {
        return ChartOfAccount::withTrashed()->updateOrCreate(
            ['code' => $code],
            [
                'name_ar' => $nameAr,
                'type' => $type,
                'nature' => $nature,
                'parent_id' => $parentId,
                'is_postable' => $isPostable,
                'is_active' => true,
                'deleted_at' => null,
            ],
        );
    }
}
