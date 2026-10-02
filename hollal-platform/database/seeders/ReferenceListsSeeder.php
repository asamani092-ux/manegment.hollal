<?php

namespace Database\Seeders;

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
                'body_ar' => 'كل خطوة يمكن أن تكون أي موظف بالاسم. الإنابة تنقل صلاحيات المفوِّض طوال مدتها.',
                'steps' => ['اختر نوع العملية', 'أضف شريحة', 'اختر موظفاً في الخطوة'],
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
    }
}
