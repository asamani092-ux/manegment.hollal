<?php

/**
 * UAT tools checklist — available only while UAT_TOOLS_ENABLED is true.
 * Default: on for every non-production APP_ENV; off when publishing (production).
 *
 * التجربة مقسومة إلى 11 تبويباً بترتيب المراجعة. لا يُفتح التبويب التالي
 * حتى تُعلَّم كل أدوات التبويب الحالي «يعتمد».
 *
 * Big O: O(n) tools per save | Space: O(n) current + O(k·n) snapshots.
 */
$defaultEnabled = env('APP_ENV', 'production') !== 'production';

return [

    'enabled' => env('UAT_TOOLS_ENABLED') === null
        ? $defaultEnabled
        : filter_var(env('UAT_TOOLS_ENABLED'), FILTER_VALIDATE_BOOLEAN),

    'verdicts' => ['غير مجرّب', 'يعتمد', 'يحتاج تحسين'],

    'note_tags' => ['', 'UI ناقص', 'بيانات/تدفق', 'صلاحيات', 'أداء/أخطاء', 'نص/RTL', 'أخرى'],

    /** تقييم عبدالله 2026-08-17 15:23 — ملاحظات المرحلة 3؛ يُحمَّل افتراضيًا. */
    'baseline' => require __DIR__.'/uat_baseline_phase3.php',

    /** احتياطي: تقييم 2026-08-14 20:27 (تجربة ثانية — مرحلة 1). */
    'baseline_round4' => require __DIR__.'/uat_baseline_round4.php',

    /** احتياطي: تقييم 19:04. */
    'baseline_round3' => require __DIR__.'/uat_baseline_round3.php',

    /** احتياطي: تقييم التجربة الثانية. */
    'baseline_round2' => require __DIR__.'/uat_baseline_round2.php',

    /**
     * أحد عشر تبويباً بترتيب المراجعة اليدوية.
     * gate: التبويب N+1 يُفتح فقط عندما كل أدوات N = «يعتمد».
     */
    'phases' => [
        [
            'id' => 1,
            'title' => 'التبويب 1 — الموارد البشرية',
            'goal' => 'الأساس · الدخول · دليل العاملين · تقييم ربعي موحّد · الحضور · الإجازات · دورة الحياة',
            'group_ids' => ['foundation', 'hr'],
        ],
        [
            'id' => 2,
            'title' => 'التبويب 2 — إسناد',
            'goal' => 'المهام · متابعة الفريق · التقويم',
            'group_ids' => ['esnad'],
        ],
        [
            'id' => 3,
            'title' => 'التبويب 3 — الاجتماعات',
            'goal' => 'جدولة · محضر · قرارات · أرشيف',
            'group_ids' => ['meetings'],
        ],
        [
            'id' => 4,
            'title' => 'التبويب 4 — المستندات',
            'goal' => 'مستودع · قوالب · نسخ · سياسات · أرشيف المحاضر',
            'group_ids' => ['documents'],
        ],
        [
            'id' => 5,
            'title' => 'التبويب 5 — التقارير',
            'goal' => 'مركز التقارير · أسبوعي · سجل النشاط',
            'group_ids' => ['reports'],
        ],
        [
            'id' => 6,
            'title' => 'التبويب 6 — الهيكلة',
            'goal' => 'هيكل (إدارة/قسم/وظيفة) · وظائف · لجان · ملف وظيفي',
            'group_ids' => ['structure'],
        ],
        [
            'id' => 7,
            'title' => 'التبويب 7 — الأدوار والصلاحيات',
            'goal' => 'أدوار · صلاحيات · استثناءات',
            'group_ids' => ['roles'],
        ],
        [
            'id' => 8,
            'title' => 'التبويب 8 — إعدادات المنصة',
            'goal' => 'عامة · مالية · SMTP · نسخ احتياطي',
            'group_ids' => ['settings'],
        ],
        [
            'id' => 9,
            'title' => 'التبويب 9 — المالية',
            'goal' => 'صرف · عهد · أصول · إيرادات · محاسبة · تقارير',
            'group_ids' => ['finance'],
        ],
        [
            'id' => 10,
            'title' => 'التبويب 10 — الشراكات',
            'goal' => 'جهات · تشخيص · رحلة · عقود · بوابة الشريك',
            'group_ids' => ['partnerships'],
        ],
        [
            'id' => 11,
            'title' => 'التبويب 11 — المشاريع',
            'goal' => 'محفظة · برامج · قوالب · تنفيذ · زيارات · قياس',
            'group_ids' => ['projects'],
        ],
    ],

    'groups' => [
        [
            'id' => 'foundation',
            'phase' => 1,
            'title' => 'أساسيات المنصة (قبل الموارد البشرية)',
            'items' => [
                ['id' => 'sidebar', 'tool' => 'الشريط الجانبي', 'path' => 'أي صفحة', 'checks' => 'طي الشريط · بحث القوائم · جرس التنبيهات · تجربة الجوال', 'lifecycle' => 'تنقّل → فتح أداة → بقاء الحالة بعد التحديث'],
                ['id' => 'login', 'tool' => 'تسجيل الدخول', 'path' => '/login', 'checks' => 'جوال + كلمة مرور · تذكرني · رسالة خطأ عربية', 'lifecycle' => 'دخول → تغيير كلمة المرور (أول مرة) → لوحة التحكم'],
                ['id' => 'change-password', 'tool' => 'تغيير كلمة المرور', 'path' => '/change-password', 'checks' => 'الإلزام عند أول دخول · رفض المطابقة للقديمة', 'lifecycle' => 'إلزام → تعيين جديدة → إعادة توجيه'],
                ['id' => 'dashboard', 'tool' => 'الرئيسية', 'path' => '/dashboard', 'checks' => 'يحتاج تدخلك قابل للطي · مؤشرات الدور · بلا خطأ', 'lifecycle' => 'عرض → طي/فتح الأقسام → روابط سريعة'],
            ],
        ],
        [
            'id' => 'hr',
            'phase' => 1,
            'title' => 'الموارد البشرية',
            'items' => [
                ['id' => 'users', 'tool' => 'دليل العاملين', 'path' => '/users', 'checks' => 'قوائم إدارة→قسم→وظيفة من الهيكل (اختيارية) · بلا أقسام منفصلة · ملف · بحث', 'lifecycle' => 'إنشاء موظف → ربط هيكل → ملف وظيفي → تفعيل/تعطيل'],
                ['id' => 'contracts-hr', 'tool' => 'عقود العاملين', 'path' => '/contracts', 'checks' => 'مُدمج في الملف الوظيفي — الشريط مخفي · تخطّى', 'lifecycle' => '— (تخطّى — انظر الملف الوظيفي)'],
                ['id' => 'pay-scales', 'tool' => 'سلم الرواتب', 'path' => '/pay-scales', 'checks' => 'إنشاء سلم · مكونات · hr.salaries.manage', 'lifecycle' => 'تعريف سلم → مكونات → ربط بالموظف'],
                ['id' => 'payroll-runs', 'tool' => 'مسيّرات الرواتب', 'path' => '/payroll-runs', 'checks' => 'إعداد مسير شهر · فلتر · ترقيم · رفع للمالية', 'lifecycle' => 'إعداد → مراجعة → اعتماد → إرسال للمالية'],
                ['id' => 'eval-templates', 'tool' => 'قوالب التقييم', 'path' => '/evaluations?step=template', 'checks' => 'مدمج في /evaluations (خطوة قالب) · أوزان = 100 · بنود مدير/موارد · بلا رابط شريط منفصل', 'lifecycle' => '— (مدمج — انظر التقييم الربعي)'],
                ['id' => 'eval-cycles', 'tool' => 'دورات التقييم', 'path' => '/evaluations?step=cycle', 'checks' => 'مدمج في /evaluations (خطوة دورة) · فتح ربع · لقطة ثابتة · بلا رابط شريط منفصل', 'lifecycle' => '— (مدمج — انظر التقييم الربعي)'],
                ['id' => 'evaluations', 'tool' => 'التقييم الربعي', 'path' => '/evaluations', 'checks' => 'شاشة واحدة بخطوات · اعتماد جماعي · نيابة موارد · بلا روابط قوالب/دورات منفصلة في الشريط · فتح جماعي · أرشفة', 'lifecycle' => 'قالب → دورة → فتح جماعي → تعبئة → اعتماد جماعي → إغلاق/أرشفة'],
                ['id' => 'team-evaluations', 'tool' => 'تقييمات فريقي', 'path' => '/evaluations?step=score', 'checks' => 'مدمج في /evaluations (خطوة تعبئة) · فريق المدير · بلا رابط شريط منفصل', 'lifecycle' => '— (مدمج — انظر التقييم الربعي)'],
                ['id' => 'my-evaluations', 'tool' => 'أرشيف تقييماتي', 'path' => '/evaluations', 'checks' => 'مدمج في /evaluations والملف (السجل) · يظهر بعد الاعتماد · بلا رابط شريط منفصل', 'lifecycle' => '— (مدمج — انظر التقييم الربعي / الملف)'],
                ['id' => 'responsibilities', 'tool' => 'المسؤوليات', 'path' => '/responsibilities', 'checks' => 'منفصلة عن التقييم الربعي · إضافة بند · إيقاف · ترتيب', 'lifecycle' => 'تعريف → تعيين → إيقاف (إعادة هيكلة لاحقاً)'],
                ['id' => 'attendance', 'tool' => 'الحضور (برنامج التحضير)', 'path' => '/attendance', 'checks' => 'تبويبات داخل الصفحة (تفعيل · ورديات · باركود · اعتماد معلّق · سجل · طباعة) · زر هيدر · عمل إضافي للعرض فقط', 'lifecycle' => 'إسناد وردية → تفعيل → تسجيل → اعتماد عن بعد/ميداني → تقرير'],
                ['id' => 'attendance-cycle', 'tool' => 'الحضور الشهري (استيراد)', 'path' => '/attendance/cycle', 'checks' => 'أقسام موحّدة (رفع · يدوي · تقرير · خصم) · رفع يستبدل · مطابقة أعمدة · اعتماد خصم · تصحيح بسبب', 'lifecycle' => 'رفع/إدخال → تقرير → اعتماد خصم → تطبيق مسير'],
                ['id' => 'leaves', 'tool' => 'الإجازات', 'path' => '/leaves', 'checks' => 'تقديم · منع التداخل · حجز رصيد · اعتماد مدير', 'lifecycle' => 'طلب → اعتماد/رفض → ظهور بالتقويم → خصم الرصيد'],
                ['id' => 'hr-lifecycle', 'tool' => 'التهيئة وإنهاء العلاقة', 'path' => '/hr-lifecycle', 'checks' => 'موانع · مهام قابلة للمتابعة · تراجع · تجميد', 'lifecycle' => 'تهيئة → مهام → إنهاء/تراجع'],
            ],
        ],
        [
            'id' => 'esnad',
            'phase' => 2,
            'title' => 'إسناد',
            'items' => [
                ['id' => 'tasks', 'tool' => 'المهام', 'path' => '/tasks', 'checks' => 'تبديل بطاقات/جدول · مكتملة منفصلة · بانتظار اعتمادي · إنشاء · مرفقات · ?open=', 'lifecycle' => 'إنشاء → إسناد → تنفيذ → مراجعة → اعتماد'],
                ['id' => 'team-followup', 'tool' => 'متابعة الفريق', 'path' => '/team-tasks', 'checks' => 'اعتماد · مهام الفريق · متأخرة · أحمال · قوالب ومتابعة · صلاحية team.view', 'lifecycle' => 'عرض فريق → أحمال → تذكير → اعتماد'],
                ['id' => 'calendar', 'tool' => 'تقويم المهام', 'path' => '/tasks-calendar', 'checks' => 'حالة عربية · تعديل موعد · رقائق محدودة +N · تنقّل الأشهر', 'lifecycle' => 'عرض شهري → فتح مهمة → تعديل موعد'],
            ],
        ],
        [
            'id' => 'meetings',
            'phase' => 3,
            'title' => 'الاجتماعات',
            'items' => [
                ['id' => 'meetings', 'tool' => 'الاجتماعات', 'path' => '/meetings', 'checks' => 'إنشاء · جدول أعمال · حضور · بطاقة 12 ساعة', 'lifecycle' => 'جدولة → انعقاد → محضر → اعتماد'],
                ['id' => 'minutes', 'tool' => 'محضر الاجتماع', 'path' => '/meetings/{id}/minutes', 'checks' => 'قرار من جدول الأعمال · اعتماد · PDF عربي PdfArabic · رفع موقعة', 'lifecycle' => 'تدوين → قرارات → اعتماد → PDF'],
                ['id' => 'open-decisions', 'tool' => 'القرارات المفتوحة', 'path' => '/meetings/open-decisions', 'checks' => 'تجميع حسب اجتماع · إغلاق · مزامنة اكتمال المهمة', 'lifecycle' => 'اجتماع → قراراته → إغلاق/مهمة'],
            ],
        ],
        [
            'id' => 'documents',
            'phase' => 4,
            'title' => 'المستندات',
            'items' => [
                ['id' => 'docs', 'tool' => 'المستودع', 'path' => '/documents', 'checks' => 'رفع · سرية · معاينة · تنزيل عربي', 'lifecycle' => 'رفع → تصنيف → مشاركة → تنزيل'],
                ['id' => 'doc-templates', 'tool' => 'مكتبة القوالب', 'path' => '/documents/templates', 'checks' => 'رفع · ظهور · معاينة · تنزيل', 'lifecycle' => 'رفع قالب → اعتماد → استخدام'],
                ['id' => 'versions', 'tool' => 'إدارة النسخ', 'path' => '/documents/versions', 'checks' => 'رفع نسخة · شارة الحالية · تنزيل قديمة', 'lifecycle' => 'نسخة جديدة → مقارنة → أرشفة قديمة'],
                ['id' => 'policies', 'tool' => 'السياسات', 'path' => '/documents/policies', 'checks' => 'سياسات · تنزيل · ملف مهام واحد للمنصة', 'lifecycle' => 'نشر → مراجعة دورية → تحديث'],
                ['id' => 'minutes-arch', 'tool' => 'أرشيف المحاضر', 'path' => '/meetings/archive', 'checks' => 'طلب ← موافقة ← تعديل بنود ← اعتماد · نسخة موسومة · معاينة الأصل', 'lifecycle' => 'أرشفة → طلب تعديل → تحرير → اعتماد → تنزيل'],
            ],
        ],
        [
            'id' => 'reports',
            'phase' => 5,
            'title' => 'التقارير',
            'items' => [
                ['id' => 'center', 'tool' => 'مركز التقارير', 'path' => '/reports/center', 'checks' => 'شهري · مشاريع · أثر · مؤشرات', 'lifecycle' => 'اختيار نوع → توليد → تصدير'],
                ['id' => 'weekly', 'tool' => 'التقرير الأسبوعي', 'path' => '/reports', 'checks' => 'عرض · توليد', 'lifecycle' => 'توليد أسبوعي → مراجعة → مشاركة'],
                ['id' => 'audit', 'tool' => 'سجل النشاط', 'path' => '/reports/audit-log', 'checks' => 'أحداث · فلترة · من نفّذ', 'lifecycle' => 'تسجيل تلقائي → فلترة → تصدير'],
            ],
        ],
        [
            'id' => 'structure',
            'phase' => 6,
            'title' => 'الهيكلة',
            'items' => [
                ['id' => 'org', 'tool' => 'الهيكل التنظيمي', 'path' => '/structure/org-tree', 'checks' => 'إدارة ← قسم ← وظيفة (لا وحدة · لا صفحة أقسام) · نقل موظف', 'lifecycle' => 'بناء هيكل → نقل → تحديث'],
                ['id' => 'jobs', 'tool' => 'الوظائف', 'path' => '/structure/org-tree?tab=jobs', 'checks' => 'بطاقة وظيفية · القسم الأب · المسؤول المباشر', 'lifecycle' => 'تعريف وظيفة → ربط موظف'],
                ['id' => 'committees', 'tool' => 'اللجان', 'path' => '/structure/org-tree?tab=committees', 'checks' => 'رئيس اللجنة · التفويض', 'lifecycle' => 'تشكيل → تفويض → انتهاء'],
                ['id' => 'profile', 'tool' => 'الملف الوظيفي', 'path' => '/users/{id}/profile', 'checks' => 'عقود+مستندات · راتب في الوظيفة · تقييمات في السجل · بيانات · إجازات', 'lifecycle' => 'عرض → تحديث → سجل تاريخي'],
            ],
        ],
        [
            'id' => 'roles',
            'phase' => 7,
            'title' => 'الأدوار والصلاحيات',
            'items' => [
                ['id' => 'roles', 'tool' => 'الأدوار والصلاحيات (مُدمَج)', 'path' => '/settings/grants', 'checks' => 'تبويب الأدوار: إنشاء/إعادة تسمية/حذف · أسماء معرّبة', 'lifecycle' => 'تعريف دور → صلاحيات → تعيين'],
                ['id' => 'grants', 'tool' => 'صلاحيات واستثناءات (مُدمَج)', 'path' => '/settings/grants', 'checks' => 'تبويب صلاحيات · استثناءات بسبب وتاريخ', 'lifecycle' => 'منح → استثناء → مراجعة → إلغاء'],
            ],
        ],
        [
            'id' => 'settings',
            'phase' => 8,
            'title' => 'إعدادات المنصة',
            'items' => [
                ['id' => 'settings', 'tool' => 'عامة', 'path' => '/settings', 'checks' => 'إعدادات عامة · بداية الدوام · نسخ احتياطي', 'lifecycle' => 'تعديل → حفظ → تحقق'],
                ['id' => 'exp-settings', 'tool' => 'إعدادات المالية', 'path' => '/settings/expenses', 'checks' => 'سلسلة الاعتماد · التصنيفات · الضريبة', 'lifecycle' => 'تعريف سلسلة → تصنيفات → تفعيل'],
                ['id' => 'smtp', 'tool' => 'SMTP', 'path' => '/settings/notifications', 'checks' => 'بريد · تفضيلات الإشعارات', 'lifecycle' => 'إعداد SMTP → اختبار → تفعيل'],
            ],
        ],
        [
            'id' => 'finance',
            'phase' => 9,
            'title' => 'المالية',
            'items' => [
                ['id' => 'expenses', 'tool' => 'طلبات الصرف', 'path' => '/expenses', 'checks' => 'طلب · سلسلة اعتماد · صرف · إرجاع · مرفق', 'lifecycle' => 'طلب → اعتماد → صرف → إثبات'],
                ['id' => 'custodies', 'tool' => 'العهد', 'path' => '/custodies', 'checks' => 'طلب · اعتماد · صرف · إثبات · بطاقات الجوال', 'lifecycle' => 'طلب → صرف → تسوية'],
                ['id' => 'assets', 'tool' => 'الأصول', 'path' => '/assets', 'checks' => 'تسجيل · تسليم · بحث · طباعة · Excel', 'lifecycle' => 'تسجيل → تسليم → إرجاع/استبعاد'],
                ['id' => 'revenues', 'tool' => 'الإيرادات', 'path' => '/revenues', 'checks' => 'تسجيل · مرفق إلزامي · فلتر المصدر', 'lifecycle' => 'تسجيل → مرفق → اعتماد'],
                ['id' => 'tax', 'tool' => 'الفواتير الضريبية', 'path' => '/tax-invoices', 'checks' => 'إصدار · إشعار دائن/مدين · PDF عربي', 'lifecycle' => 'إصدار → طباعة → إشعار تعديل'],
                ['id' => 'budgets', 'tool' => 'الميزانيات', 'path' => '/budgets', 'checks' => 'موازنات المشاريع · تجاوزات', 'lifecycle' => 'تخصيص → صرف → تنبيه تجاوز'],
                ['id' => 'chart-of-accounts', 'tool' => 'دليل الحسابات', 'path' => '/chart-of-accounts', 'checks' => 'شجرة حسابات · إنشاء · تعديل · تفعيل', 'lifecycle' => 'تعريف → ربط → تفعيل'],
                ['id' => 'journal-entries', 'tool' => 'القيود اليومية', 'path' => '/journal-entries', 'checks' => 'قيد يدوي · توازن مدين/دائن · اعتماد', 'lifecycle' => 'إنشاء → مراجعة → ترحيل'],
                ['id' => 'accounting-reports', 'tool' => 'الدفاتر والقوائم', 'path' => '/accounting-reports', 'checks' => 'ميزان مراجعة · قائمة دخل · ميزانية', 'lifecycle' => 'فترة → توليد → تصدير'],
                ['id' => 'accounting-close', 'tool' => 'مراكز التكلفة والإقفال', 'path' => '/accounting-close', 'checks' => 'مراكز تكلفة · إقفال فترة · منع تعديل', 'lifecycle' => 'إقفال شهري → مراجعة → قفل'],
                ['id' => 'fin-reports', 'tool' => 'التقرير المالي', 'path' => '/financial-reports', 'checks' => 'توليد · طباعة · Excel', 'lifecycle' => 'فلتر → توليد → تصدير'],
                ['id' => 'fin-docs', 'tool' => 'المستندات المالية', 'path' => '/financial-documents', 'checks' => 'فهرس المستندات المالية', 'lifecycle' => 'رفع → فهرسة → تنزيل'],
            ],
        ],
        [
            'id' => 'partnerships',
            'phase' => 10,
            'title' => 'الشراكات',
            'items' => [
                ['id' => 'orgs', 'tool' => 'الجهات الشريكة', 'path' => '/organizations', 'checks' => 'إنشاء جهة · بحث', 'lifecycle' => 'تسجيل جهة → ملف → شراكات'],
                ['id' => 'diagnosis-questions', 'tool' => 'أسئلة التشخيص', 'path' => '/partnerships/diagnosis-questions', 'checks' => 'إدارة أسئلة · ترتيب · تفعيل', 'lifecycle' => 'تعريف → ترتيب → استخدام بالتشخيص'],
                ['id' => 'org-show', 'tool' => 'ملف الجهة', 'path' => '/organizations/{id}', 'checks' => 'الشراكات المرتبطة · جهات الاتصال', 'lifecycle' => 'عرض → تحديث → ربط شراكة'],
                ['id' => 'pipeline', 'tool' => 'رحلة الشراكات', 'path' => '/partnerships/pipeline', 'checks' => 'المراحل · نقل شراكة · فتح ملف', 'lifecycle' => 'تشخيص → تفاوض → عقد → تنفيذ'],
                ['id' => 'p-contract', 'tool' => 'عقد الشراكة (داخل الصفحة)', 'path' => '/partnerships/{id}', 'checks' => 'عقد الشراكة · بصمة · عروض · دفعات', 'lifecycle' => 'عرض سعر → عقد → دفعات'],
                ['id' => 'portal', 'tool' => 'بوابة الشريك', 'path' => '/portal/{token}', 'checks' => 'فتح برابط · عرض PDF · لا تسريب', 'lifecycle' => 'إرسال رابط → دخول شريك → عرض'],
            ],
        ],
        [
            'id' => 'projects',
            'phase' => 11,
            'title' => 'المشاريع',
            'items' => [
                ['id' => 'projects', 'tool' => 'محفظة المشاريع', 'path' => '/projects', 'checks' => 'قائمة · مشروع جديد · فلاتر', 'lifecycle' => 'إنشاء → تعيين فريق → متابعة'],
                ['id' => 'project-show', 'tool' => 'ملف المشروع', 'path' => '/projects/{id}', 'checks' => 'تبويبات · فريق · ميزانية', 'lifecycle' => 'عرض → تحديث → أرشفة'],
                ['id' => 'project-exec', 'tool' => 'تنفيذ المشروع', 'path' => '/projects/{id}/execution', 'checks' => 'خطة · نسبة إنجاز · زيارات · إغلاق', 'lifecycle' => 'خطة → تنفيذ → زيارات → إغلاق'],
                ['id' => 'programs', 'tool' => 'مكتبة البرامج', 'path' => '/programs', 'checks' => 'برامج · أسعار · ملفات', 'lifecycle' => 'تعريف برنامج → تسعير → توليد مشروع'],
                ['id' => 'program-show', 'tool' => 'بطاقة البرنامج', 'path' => '/programs/{id}', 'checks' => 'تفاصيل · توليد مشروع', 'lifecycle' => 'عرض → تعديل → توليد'],
                ['id' => 'templates', 'tool' => 'محرر القوالب', 'path' => '/plan-templates', 'checks' => 'مستويات · بنود بانتظار المراجعة', 'lifecycle' => 'قالب → بنود → اعتماد'],
                ['id' => 'visits', 'tool' => 'الزيارات', 'path' => '/visits', 'checks' => 'فلتر المشروع · رابط التنفيذ · بطاقات الجوال', 'lifecycle' => 'جدولة → تنفيذ → تقرير'],
                ['id' => 'measurement', 'tool' => 'القياس والأثر', 'path' => '/measurement', 'checks' => 'نماذج القياس · فلتر البرنامج', 'lifecycle' => 'نموذج → جمع بيانات → تقرير أثر'],
            ],
        ],
    ],

];
