<?php

/**
 * Grouped navigation — 11 tabs per docs/specs + GLOSSARY-AR.md.
 * Sub-items: in-page tabs or sidebar children under each group.
 */
return [

    'top' => [
        [
            'label' => 'الرئيسية',
            'route' => 'dashboard',
            'icon' => 'fa-home',
            'permission' => 'dashboard.view',
        ],
        [
            'label' => 'مساحتي',
            'route' => 'employee-hub.index',
            'icon' => 'fa-user',
            'permission' => 'dashboard.view',
        ],
        [
            'label' => 'تقييم الأدوات (UAT)',
            'route' => 'uat.tools',
            'icon' => 'fa-clipboard-check',
            'permission' => 'dashboard.view',
            'uat_only' => true,
        ],
    ],

    'groups' => [
        [
            'label' => 'الموارد البشرية',
            'items' => [
                ['label' => 'دليل العاملين', 'route' => 'users.index', 'icon' => 'fa-users', 'permission' => 'hr.employees.view'],
                ['label' => 'عقود العاملين', 'route' => 'contracts.index', 'icon' => 'fa-file-signature', 'permission' => 'hr.employees.view'],
                ['label' => 'سلم الرواتب', 'route' => 'pay-scales.index', 'icon' => 'fa-layer-group', 'permission' => 'hr.salaries.manage'],
                ['label' => 'مسيّرات الرواتب', 'route' => 'payroll-runs.index', 'icon' => 'fa-file-invoice-dollar', 'permission' => 'hr.salaries.view'],
                // «الرواتب الشهرية» أُدمجت في المسيّر + الملف الوظيفي — لا تبويب ثالث.
                ['label' => 'التقييم الدوري', 'route' => 'evaluations.index', 'icon' => 'fa-star-half-alt', 'permission' => 'hr.employees.view'],
                ['label' => 'المسؤوليات', 'route' => 'responsibilities.index', 'icon' => 'fa-clipboard-list', 'permission' => 'hr.employees.update'],
                ['label' => 'الحضور', 'route' => 'attendance.index', 'icon' => 'fa-user-clock', 'permission' => 'hr.employees.view'],
                ['label' => 'دورة الحضور', 'route' => 'attendance.cycle', 'icon' => 'fa-calendar-week', 'permission' => 'hr.employees.update'],
                ['label' => 'الإجازات', 'route' => 'leaves.index', 'icon' => 'fa-umbrella-beach', 'permission' => 'hr.leaves.request|hr.leaves.approve|hr.leaves.view-all|hr.employees.view'],
                ['label' => 'التهيئة وإنهاء العلاقة', 'route' => 'hr-lifecycle.index', 'icon' => 'fa-user-slash', 'permission' => 'hr.employees.update'],
            ],
        ],
        [
            'label' => 'إسناد',
            'items' => [
                ['label' => 'المهام', 'route' => 'tasks.index', 'icon' => 'fa-tasks', 'permission' => 'esnad.tasks.view'],
                ['label' => 'مهام الفريق', 'route' => 'team-tasks.index', 'icon' => 'fa-people-carry', 'permission' => 'esnad.tasks.team.view'],
                ['label' => 'تقويم المهام', 'route' => 'tasks-calendar.index', 'icon' => 'fa-calendar', 'permission' => 'esnad.tasks.view'],
                ['label' => 'لوحة الأحمال', 'route' => 'workload-board.index', 'icon' => 'fa-chart-area', 'permission' => 'esnad.tasks.team.view|esnad.tasks.create'],
            ],
        ],
        [
            'label' => 'الاجتماعات',
            'items' => [
                ['label' => 'الاجتماعات', 'route' => 'meetings.index', 'icon' => 'fa-calendar-alt', 'permission' => 'meetings.view'],
                ['label' => 'القرارات المفتوحة', 'route' => 'meetings.open-decisions', 'icon' => 'fa-gavel', 'permission' => 'meetings.view'],
            ],
        ],
        [
            'label' => 'المالية',
            'items' => [
                ['label' => 'طلبات الصرف', 'route' => 'expenses.index', 'icon' => 'fa-money-bill-wave', 'permission' => 'finance.expenses.view|finance.expenses.create|finance.expenses.approve|finance.expenses.pay'],
                ['label' => 'مسير الرواتب', 'route' => 'payroll-runs.index', 'icon' => 'fa-file-invoice-dollar', 'permission' => 'finance.payroll.view|hr.salaries.view'],
                ['label' => 'العهد', 'route' => 'custodies.index', 'icon' => 'fa-hand-holding-usd', 'permission' => 'finance.custodies.view|finance.custodies.approve|finance.custodies.disburse'],
                ['label' => 'الأصول', 'route' => 'assets.index', 'icon' => 'fa-boxes', 'permission' => 'finance.assets.view|finance.assets.manage'],
                ['label' => 'الإيرادات', 'route' => 'revenues.index', 'icon' => 'fa-coins', 'permission' => 'finance.revenues.view|finance.revenues.manage'],
                ['label' => 'الفواتير الضريبية', 'route' => 'tax-invoices.index', 'icon' => 'fa-file-invoice', 'permission' => 'finance.tax_invoices.view'],
                ['label' => 'الميزانيات', 'route' => 'budgets.index', 'icon' => 'fa-balance-scale-left', 'permission' => 'finance.budgets.view'],
                ['label' => 'التقرير المالي', 'route' => 'financial-reports.index', 'icon' => 'fa-chart-line', 'permission' => 'finance.reports.view'],
                ['label' => 'دليل الحسابات', 'route' => 'chart-of-accounts.index', 'icon' => 'fa-sitemap', 'permission' => 'finance.accounting.manage'],
                ['label' => 'القيود اليومية', 'route' => 'journal-entries.index', 'icon' => 'fa-book', 'permission' => 'finance.accounting.manage'],
                ['label' => 'الدفاتر والقوائم', 'route' => 'accounting-reports.index', 'icon' => 'fa-balance-scale', 'permission' => 'finance.accounting.manage'],
                ['label' => 'مراكز التكلفة والإقفال', 'route' => 'accounting-close.index', 'icon' => 'fa-calendar-check', 'permission' => 'finance.accounting.manage'],
                ['label' => 'المستندات المالية', 'route' => 'financial-documents.index', 'icon' => 'fa-folder-open', 'permission' => 'finance.revenues.view'],
            ],
        ],
        [
            'label' => 'الشراكات',
            'items' => [
                ['label' => 'الجهات الشريكة', 'route' => 'organizations.index', 'icon' => 'fa-building', 'permission' => 'partnerships.organizations.view'],
                ['label' => 'أسئلة التشخيص', 'route' => 'partnerships.diagnosis-questions.index', 'icon' => 'fa-clipboard-list', 'permission' => 'partnerships.organizations.view'],
                ['label' => 'رحلة الشراكات', 'route' => 'partnerships.pipeline', 'icon' => 'fa-stream', 'permission' => 'partnerships.pipeline.view'],
            ],
        ],
        [
            'label' => 'المشاريع',
            'items' => [
                ['label' => 'محفظة المشاريع', 'route' => 'projects.index', 'icon' => 'fa-project-diagram', 'permission' => 'projects.view'],
                ['label' => 'مكتبة البرامج', 'route' => 'programs.index', 'icon' => 'fa-book', 'permission' => 'projects.programs.view'],
                ['label' => 'محرر القوالب', 'route' => 'plan-templates.index', 'icon' => 'fa-sitemap', 'permission' => 'projects.templates.manage'],
                ['label' => 'الزيارات', 'route' => 'visits.index', 'icon' => 'fa-map-marker-alt', 'permission' => 'projects.visits.view'],
                ['label' => 'القياس والأثر', 'route' => 'measurement.index', 'icon' => 'fa-chart-pie', 'permission' => 'projects.measurement.view'],
            ],
        ],
        [
            'label' => 'المستندات',
            'items' => [
                ['label' => 'المستودع', 'route' => 'documents.index', 'icon' => 'fa-folder', 'permission' => 'documents.view'],
                ['label' => 'مكتبة القوالب', 'route' => 'documents.templates', 'icon' => 'fa-file-alt', 'permission' => 'documents.templates.manage'],
                ['label' => 'إدارة النسخ', 'route' => 'documents.versions', 'icon' => 'fa-code-branch', 'permission' => 'documents.manage-versions|documents.view'],
                ['label' => 'السياسات', 'route' => 'documents.policies', 'icon' => 'fa-balance-scale', 'permission' => 'documents.policies.manage'],
                ['label' => 'أرشيف المحاضر', 'route' => 'meetings.archive', 'icon' => 'fa-archive', 'permission' => 'meetings.view|documents.view'],
            ],
        ],
        [
            'label' => 'التقارير',
            'items' => [
                ['label' => 'مركز التقارير', 'route' => 'reports.center', 'icon' => 'fa-th-large', 'permission' => 'reports.view|reports.monthly.view|reports.projects.view|reports.impact.view|reports.kpis.view'],
                ['label' => 'التقرير الأسبوعي', 'route' => 'reports.index', 'icon' => 'fa-chart-bar', 'permission' => 'reports.view'],
                ['label' => 'سجل النشاط', 'route' => 'reports.audit-log', 'icon' => 'fa-history', 'permission' => 'reports.audit-log.view'],
            ],
        ],
        [
            'label' => 'الأقسام والهيكلة',
            'items' => [
                ['label' => 'الأقسام', 'route' => 'departments.index', 'icon' => 'fa-sitemap', 'permission' => 'structure.departments.view'],
                ['label' => 'الهيكل التنظيمي', 'route' => 'structure.org-tree', 'icon' => 'fa-network-wired', 'permission' => 'structure.view|structure.departments.view|structure.manage'],
            ],
        ],
        [
            'label' => 'الأدوار والصلاحيات',
            'items' => [
                ['label' => 'الأدوار والصلاحيات', 'route' => 'settings.grants', 'icon' => 'fa-shield-halved', 'permission' => 'roles.view'],
            ],
        ],
        [
            'label' => 'إعدادات المنصة',
            'items' => [
                ['label' => 'عامة', 'route' => 'settings.index', 'icon' => 'fa-cog', 'permission' => 'settings.manage|settings.general.manage|settings.finance.manage|settings.backup.manage'],
                ['label' => 'المالية', 'route' => 'settings.expenses', 'icon' => 'fa-coins', 'permission' => 'settings.manage|settings.finance.manage'],
                ['label' => 'SMTP', 'route' => 'settings.notifications', 'icon' => 'fa-envelope', 'permission' => 'settings.notifications.manage'],
            ],
        ],
    ],

    // Flat fallback for helpers/tests that still read primary
    'primary' => [],

    'secondary' => [],

];
