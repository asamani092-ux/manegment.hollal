<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\ExpenseCategory;
use App\Models\PlatformSetting;
use App\Models\RevenueCategory;
use Illuminate\Database\Seeder;

/**
 * 00-B6 — seed platform settings and default category trees. Idempotent.
 */
class PlatformSettingsSeeder extends Seeder
{
    /** @var list<array{0:string,1:?string,2:string,3:string}> key, value, type, label_ar */
    private const SETTINGS = [
        ['general.platform_name', 'منصة حلّل الإدارية', 'string', 'اسم المنصة'],
        ['general.logo_path', null, 'string', 'مسار الشعار'],
        ['general.timezone', 'Asia/Riyadh', 'string', 'المنطقة الزمنية'],
        ['notifications.task_due_days_before', '1', 'integer', 'التنبيه قبل استحقاق المهمة (أيام)'],
        ['notifications.task_escalation_hours', '48', 'integer', 'تصعيد المهمة المتأخرة (ساعات)'],
        ['notifications.contract_expiry_days', '[90,60,30]', 'json', 'تنبيهات انتهاء العقد (أيام)'],
        ['notifications.meeting_reminder_minutes', '60', 'integer', 'تذكير الاجتماع (دقائق)'],
        ['notifications.partnership_stale_days', '14', 'integer', 'ركود الشراكة (أيام)'],
        ['notifications.decision_stale_days', '30', 'integer', 'ركود القرار (أيام)'],
        ['finance.tax_rate', '0.15', 'string', 'نسبة الضريبة'],
        ['finance.currency', 'SAR', 'string', 'العملة'],
        ['finance.chain_mode', 'full', 'string', 'نمط سلسلة اعتماد المصروفات'],
        ['finance.skip_missing_dept_manager', '1', 'boolean', 'تخطي مدير القسم عند غيابه'],
        ['finance.budget_alert_threshold', '0.80', 'string', 'حد تنبيه الموازنة'],
        ['hr.evaluation_cycle', 'quarterly', 'string', 'دورة التقييم'],
        ['hr.evaluation_window_days', '14', 'integer', 'نافذة التقييم (أيام)'],
        ['hr.overtime_monthly_days', '0', 'integer', 'أيام العمل الإضافي الشهرية'],
        ['attendance.workload_threshold', '10', 'integer', 'حد عبء العمل'],
        ['links.default_expiry_days', '7', 'integer', 'مدة صلاحية الروابط الافتراضية (أيام)'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as [$key, $value, $type, $label]) {
            PlatformSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'label_ar' => $label],
            );
        }

        $this->seedExpenseCategories();
        $this->seedRevenueCategories();
        $this->seedAssetCategories();
    }

    private function seedExpenseCategories(): void
    {
        foreach (['رواتب وأجور', 'إيجارات', 'مستلزمات مكتبية', 'ضيافة', 'مواصلات', 'تدريب وتطوير', 'أخرى'] as $name) {
            ExpenseCategory::firstOrCreate(['name_ar' => $name, 'parent_id' => null]);
        }
    }

    private function seedRevenueCategories(): void
    {
        foreach (['شراكات', 'منح', 'تبرعات', 'أخرى'] as $name) {
            RevenueCategory::firstOrCreate(['name_ar' => $name, 'parent_id' => null]);
        }
    }

    private function seedAssetCategories(): void
    {
        $categories = [
            ['أجهزة حاسب', true],
            ['أجهزة عرض', true],
            ['أثاث مكتبي', false],
            ['مركبات', false],
            ['أخرى', false],
        ];

        foreach ($categories as [$name, $custody]) {
            AssetCategory::firstOrCreate(['name_ar' => $name], ['can_be_custody' => $custody]);
        }
    }
}
