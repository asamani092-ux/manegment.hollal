<?php

/**
 * UAT tools checklist — available only while UAT_TOOLS_ENABLED is true.
 * Default: on for every non-production APP_ENV; off when publishing (production).
 *
 * التجربة مقسومة إلى 3 مراحل متوازنة. لا تُفتح المرحلة التالية حتى تُعلَّم
 * كل أدوات المرحلة الحالية «يعتمد» (لا «غير مجرّب» ولا «يحتاج تحسين»).
 */
$defaultEnabled = env('APP_ENV', 'production') !== 'production';

return [

    'enabled' => env('UAT_TOOLS_ENABLED') === null
        ? $defaultEnabled
        : filter_var(env('UAT_TOOLS_ENABLED'), FILTER_VALIDATE_BOOLEAN),

    'verdicts' => ['غير مجرّب', 'يعتمد', 'يحتاج تحسين'],

    'note_tags' => ['', 'UI ناقص', 'بيانات/تدفق', 'صلاحيات', 'أداء/أخطاء', 'نص/RTL', 'أخرى'],

    /** تقييم عبدالله السابق (التجربة الثانية) — يُحمَّل افتراضيًا في الواجهة. */
    'baseline' => require __DIR__.'/uat_baseline_round2.php',

    /**
     * ثلاث مراحل متساوية تقريباً حسب تبويبات التنقّل.
     * gate: المرحلة N+1 تُفتح فقط عندما كل أدوات N = «يعتمد».
     */
    'phases' => [
        [
            'id' => 1,
            'title' => 'المرحلة 1 — الأساس والموارد',
            'goal' => 'التنقّل · الدخول · الرئيسية · الموارد البشرية · الهيكل · الأدوار',
            'group_ids' => ['shell', 'auth', 'hr', 'structure', 'roles'],
        ],
        [
            'id' => 2,
            'title' => 'المرحلة 2 — التشغيل والمالية',
            'goal' => 'إسناد · الاجتماعات · المالية · التقارير',
            'group_ids' => ['esnad', 'meetings', 'finance', 'reports'],
        ],
        [
            'id' => 3,
            'title' => 'المرحلة 3 — النمو والمحتوى',
            'goal' => 'الشراكات · المشاريع · المستندات · إعدادات المنصة',
            'group_ids' => ['partnerships', 'projects', 'documents', 'settings'],
        ],
    ],

    'groups' => [
        [
            'id' => 'shell',
            'phase' => 1,
            'title' => 'التنقّل وهيكل الواجهة',
            'items' => [
                ['id' => 'sidebar', 'tool' => 'الشريط الجانبي', 'path' => 'أي صفحة', 'checks' => 'طي المجموعة وفتحها · بقاء الحالة بعد التحديث · فتح مجموعة الصفحة الحالية تلقائيًا'],
                ['id' => 'nav-search', 'tool' => 'بحث القوائم', 'path' => 'أي صفحة', 'checks' => 'كتابة «إجاز» تُظهر الإجازات فقط · «zzz» تُظهر «لا توجد أداة بهذا الاسم» · المسح يعيد الكل'],
                ['id' => 'bell', 'tool' => 'جرس التنبيهات', 'path' => 'الشريط العلوي', 'checks' => 'عدّاد غير المقروء · فتح القائمة · النقر يفتح السجل بـ ?open=id (ليس /tasks فقط) · تعليم كمقروء'],
                ['id' => 'mobile', 'tool' => 'تجربة الجوال', 'path' => 'عرض < 768px', 'checks' => 'زر القائمة · قوائم الحساب/الإشعارات داخل الشاشة · Toast أعلى الوسط · لا تمرير أفقي'],
            ],
        ],
        [
            'id' => 'auth',
            'phase' => 1,
            'title' => 'الدخول',
            'items' => [
                ['id' => 'login', 'tool' => 'تسجيل الدخول', 'path' => '/login', 'checks' => 'جوال + كلمة مرور · تذكرني · رسالة خطأ عربية'],
                ['id' => 'change-password', 'tool' => 'تغيير كلمة المرور', 'path' => '/change-password', 'checks' => 'الإلزام عند أول دخول · رفض المطابقة للقديمة'],
                ['id' => 'dashboard', 'tool' => 'الرئيسية', 'path' => '/dashboard', 'checks' => 'يحتاج تدخلك قابل للطي (مطوي افتراضيًا) · مؤشرات الدور · بلا أزرار حضور/انصراف · بلا خطأ'],
            ],
        ],
        [
            'id' => 'hr',
            'phase' => 1,
            'title' => 'الموارد البشرية',
            'items' => [
                ['id' => 'users', 'tool' => 'دليل العاملين', 'path' => '/users', 'checks' => 'قائمة · فتح ملف · بحث · صلاحية hr.employees.view'],
                ['id' => 'contracts-hr', 'tool' => 'عقود العاملين', 'path' => '/contracts', 'checks' => 'عمود الراتب الشهري (من الملف) · بلا قيمة عقد منفصلة · حالات · رفع ملف · تنبيه انتهاء'],
                ['id' => 'pay-scales', 'tool' => 'سلم الرواتب', 'path' => '/pay-scales', 'checks' => 'إنشاء سلم · مكونات · hr.salaries.manage'],
                ['id' => 'payroll-runs', 'tool' => 'مسيّرات الرواتب', 'path' => '/payroll-runs', 'checks' => 'إعداد مسير شهر · فلتر الحالة/الشهر · ترقيم · رفع للمالية · مرآة الراتب إلى الملف'],
                ['id' => 'payroll-monthly', 'tool' => 'الرواتب الشهرية (مُدمَج)', 'path' => '/payroll', 'checks' => 'التحويل إلى /payroll-runs · لا تبويب ثالث في القائمة · مصدر الراتب: الملف + المسيّر فقط'],
                ['id' => 'evaluations', 'tool' => 'التقييم الدوري', 'path' => '/evaluations', 'checks' => 'إنشاء 2026-Q3 · رفض التكرار · معاينة قبل النشر · النشر يظهر للموظف · فلاتر'],
                ['id' => 'responsibilities', 'tool' => 'المسؤوليات', 'path' => '/responsibilities', 'checks' => 'إضافة بند · إيقاف · ترتيب · فلتر النشطة'],
                ['id' => 'attendance', 'tool' => 'الحضور', 'path' => '/attendance', 'checks' => 'تفعيل لكل موظف · حضور/انصراف · إقرار · عمود التأخر · طباعة شهرية · بداية الدوام من الإعدادات'],
                ['id' => 'leaves', 'tool' => 'الإجازات', 'path' => '/leaves', 'checks' => 'تقديم سنوية · منع التداخل · حجز رصيد · اعتماد مدير · تعذّر اعتماد الذات · ظهورها بالتقويم'],
                ['id' => 'hr-lifecycle', 'tool' => 'التهيئة وإنهاء العلاقة', 'path' => '/hr-lifecycle', 'checks' => 'موانع العهد والأصول · تعذّر إنهاء الذات · مهمة تسليم'],
            ],
        ],
        [
            'id' => 'structure',
            'phase' => 1,
            'title' => 'الأقسام والهيكلة',
            'items' => [
                ['id' => 'depts', 'tool' => 'الأقسام', 'path' => '/departments', 'checks' => 'إنشاء · تعديل · مدير القسم'],
                ['id' => 'org', 'tool' => 'الهيكل التنظيمي', 'path' => '/structure/org-tree', 'checks' => 'إدارة/وحدة/وظيفة بالعربية · نقل موظف'],
                ['id' => 'jobs', 'tool' => 'الوظائف', 'path' => '/structure/jobs', 'checks' => 'بطاقة وظيفية · المسؤول المباشر · بحث وفلتر الوحدة'],
                ['id' => 'committees', 'tool' => 'اللجان', 'path' => '/structure/committees', 'checks' => 'رئيس اللجنة · التفويض · فلتر النشطة'],
                ['id' => 'profile', 'tool' => 'الملف الوظيفي', 'path' => '/users/{id}/profile', 'checks' => 'بيانات · عقد · مسؤوليات · رصيد الإجازات · مكوّنات الراتب الشهري · تفعيل الحضور'],
            ],
        ],
        [
            'id' => 'roles',
            'phase' => 1,
            'title' => 'الأدوار والصلاحيات',
            'items' => [
                ['id' => 'roles', 'tool' => 'الأدوار', 'path' => '/settings/roles', 'checks' => 'أدوار · صلاحيات معرّبة بالكامل'],
                ['id' => 'grants', 'tool' => 'منح الصلاحيات', 'path' => '/settings/grants', 'checks' => 'منح استثنائية بسبب وتاريخ'],
            ],
        ],
        [
            'id' => 'esnad',
            'phase' => 2,
            'title' => 'إسناد',
            'items' => [
                ['id' => 'tasks', 'tool' => 'المهام', 'path' => '/tasks', 'checks' => 'مهامي/أسندتها · حالات · إنشاء · مرفقات · فتح بـ ?open='],
                ['id' => 'team-tasks', 'tool' => 'مهام الفريق', 'path' => '/team-tasks', 'checks' => 'متأخرة · بانتظار المراجعة'],
                ['id' => 'calendar', 'tool' => 'تقويم المهام', 'path' => '/tasks-calendar', 'checks' => 'مهام الشهر · الإجازات المعتمدة · تنقّل بين الأشهر'],
                ['id' => 'recurring', 'tool' => 'المهام المتكررة', 'path' => '/recurring-tasks', 'checks' => 'قالب · توليد الدورة'],
                ['id' => 'workload', 'tool' => 'لوحة الأحمال', 'path' => '/workload-board', 'checks' => 'توزيع الأحمال · صلاحية الفريق'],
            ],
        ],
        [
            'id' => 'meetings',
            'phase' => 2,
            'title' => 'الاجتماعات',
            'items' => [
                ['id' => 'meetings', 'tool' => 'الاجتماعات', 'path' => '/meetings', 'checks' => 'إنشاء · جدول أعمال · حضور'],
                ['id' => 'minutes', 'tool' => 'محضر الاجتماع', 'path' => '/meetings/{id}/minutes', 'checks' => 'قرارات · اعتماد · PDF'],
                ['id' => 'open-decisions', 'tool' => 'القرارات المفتوحة', 'path' => '/meetings/open-decisions', 'checks' => 'قرارات متأخرة · تحويل لمهمة'],
            ],
        ],
        [
            'id' => 'finance',
            'phase' => 2,
            'title' => 'المالية',
            'items' => [
                ['id' => 'expenses', 'tool' => 'طلبات الصرف', 'path' => '/expenses', 'checks' => 'طلب · سلسلة اعتماد · صرف · إرجاع returned · فلاتر · مرفق'],
                ['id' => 'custodies', 'tool' => 'العهد', 'path' => '/custodies', 'checks' => 'طلب · اعتماد · صرف · إثبات · فلتر · بطاقات الجوال'],
                ['id' => 'assets', 'tool' => 'الأصول', 'path' => '/assets', 'checks' => 'تسجيل · تسليم لموظف · بحث بالرمز · فلتر الحالة'],
                ['id' => 'revenues', 'tool' => 'الإيرادات', 'path' => '/revenues', 'checks' => 'تسجيل إيراد · مرفق إلزامي ينتظر الرفع · فلتر المصدر والتاريخ'],
                ['id' => 'tax', 'tool' => 'الفواتير الضريبية', 'path' => '/tax-invoices', 'checks' => 'إصدار · إشعار دائن/مدين · طباعة/PDF عربي'],
                ['id' => 'budgets', 'tool' => 'الميزانيات', 'path' => '/budgets', 'checks' => 'موازنات المشاريع · تجاوزات'],
                ['id' => 'fin-reports', 'tool' => 'التقرير المالي', 'path' => '/financial-reports', 'checks' => 'توليد · طباعة · Excel'],
                ['id' => 'fin-docs', 'tool' => 'المستندات المالية', 'path' => '/financial-documents', 'checks' => 'فهرس المستندات المالية'],
            ],
        ],
        [
            'id' => 'reports',
            'phase' => 2,
            'title' => 'التقارير',
            'items' => [
                ['id' => 'center', 'tool' => 'مركز التقارير', 'path' => '/reports/center', 'checks' => 'شهري · مشاريع · أثر · مؤشرات'],
                ['id' => 'weekly', 'tool' => 'التقرير الأسبوعي', 'path' => '/reports', 'checks' => 'عرض · توليد'],
                ['id' => 'audit', 'tool' => 'سجل النشاط', 'path' => '/reports/audit-log', 'checks' => 'أحداث · فلترة · من نفّذ'],
            ],
        ],
        [
            'id' => 'partnerships',
            'phase' => 3,
            'title' => 'الشراكات',
            'items' => [
                ['id' => 'orgs', 'tool' => 'الجهات الشريكة', 'path' => '/organizations', 'checks' => 'إنشاء جهة · بحث'],
                ['id' => 'org-show', 'tool' => 'ملف الجهة', 'path' => '/organizations/{id}', 'checks' => 'الشراكات المرتبطة · جهات الاتصال'],
                ['id' => 'pipeline', 'tool' => 'رحلة الشراكات', 'path' => '/partnerships/pipeline', 'checks' => 'المراحل · نقل شراكة · فتح ملف'],
                ['id' => 'p-contract', 'tool' => 'عقد الشراكة (داخل الصفحة)', 'path' => '/partnerships/{id}', 'checks' => 'المسمى «عقد الشراكة» · بصمة الملف · عروض الأسعار · الدفعات'],
                ['id' => 'portal', 'tool' => 'بوابة الشريك', 'path' => '/portal/{token}', 'checks' => 'فتح برابط · عرض العقد PDF · لا تسريب بيانات أخرى'],
            ],
        ],
        [
            'id' => 'projects',
            'phase' => 3,
            'title' => 'المشاريع',
            'items' => [
                ['id' => 'projects', 'tool' => 'محفظة المشاريع', 'path' => '/projects', 'checks' => 'قائمة · مشروع جديد · فلاتر'],
                ['id' => 'project-show', 'tool' => 'ملف المشروع', 'path' => '/projects/{id}', 'checks' => 'تبويبات · فريق · ميزانية'],
                ['id' => 'project-exec', 'tool' => 'تنفيذ المشروع', 'path' => '/projects/{id}/execution', 'checks' => 'خطة · نسبة إنجاز · زيارات · إغلاق'],
                ['id' => 'programs', 'tool' => 'مكتبة البرامج', 'path' => '/programs', 'checks' => 'برامج · أسعار · ملفات'],
                ['id' => 'program-show', 'tool' => 'بطاقة البرنامج', 'path' => '/programs/{id}', 'checks' => 'تفاصيل · توليد مشروع'],
                ['id' => 'templates', 'tool' => 'محرر القوالب', 'path' => '/plan-templates', 'checks' => 'مستويات · بنود بانتظار المراجعة'],
                ['id' => 'visits', 'tool' => 'الزيارات', 'path' => '/visits', 'checks' => 'فلتر المشروع والحالة والتاريخ · رابط التنفيذ · بطاقات الجوال'],
                ['id' => 'measurement', 'tool' => 'القياس والأثر', 'path' => '/measurement', 'checks' => 'نماذج القياس · فلتر البرنامج والنوع'],
            ],
        ],
        [
            'id' => 'documents',
            'phase' => 3,
            'title' => 'المستندات',
            'items' => [
                ['id' => 'docs', 'tool' => 'المستودع', 'path' => '/documents', 'checks' => 'رفع · مستوى السرية · تنزيل · روابط الأقسام'],
                ['id' => 'doc-templates', 'tool' => 'مكتبة القوالب', 'path' => '/documents/templates', 'checks' => 'قوالب معتمدة'],
                ['id' => 'versions', 'tool' => 'إدارة النسخ', 'path' => '/documents/versions', 'checks' => 'رفع نسخة · بقاء القديمة · لا تظهر نسخ خارج صلاحيتك'],
                ['id' => 'policies', 'tool' => 'السياسات', 'path' => '/documents/policies', 'checks' => 'سياسات · تاريخ المراجعة'],
                ['id' => 'minutes-arch', 'tool' => 'أرشيف المحاضر', 'path' => '/meetings/archive', 'checks' => 'المعتمدة فقط · بحث وشهر · رابط المحضر بحسب الصلاحية'],
            ],
        ],
        [
            'id' => 'settings',
            'phase' => 3,
            'title' => 'إعدادات المنصة',
            'items' => [
                ['id' => 'settings', 'tool' => 'عامة', 'path' => '/settings', 'checks' => 'إعدادات عامة · بداية الدوام attendance.office_start_time · نسخ احتياطي'],
                ['id' => 'exp-settings', 'tool' => 'إعدادات المالية', 'path' => '/settings/expenses', 'checks' => 'سلسلة الاعتماد · التصنيفات · الضريبة'],
                ['id' => 'smtp', 'tool' => 'SMTP', 'path' => '/settings/notifications', 'checks' => 'بريد · تفضيلات الإشعارات'],
            ],
        ],
    ],

];
