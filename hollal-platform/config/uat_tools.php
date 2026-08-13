<?php

/**
 * UAT tools checklist — available only while UAT_TOOLS_ENABLED is true.
 * Default: on for every non-production APP_ENV; off when publishing (production).
 *
 * Catalog mirrors the canvas uat-tools-checklist with per-tool verification bullets.
 */
$defaultEnabled = env('APP_ENV', 'production') !== 'production';

return [

    'enabled' => env('UAT_TOOLS_ENABLED') === null
        ? $defaultEnabled
        : filter_var(env('UAT_TOOLS_ENABLED'), FILTER_VALIDATE_BOOLEAN),

    'verdicts' => ['غير مجرّب', 'يعتمد', 'يحتاج تحسين'],

    'note_tags' => ['', 'UI ناقص', 'بيانات/تدفق', 'صلاحيات', 'أداء/أخطاء', 'نص/RTL', 'أخرى'],

    'groups' => [
        [
            'id' => 'shell',
            'title' => 'التنقّل وهيكل الواجهة',
            'items' => [
                ['id' => 'sidebar', 'tool' => 'الشريط الجانبي', 'path' => 'أي صفحة', 'checks' => 'طي المجموعة وفتحها · بقاء الحالة بعد التحديث · فتح مجموعة الصفحة الحالية تلقائيًا'],
                ['id' => 'nav-search', 'tool' => 'بحث القوائم', 'path' => 'أي صفحة', 'checks' => 'كتابة «إجاز» تُظهر الإجازات فقط · «zzz» تُظهر «لا توجد أداة بهذا الاسم» · المسح يعيد الكل'],
                ['id' => 'bell', 'tool' => 'جرس التنبيهات', 'path' => 'الشريط العلوي', 'checks' => 'عدّاد غير المقروء · فتح القائمة · تعليم كمقروء'],
                ['id' => 'mobile', 'tool' => 'تجربة الجوال', 'path' => 'عرض < 768px', 'checks' => 'زر القائمة · بطاقات بدل الجداول في الإجازات والعهد والزيارات · لا تمرير أفقي'],
            ],
        ],
        [
            'id' => 'auth',
            'title' => 'الدخول',
            'items' => [
                ['id' => 'login', 'tool' => 'تسجيل الدخول', 'path' => '/login', 'checks' => 'جوال + كلمة مرور · تذكرني · رسالة خطأ عربية'],
                ['id' => 'change-password', 'tool' => 'تغيير كلمة المرور', 'path' => '/change-password', 'checks' => 'الإلزام عند أول دخول · رفض المطابقة للقديمة'],
                ['id' => 'dashboard', 'tool' => 'الرئيسية', 'path' => '/dashboard', 'checks' => 'يحتاج تدخلك · مؤشرات الدور · بلا خطأ'],
            ],
        ],
        [
            'id' => 'hr',
            'title' => 'الموارد البشرية',
            'items' => [
                ['id' => 'users', 'tool' => 'دليل العاملين', 'path' => '/users', 'checks' => 'قائمة · فتح ملف · بحث · صلاحية hr.employees.view'],
                ['id' => 'contracts-hr', 'tool' => 'عقود العاملين', 'path' => '/contracts', 'checks' => 'ليست عقود شراكة · حالات العقد · رفع ملف · تنبيه انتهاء'],
                ['id' => 'pay-scales', 'tool' => 'سلم الرواتب', 'path' => '/pay-scales', 'checks' => 'إنشاء سلم · مكونات · hr.salaries.manage'],
                ['id' => 'payroll-runs', 'tool' => 'مسيّرات الرواتب', 'path' => '/payroll-runs', 'checks' => 'إعداد مسير شهر · فلتر الحالة/الشهر · ترقيم صفحات · رفع للمالية'],
                ['id' => 'payroll-monthly', 'tool' => 'الرواتب الشهرية', 'path' => '/payroll', 'checks' => 'مدخل في القائمة · إنشاء/تعديل راتب · صافي محسوب · حالة التحويل'],
                ['id' => 'evaluations', 'tool' => 'التقييم الدوري', 'path' => '/evaluations', 'checks' => 'إنشاء بصيغة 2026-Q3 · رفض التكرار · نشر · فلاتر'],
                ['id' => 'responsibilities', 'tool' => 'المسؤوليات', 'path' => '/responsibilities', 'checks' => 'إضافة بند · إيقاف · ترتيب · فلتر النشطة'],
                ['id' => 'attendance', 'tool' => 'الحضور', 'path' => '/attendance', 'checks' => 'حضور/انصراف · إقرار اليوم لا يغيّر وقت الحضور · فلاتر'],
                ['id' => 'leaves', 'tool' => 'الإجازات', 'path' => '/leaves', 'checks' => 'تقديم سنوية · منع التداخل · حجز رصيد · اعتماد مدير · تعذّر اعتماد الذات · ظهورها بالتقويم'],
                ['id' => 'hr-lifecycle', 'tool' => 'التهيئة وإنهاء العلاقة', 'path' => '/hr-lifecycle', 'checks' => 'موانع العهد والأصول · تعذّر إنهاء الذات · مهمة تسليم'],
            ],
        ],
        [
            'id' => 'esnad',
            'title' => 'إسناد',
            'items' => [
                ['id' => 'tasks', 'tool' => 'المهام', 'path' => '/tasks', 'checks' => 'مهامي/أسندتها · حالات · إنشاء · مرفقات'],
                ['id' => 'team-tasks', 'tool' => 'مهام الفريق', 'path' => '/team-tasks', 'checks' => 'متأخرة · بانتظار المراجعة'],
                ['id' => 'calendar', 'tool' => 'تقويم المهام', 'path' => '/tasks-calendar', 'checks' => 'مهام الشهر · الإجازات المعتمدة · تنقّل بين الأشهر'],
                ['id' => 'recurring', 'tool' => 'المهام المتكررة', 'path' => '/recurring-tasks', 'checks' => 'قالب · توليد الدورة'],
                ['id' => 'workload', 'tool' => 'لوحة الأحمال', 'path' => '/workload-board', 'checks' => 'توزيع الأحمال · صلاحية الفريق'],
            ],
        ],
        [
            'id' => 'meetings',
            'title' => 'الاجتماعات',
            'items' => [
                ['id' => 'meetings', 'tool' => 'الاجتماعات', 'path' => '/meetings', 'checks' => 'إنشاء · جدول أعمال · حضور'],
                ['id' => 'minutes', 'tool' => 'محضر الاجتماع', 'path' => '/meetings/{id}/minutes', 'checks' => 'قرارات · اعتماد · PDF'],
                ['id' => 'open-decisions', 'tool' => 'القرارات المفتوحة', 'path' => '/meetings/open-decisions', 'checks' => 'قرارات متأخرة · تحويل لمهمة'],
            ],
        ],
        [
            'id' => 'finance',
            'title' => 'المالية',
            'items' => [
                ['id' => 'expenses', 'tool' => 'طلبات الصرف', 'path' => '/expenses', 'checks' => 'طلب · سلسلة اعتماد · صرف · فلاتر · مرفق'],
                ['id' => 'custodies', 'tool' => 'العهد', 'path' => '/custodies', 'checks' => 'طلب · اعتماد · صرف · فلتر الحالة والموظف · بطاقات الجوال'],
                ['id' => 'assets', 'tool' => 'الأصول', 'path' => '/assets', 'checks' => 'تسجيل · تسليم لموظف · بحث بالرمز · فلتر الحالة'],
                ['id' => 'revenues', 'tool' => 'الإيرادات', 'path' => '/revenues', 'checks' => 'تسجيل إيراد · فلتر المصدر ونطاق التاريخ'],
                ['id' => 'tax', 'tool' => 'الفواتير الضريبية', 'path' => '/tax-invoices', 'checks' => 'إصدار · إشعار دائن/مدين · PDF'],
                ['id' => 'budgets', 'tool' => 'الميزانيات', 'path' => '/budgets', 'checks' => 'موازنات المشاريع · تجاوزات'],
                ['id' => 'fin-reports', 'tool' => 'التقرير المالي', 'path' => '/financial-reports', 'checks' => 'توليد · PDF'],
                ['id' => 'fin-docs', 'tool' => 'المستندات المالية', 'path' => '/financial-documents', 'checks' => 'فهرس المستندات المالية'],
            ],
        ],
        [
            'id' => 'partnerships',
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
            'id' => 'reports',
            'title' => 'التقارير',
            'items' => [
                ['id' => 'center', 'tool' => 'مركز التقارير', 'path' => '/reports/center', 'checks' => 'شهري · مشاريع · أثر · مؤشرات'],
                ['id' => 'weekly', 'tool' => 'التقرير الأسبوعي', 'path' => '/reports', 'checks' => 'عرض · توليد'],
                ['id' => 'audit', 'tool' => 'سجل النشاط', 'path' => '/reports/audit-log', 'checks' => 'أحداث · فلترة · من نفّذ'],
            ],
        ],
        [
            'id' => 'structure',
            'title' => 'الأقسام والهيكلة',
            'items' => [
                ['id' => 'depts', 'tool' => 'الأقسام', 'path' => '/departments', 'checks' => 'إنشاء · تعديل · مدير القسم'],
                ['id' => 'org', 'tool' => 'الهيكل التنظيمي', 'path' => '/structure/org-tree', 'checks' => 'إدارة/وحدة/وظيفة بالعربية · نقل موظف'],
                ['id' => 'jobs', 'tool' => 'الوظائف', 'path' => '/structure/jobs', 'checks' => 'بطاقة وظيفية · المسؤول المباشر · بحث وفلتر الوحدة'],
                ['id' => 'committees', 'tool' => 'اللجان', 'path' => '/structure/committees', 'checks' => 'رئيس اللجنة · التفويض · فلتر النشطة'],
                ['id' => 'profile', 'tool' => 'الملف الوظيفي', 'path' => '/users/{id}/profile', 'checks' => 'بيانات · عقد · مسؤوليات · رصيد الإجازات'],
            ],
        ],
        [
            'id' => 'roles',
            'title' => 'الأدوار والصلاحيات',
            'items' => [
                ['id' => 'roles', 'tool' => 'الأدوار', 'path' => '/settings/roles', 'checks' => 'أدوار · صلاحيات معرّبة بالكامل'],
                ['id' => 'grants', 'tool' => 'منح الصلاحيات', 'path' => '/settings/grants', 'checks' => 'منح استثنائية بسبب وتاريخ'],
            ],
        ],
        [
            'id' => 'settings',
            'title' => 'إعدادات المنصة',
            'items' => [
                ['id' => 'settings', 'tool' => 'عامة', 'path' => '/settings', 'checks' => 'إعدادات عامة · نسخ احتياطي'],
                ['id' => 'exp-settings', 'tool' => 'إعدادات المالية', 'path' => '/settings/expenses', 'checks' => 'سلسلة الاعتماد · التصنيفات · الضريبة'],
                ['id' => 'smtp', 'tool' => 'SMTP', 'path' => '/settings/notifications', 'checks' => 'بريد · تفضيلات الإشعارات'],
            ],
        ],
    ],

];
