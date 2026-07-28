<?php

/**
 * Flat navigation — all tools visible per permission (no «المزيد»).
 * Group order follows spec tabs 1–11.
 */
return [

    'top' => [
        [
            'label' => 'الرئيسية',
            'route' => 'dashboard',
            'icon' => 'fa-home',
            'permission' => 'dashboard.view',
        ],
    ],

    'primary' => [
        // 1 — الموارد البشرية
        ['label' => 'الفريق', 'route' => 'users.index', 'icon' => 'fa-users', 'permission' => 'hr.employees.view'],
        ['label' => 'سلم الرواتب', 'route' => 'pay-scales.index', 'icon' => 'fa-layer-group', 'permission' => 'hr.salaries.manage'],
        ['label' => 'مسيّرات الرواتب', 'route' => 'payroll-runs.index', 'icon' => 'fa-file-invoice-dollar', 'permission' => 'hr.salaries.view'],
        ['label' => 'الرواتب', 'route' => 'payroll.index', 'icon' => 'fa-wallet', 'permission' => 'hr.salaries.view'],

        // 2 — إسناد
        ['label' => 'المهام', 'route' => 'tasks.index', 'icon' => 'fa-tasks', 'permission' => 'esnad.tasks.view'],
        ['label' => 'مهام الفريق', 'route' => 'team-tasks.index', 'icon' => 'fa-people-carry', 'permission' => 'esnad.tasks.team.view'],
        ['label' => 'تقويم المهام', 'route' => 'tasks-calendar.index', 'icon' => 'fa-calendar', 'permission' => 'esnad.tasks.view'],
        ['label' => 'المهام المتكررة', 'route' => 'recurring-tasks.index', 'icon' => 'fa-redo', 'permission' => 'esnad.tasks.create'],
        ['label' => 'عبء العمل', 'route' => 'workload-board.index', 'icon' => 'fa-chart-area', 'permission' => 'esnad.tasks.team.view'],

        // 3 — الاجتماعات
        ['label' => 'الاجتماعات', 'route' => 'meetings.index', 'icon' => 'fa-calendar-alt', 'permission' => 'meetings.view'],
        ['label' => 'القرارات المفتوحة', 'route' => 'meetings.open-decisions', 'icon' => 'fa-gavel', 'permission' => 'meetings.view'],

        // 4 — المالية
        ['label' => 'طلبات الصرف', 'route' => 'expenses.index', 'icon' => 'fa-money-bill-wave', 'permission' => 'finance.expenses.view|finance.expenses.create|finance.expenses.approve|finance.expenses.pay'],
        ['label' => 'العهد', 'route' => 'custodies.index', 'icon' => 'fa-hand-holding-usd', 'permission' => 'finance.custodies.view|finance.custodies.approve|finance.custodies.disburse'],
        ['label' => 'الأصول', 'route' => 'assets.index', 'icon' => 'fa-boxes', 'permission' => 'finance.assets.view|finance.assets.manage'],
        ['label' => 'الإيرادات', 'route' => 'revenues.index', 'icon' => 'fa-coins', 'permission' => 'finance.revenues.view|finance.revenues.manage'],
        ['label' => 'الفواتير الضريبية', 'route' => 'tax-invoices.index', 'icon' => 'fa-file-invoice', 'permission' => 'finance.tax_invoices.view'],
        ['label' => 'الموازنات', 'route' => 'budgets.index', 'icon' => 'fa-balance-scale-left', 'permission' => 'finance.budgets.view'],
        ['label' => 'التقارير المالية', 'route' => 'financial-reports.index', 'icon' => 'fa-chart-line', 'permission' => 'finance.reports.view'],
        ['label' => 'المستندات المالية', 'route' => 'financial-documents.index', 'icon' => 'fa-folder-open', 'permission' => 'finance.revenues.view'],

        // 5 — الشراكات
        ['label' => 'الجهات الشريكة', 'route' => 'organizations.index', 'icon' => 'fa-building', 'permission' => 'partnerships.organizations.view'],
        ['label' => 'رحلة الشراكات', 'route' => 'partnerships.pipeline', 'icon' => 'fa-stream', 'permission' => 'partnerships.pipeline.view'],
        ['label' => 'العقود', 'route' => 'contracts.index', 'icon' => 'fa-file-contract', 'permission' => 'partnerships.contracts.view'],

        // 6 — البرامج والقوالب
        ['label' => 'مكتبة البرامج', 'route' => 'programs.index', 'icon' => 'fa-book', 'permission' => 'projects.programs.view'],
        ['label' => 'قوالب الخطط', 'route' => 'plan-templates.index', 'icon' => 'fa-sitemap', 'permission' => 'projects.templates.manage'],

        // 7 — المشاريع
        ['label' => 'المشاريع', 'route' => 'projects.index', 'icon' => 'fa-project-diagram', 'permission' => 'projects.view'],

        // 8 — المستندات
        ['label' => 'المستندات', 'route' => 'documents.index', 'icon' => 'fa-folder', 'permission' => 'documents.view'],
        ['label' => 'نماذج المستندات', 'route' => 'documents.templates', 'icon' => 'fa-file-alt', 'permission' => 'documents.templates.manage'],
        ['label' => 'السياسات والمهام', 'route' => 'documents.policies', 'icon' => 'fa-balance-scale', 'permission' => 'documents.policies.manage'],

        // 9 — التقارير
        ['label' => 'مركز التقارير', 'route' => 'reports.center', 'icon' => 'fa-th-large', 'permission' => 'reports.view|reports.monthly.view|reports.projects.view|reports.impact.view|reports.kpis.view'],
        ['label' => 'التقارير', 'route' => 'reports.index', 'icon' => 'fa-chart-bar', 'permission' => 'reports.view'],
        ['label' => 'سجل النشاط', 'route' => 'reports.audit-log', 'icon' => 'fa-history', 'permission' => 'reports.audit-log.view'],

        // 10 — الهيكل
        ['label' => 'الأقسام', 'route' => 'departments.index', 'icon' => 'fa-sitemap', 'permission' => 'structure.departments.view'],
        ['label' => 'الهيكل التنظيمي', 'route' => 'structure.org-tree', 'icon' => 'fa-network-wired', 'permission' => 'structure.view|structure.departments.view|structure.manage'],

        // 11 — الإعدادات والأدوار
        ['label' => 'الإعدادات', 'route' => 'settings.index', 'icon' => 'fa-cog', 'permission' => 'settings.manage|settings.general.manage|settings.finance.manage|settings.backup.manage'],
        ['label' => 'الأدوار والصلاحيات', 'route' => 'settings.roles', 'icon' => 'fa-shield-halved', 'permission' => 'roles.view'],
        ['label' => 'منح استثنائية', 'route' => 'settings.grants', 'icon' => 'fa-key', 'permission' => 'roles.view'],
        ['label' => 'إشعارات البريد', 'route' => 'settings.notifications', 'icon' => 'fa-envelope', 'permission' => 'settings.notifications.manage'],
    ],

    'secondary' => [],

];
