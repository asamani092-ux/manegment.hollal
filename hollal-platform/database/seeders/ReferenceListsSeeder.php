<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Services\ReferenceListService;
use Illuminate\Database\Seeder;

/**
 * Idempotent list definitions. Items are added by later commands except exclusion reasons and help.
 */
class ReferenceListsSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'leave_types' => [
                'name_ar' => 'أنواع الإجازات',
                'description_ar' => 'أنواع الإجازات القابلة للتعديل دون إعادة كتابة السجل',
                'schema' => [
                    ['name' => 'max_days_per_request', 'type' => 'integer', 'required' => false, 'label_ar' => 'الحد لكل طلب'],
                    ['name' => 'annual_entitlement_days', 'type' => 'integer', 'required' => false, 'label_ar' => 'الاستحقاق السنوي'],
                    ['name' => 'entitlement_after_5y_days', 'type' => 'integer', 'required' => false, 'label_ar' => 'الاستحقاق بعد خمس سنوات'],
                    ['name' => 'pay_tiers', 'type' => 'json', 'required' => false, 'label_ar' => 'شرائح الأجر'],
                    ['name' => 'deducts_from', 'type' => 'string', 'required' => false, 'label_ar' => 'يخصم من'],
                    ['name' => 'once_per_service', 'type' => 'boolean', 'required' => false, 'label_ar' => 'مرة واحدة'],
                    ['name' => 'min_service_months', 'type' => 'integer', 'required' => false, 'label_ar' => 'الحد الأدنى للخدمة بالأشهر'],
                    ['name' => 'requires_attachment', 'type' => 'boolean', 'required' => false, 'label_ar' => 'يتطلب مرفقاً'],
                    ['name' => 'requires_substitute', 'type' => 'boolean', 'required' => false, 'label_ar' => 'يتطلب بديلاً'],
                    ['name' => 'paid', 'type' => 'boolean', 'required' => false, 'label_ar' => 'مدفوعة'],
                ],
            ],
            'violations' => [
                'name_ar' => 'المخالفات',
                'description_ar' => 'سجل المخالفات والجزاءات',
                'schema' => [
                    ['name' => 'category', 'type' => 'string', 'required' => true, 'label_ar' => 'التصنيف'],
                    ['name' => 'description', 'type' => 'string', 'required' => false, 'label_ar' => 'الوصف'],
                    ['name' => 'penalties', 'type' => 'json', 'required' => false, 'label_ar' => 'الجزاءات'],
                    ['name' => 'auto_detectable', 'type' => 'string', 'required' => false, 'label_ar' => 'الاكتشاف الآلي'],
                    ['name' => 'late_threshold_minutes', 'type' => 'integer', 'required' => false, 'label_ar' => 'حد التأخر بالدقائق'],
                    ['name' => 'ministry_baseline', 'type' => 'json', 'required' => false, 'label_ar' => 'أساس الوزارة'],
                    ['name' => 'origin', 'type' => 'string', 'required' => false, 'label_ar' => 'المصدر'],
                ],
            ],
            'payroll_adjustment_items' => [
                'name_ar' => 'بنود تسوية المسير',
                'description_ar' => 'بنود الإضافة والحسم',
                'schema' => [
                    ['name' => 'kind', 'type' => 'string', 'required' => true, 'label_ar' => 'النوع'],
                    ['name' => 'account_code', 'type' => 'string', 'required' => false, 'label_ar' => 'رمز الحساب'],
                    ['name' => 'auto_source', 'type' => 'string', 'required' => false, 'label_ar' => 'المصدر الآلي'],
                ],
            ],
            'document_types' => [
                'name_ar' => 'أنواع الوثائق',
                'description_ar' => 'وثائق الموظف المطلوبة',
                'schema' => [
                    ['name' => 'category', 'type' => 'string', 'required' => true, 'label_ar' => 'التصنيف'],
                    ['name' => 'has_expiry', 'type' => 'boolean', 'required' => false, 'label_ar' => 'لها انتهاء'],
                    ['name' => 'renewal_notice_days', 'type' => 'integer', 'required' => false, 'label_ar' => 'أيام التنبيه'],
                    ['name' => 'required_for', 'type' => 'string', 'required' => false, 'label_ar' => 'الإلزام'],
                ],
            ],
            'onboarding_steps' => [
                'name_ar' => 'خطوات التهيئة',
                'description_ar' => 'قائمة التهيئة عند إضافة موظف',
                'schema' => [
                    ['name' => 'default_assignee_type', 'type' => 'string', 'required' => false, 'label_ar' => 'نوع المسؤول'],
                    ['name' => 'default_role', 'type' => 'string', 'required' => false, 'label_ar' => 'الدور الافتراضي'],
                    ['name' => 'default_user_id', 'type' => 'integer', 'required' => false, 'label_ar' => 'المستخدم الافتراضي'],
                    ['name' => 'link_target', 'type' => 'string', 'required' => false, 'label_ar' => 'تبويب الإكمال'],
                    ['name' => 'required', 'type' => 'boolean', 'required' => false, 'label_ar' => 'إلزامي'],
                ],
            ],
            'exclusion_reasons' => [
                'name_ar' => 'أسباب الاستبعاد',
                'description_ar' => 'أسباب استبعاد يوم أو مخالفة',
                'schema' => [],
            ],
            'help_topics' => [
                'name_ar' => 'شروحات الشاشات',
                'description_ar' => 'محتوى المساعدة داخل المنصة',
                'schema' => [
                    ['name' => 'screen_key', 'type' => 'string', 'required' => true, 'label_ar' => 'مفتاح الشاشة'],
                    ['name' => 'title_ar', 'type' => 'string', 'required' => true, 'label_ar' => 'العنوان'],
                    ['name' => 'body_ar', 'type' => 'string', 'required' => true, 'label_ar' => 'النص'],
                    ['name' => 'steps', 'type' => 'json', 'required' => false, 'label_ar' => 'الخطوات'],
                ],
            ],
        ];

        foreach ($definitions as $key => $def) {
            ReferenceList::query()->firstOrCreate(
                ['key' => $key],
                $def
            );
        }

        $service = app(ReferenceListService::class);

        $leaveTypes = [
            ['annual', 'سنوية', ['annual_entitlement_days' => 21, 'entitlement_after_5y_days' => 30, 'deducts_from' => 'own', 'paid' => true, 'requires_substitute' => true]],
            ['emergency', 'طارئة', ['max_days_per_request' => 3, 'deducts_from' => 'annual', 'paid' => true, 'requires_substitute' => true]],
            ['sick', 'مرضية', ['deducts_from' => 'own', 'paid' => true, 'requires_attachment' => true, 'pay_tiers' => [['days' => 30, 'pay_pct' => 100], ['days' => 60, 'pay_pct' => 75], ['days' => 30, 'pay_pct' => 0]]]],
            ['marriage', 'زواج', ['max_days_per_request' => 5, 'once_per_service' => true, 'paid' => true]],
            ['bereavement', 'وفاة', ['max_days_per_request' => 5, 'paid' => true]],
            ['newborn', 'مولود', ['max_days_per_request' => 3, 'paid' => true]],
            ['hajj', 'حج', ['max_days_per_request' => 15, 'once_per_service' => true, 'min_service_months' => 24, 'paid' => true]],
            ['exceptional', 'استثنائية', ['paid' => false, 'deducts_from' => 'none']],
        ];
        if (! $service->item('violations', 'draft-sample')) {
            $draft = $service->createDraft('violations', 'draft-sample', 'نموذج — للاختبار', [
                'category' => 'مواعيد العمل',
                'description' => 'نموذج — للاختبار',
                'origin' => 'ministry',
                'auto_detectable' => 'none',
                'penalties' => [],
                'ministry_baseline' => [],
            ], now()->toDateString());
        }

        foreach ([
            ['login', 'حساب الدخول'],
            ['role', 'الدور والصلاحيات'],
            ['org', 'الموقع في الهيكل'],
            ['manager', 'المدير المباشر'],
            ['fingerprint', 'رقم البصمة'],
            ['leave_opening', 'الرصيد الافتتاحي للإجازات'],
            ['salary', 'الراتب والمكونات'],
            ['documents', 'الوثائق الرسمية'],
        ] as [$code, $name]) {
            if ($service->item('onboarding_steps', $code)) {
                continue;
            }
            $draft = $service->createDraft('onboarding_steps', $code, $name, [
                'default_assignee_type' => 'direct_manager',
                'required' => true,
            ], now()->toDateString());
            $service->publish($draft, null, 'بذرة خطوات التهيئة');
        }

        foreach ($leaveTypes as [$code, $name, $attrs]) {
            if ($service->item('leave_types', $code)) {
                continue;
            }
            $draft = $service->createDraft('leave_types', $code, $name, $attrs, now()->toDateString());
            $service->publish($draft, null, 'بذرة أنواع الإجازة');
        }

        foreach ([
            ['fingerprint_error', 'خطأ في البصمة'],
            ['prior_permission', 'إذن مسبق'],
            ['external_task', 'مهمة خارجية'],
            ['device_fault', 'عطل الجهاز'],
            ['other', 'أخرى'],
        ] as [$code, $name]) {
            if ($service->item('exclusion_reasons', $code)) {
                continue;
            }
            $draft = $service->createDraft('exclusion_reasons', $code, $name, [], now()->toDateString());
            $service->publish($draft, null, 'بذرة أسباب الاستبعاد');
        }

        if (! $service->item('help_topics', 'settings.approval-chains')) {
            $draft = $service->createDraft('help_topics', 'settings.approval-chains', 'سلاسل الطلبات', [
                'screen_key' => 'settings.approval-chains',
                'title_ar' => 'سلاسل الطلبات',
                'body_ar' => 'لكل نوع طلب سلسلة واحدة تُقرأ من الأعلى إلى الأسفل. مثال: طلب صرف بـ 500 ريال يقف عند المدير المباشر، وطلب بـ 15,000 ريال يكمل إلى المالية ثم الإدارة التنفيذية لأن شرط المبلغ تحقق. غيّر الرقم من البطاقة وسترى المسار فورًا في المعاينة.',
                'steps' => ['اختر نوع الطلب', 'رتّب البطاقات', 'ضع الشرط والمبلغ', 'احفظ'],
            ], now()->toDateString());
            $service->publish($draft, null, 'بذرة شرح سلاسل الطلبات');
        }

        if (! $service->item('help_topics', 'settings.lists')) {
            $draft = $service->createDraft('help_topics', 'settings.lists', 'القوائم المرجعية', [
                'screen_key' => 'settings.lists',
                'title_ar' => 'القوائم المرجعية',
                'body_ar' => 'من هنا تُدار القوائم التي تعتمد عليها الإجازات والمخالفات والرواتب. التعديل على عنصر مستخدم ينشئ نسخة جديدة دون تغيير السجلات القديمة.',
                'steps' => ['اختر القائمة', 'أضف مسودة', 'انشرها', 'راجع السجل عند التعديل'],
            ], now()->toDateString());
            $service->publish($draft, null, 'بذرة شرح الشاشة');
        }

        foreach ([
            ['project_bonus', 'مكافأة إنجاز مشروع', ['kind' => 'earning', 'account_code' => '521', 'auto_source' => 'none']],
            ['excellence_bonus', 'مكافأة تميز', ['kind' => 'earning', 'account_code' => '521', 'auto_source' => 'none']],
            ['overtime_pay', 'أجر إضافي', ['kind' => 'earning', 'account_code' => '521', 'auto_source' => 'overtime']],
            ['delegation_allowance', 'بدل انتداب', ['kind' => 'earning', 'account_code' => '523', 'auto_source' => 'delegation_allowance']],
            ['absence_deduction', 'حسم غياب', ['kind' => 'deduction', 'account_code' => '521', 'auto_source' => 'absence']],
            ['unpaid_leave', 'حسم إجازة بدون أجر', ['kind' => 'deduction', 'account_code' => '521', 'auto_source' => 'leave_unpaid']],
            ['partial_leave', 'حسم إجازة بأجر جزئي', ['kind' => 'deduction', 'account_code' => '521', 'auto_source' => 'leave_partial']],
            ['violation_penalty', 'جزاء مخالفة', ['kind' => 'deduction', 'account_code' => '521', 'auto_source' => 'violation']],
            ['advance', 'سلفة', ['kind' => 'deduction', 'account_code' => '114', 'auto_source' => 'none']],
        ] as [$code, $name, $attrs]) {
            $this->publishIfMissing($service, 'payroll_adjustment_items', $code, $name, $attrs);
        }

        foreach ([
            ['id_card', 'هوية', ['category' => 'official', 'has_expiry' => true, 'renewal_notice_days' => 30, 'required_for' => 'all']],
            ['iqama', 'إقامة', ['category' => 'official', 'has_expiry' => true, 'renewal_notice_days' => 30, 'required_for' => 'optional']],
            ['passport', 'جواز', ['category' => 'official', 'has_expiry' => true, 'renewal_notice_days' => 30, 'required_for' => 'optional']],
            ['contract', 'عقد عمل', ['category' => 'official', 'has_expiry' => true, 'renewal_notice_days' => 30, 'required_for' => 'optional']],
            ['clearance', 'مخالصة', ['category' => 'official', 'has_expiry' => false, 'required_for' => 'optional']],
            ['other', 'أخرى', ['category' => 'other', 'has_expiry' => false, 'required_for' => 'optional']],
            ['degree', 'شهادة علمية', ['category' => 'academic', 'has_expiry' => false, 'required_for' => 'optional']],
            ['certificate', 'شهادة مهنية', ['category' => 'professional', 'has_expiry' => true, 'renewal_notice_days' => 30, 'required_for' => 'optional']],
        ] as [$code, $name, $attrs]) {
            $this->publishIfMissing($service, 'document_types', $code, $name, $attrs);
        }

        foreach ([
            ['hr.violations', 'المخالفات', 'من هنا تُستبعد المخالفة المقترحة أو تُؤكد ثم تُطلب الإفادة قبل القرار.'],
            ['hr.payroll-adjustments', 'تسويات الرواتب', 'التسوية المقترحة تُعتمد أو تُعدّل أو تُلغى أو تُؤجل، ثم تدخل المسير.'],
            ['hr.document-reviews', 'مراجعة الوثائق', 'الاعتماد يستبدل النسخة السابقة، والرفض يحتاج سبباً.'],
            ['structure.org-tree', 'الهيكل التنظيمي', 'الشجرة تعرض المواقع من الأعلى، والجدول يبقى للتعديل.'],
            ['employees.profile', 'ملف الموظف', 'كل تبويب يعرض جزءاً من الملف، والوثيقة لا تُعتمد إلا بعد المراجعة.'],
        ] as [$code, $title, $body]) {
            if ($service->item('help_topics', $code)) {
                continue;
            }
            $draft = $service->createDraft('help_topics', $code, $title, [
                'screen_key' => $code,
                'title_ar' => $title,
                'body_ar' => $body,
                'steps' => [],
            ], now()->toDateString());
            $service->publish($draft, null, 'بذرة شرح');
        }

        foreach ([
            ['hr.violations.recurrence_window_days', '180', 'integer', 'نافذة تكرار المخالفة بالأيام'],
            ['hr.violations.max_deduction_days_per_month', '5', 'integer', 'سقف أيام الحسم في الشهر'],
            ['hr.violations.detection_limit_days', '30', 'integer', 'مهلة اكتشاف المخالفة'],
            ['hr.violations.statement_deadline_workdays', '3', 'integer', 'مهلة الإفادة بأيام العمل'],
            ['hr.overtime.rate_formula', 'hourly_plus_50', 'string', 'معادلة الأجر الإضافي'],
        ] as [$key, $value, $type, $label]) {
            PlatformSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'label_ar' => $label],
            );
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function publishIfMissing(ReferenceListService $service, string $list, string $code, string $name, array $attributes): void
    {
        $exists = ReferenceItem::query()
            ->where('code', $code)
            ->whereHas('list', fn ($query) => $query->where('key', $list))
            ->exists();
        if ($exists) {
            return;
        }
        $draft = $service->createDraft($list, $code, $name, $attributes, now()->toDateString());
        $service->publish($draft, null, 'بذرة '.$list);
    }
}
