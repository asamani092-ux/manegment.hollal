<?php

/**
 * تقييم عبدالله — ملاحظات المرحلة 3 / 2026-08-17 15:23
 * المرحلة 1 و2 مكتملتان («يعتمد») حتى تُفتح المرحلة 3 في النموذج.
 *
 * @return array{label: string, date: string, summary: string, verdicts: array<string, string>, tags: array<string, string>, notes: array<string, string>}
 */
$accepted = [
    'sidebar', 'nav-search', 'bell', 'mobile',
    'login', 'change-password', 'dashboard',
    'users', 'contracts-hr', 'pay-scales', 'payroll-runs', 'payroll-monthly',
    'evaluations', 'responsibilities', 'attendance', 'leaves', 'hr-lifecycle',
    'depts', 'org', 'jobs', 'committees', 'profile', 'roles', 'grants',
    'tasks', 'team-tasks', 'calendar', 'workload',
    'meetings', 'minutes', 'open-decisions',
    'expenses', 'custodies', 'assets', 'revenues', 'tax', 'budgets', 'fin-reports', 'fin-docs',
    'center', 'weekly', 'audit',
    'programs', 'program-show',
];

$verdicts = array_fill_keys($accepted, 'يعتمد');

$needsWork = [
    'orgs', 'org-show', 'pipeline', 'p-contract', 'portal',
    'projects', 'project-show', 'project-exec', 'templates', 'visits', 'measurement',
    'docs', 'doc-templates', 'versions', 'policies', 'minutes-arch',
    'settings', 'exp-settings', 'smtp',
];

foreach ($needsWork as $id) {
    $verdicts[$id] = 'يحتاج تحسين';
}

return [
    'label' => 'تقييم 2026-08-17 15:23 (ملاحظات المرحلة 3)',
    'date' => '2026-08-17 15:23',
    'summary' => '63 إجمالي · 44 يعتمد · 19 يحتاج تحسين · المرحلة 3 مفتوحة',

    'verdicts' => $verdicts,

    'tags' => [
        'orgs' => 'UI ناقص',
        'org-show' => 'بيانات/تدفق',
        'pipeline' => 'أخرى',
        'p-contract' => 'UI ناقص',
        'portal' => 'أداء/أخطاء',
        'projects' => 'UI ناقص',
        'project-show' => 'بيانات/تدفق',
        'project-exec' => 'بيانات/تدفق',
        'templates' => 'أخرى',
        'visits' => 'UI ناقص',
        'measurement' => 'بيانات/تدفق',
        'docs' => 'نص/RTL',
        'doc-templates' => 'UI ناقص',
        'versions' => 'أخرى',
        'policies' => 'بيانات/تدفق',
        'minutes-arch' => 'بيانات/تدفق',
        'settings' => 'نص/RTL',
        'exp-settings' => 'بيانات/تدفق',
        'smtp' => 'أخرى',
    ],

    'notes' => [
        'orgs' => 'نموذج إضافة جهة يحتاج الى تحسين: الأدوار بالضغط على النص وتحديد بيضاوي.',
        'org-show' => 'توضيح حقل الدخول للملف. إضافة تجديد لمشروع متوقف أو منتهي.',
        'pipeline' => 'دراسة نقل المراحل من النظام مع النقل اليدوي وكيفية الربط — معتمد كاحتياج أساسي.',
        'p-contract' => 'ملف الشراكة يحتاج ترتيب أوضح وخطوات يتبعها المستخدم. إضافة معاينة لعرض السعر.',
        'portal' => 'ترتيب البوابة وفق الخطة الأساسية. أكثر الأزرار لا تعمل.',
        'projects' => 'العرض متكرر بطاقات وجدول وعند اختيار جدول لا تختفي البطاقات. مقارنة بلوك الشراكات مع تبويب الشراكات ودمج الفروقات.',
        'project-show' => 'يحتاج تفكيك ودراسة لتبسيطه إلى أكبر قدر ممكن قبل مراجعة أدواته.',
        'project-exec' => 'مرتبط بملف المشروع — دراسة وتفكيك الأدوات بشكل كامل.',
        'templates' => 'تفكيك ودراسة: المتطلبات والهدف الأساسي وكيف يُحسَّن.',
        'visits' => 'العرض متكرر بطاقات وجدول بنفس المحتوى. أين تُنشأ الزيارات؟',
        'measurement' => 'أين الاختبار وأين النتائج؟',
        'docs' => 'معاينة وتنزيل بعنوان عربي (RFC 5987). الشريط مصدر التنقّل.',
        'doc-templates' => 'معاينة وتنزيل + ظهور all/department. بلا زر مستندات مكرر.',
        'versions' => 'آخر نسخة في المستودع؛ شارة الحالية + تنزيل النسخ القديمة.',
        'policies' => 'السياسات من الصفحة والمستودع. ملف المهام واحد للمنصة من الرئيسية.',
        'minutes-arch' => 'موافقة التعديل تنشئ DocumentVersion موسومة؛ الأصل محفوظ.',
        'settings' => 'إعادة ترتيب الإعدادات بطاقات لكل إعداد مع إكمالها وتعريبها وتوضيحها.',
        'exp-settings' => 'خيار التخطي وتنبيه التخطي التلقائي يبدو خطأ منطقياً. إضافة تعديل مسار طلبات معلّقة (موظف مجاز/مستقيل) مع بحث — الأصل لكل طلبات المنصة أو تحت الموارد البشرية.',
        'smtp' => 'الاستضافة على هوستنقر: كيف يُفعَّل الخيار وما الخطوات بالتفصيل؟',
        'programs' => 'حالياً',
        'program-show' => 'حالياً',
    ],
];
