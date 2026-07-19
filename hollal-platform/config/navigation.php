<?php

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
        [
            'label' => 'الفريق',
            'route' => 'users.index',
            'icon' => 'fa-users',
            'permission' => 'hr.employees.view',
        ],
        [
            'label' => 'إسناد',
            'route' => 'tasks.index',
            'icon' => 'fa-tasks',
            'permission' => 'esnad.tasks.view',
        ],
        [
            'label' => 'الاجتماعات',
            'route' => 'meetings.index',
            'icon' => 'fa-calendar-alt',
            'permission' => 'meetings.view',
        ],
        [
            'label' => 'المالية',
            'route' => 'expenses.index',
            'icon' => 'fa-money-bill-wave',
            'permission' => 'finance.expenses.view',
        ],
        [
            'label' => 'المشاريع',
            'route' => 'projects.index',
            'icon' => 'fa-project-diagram',
            'permission' => 'projects.view',
        ],
    ],

    'secondary' => [
        [
            'label' => 'المستندات',
            'route' => 'documents.index',
            'icon' => 'fa-folder-open',
            'permission' => 'documents.view',
        ],
        [
            'label' => 'نماذج المستندات',
            'route' => 'documents.templates',
            'icon' => 'fa-file-alt',
            'permission' => 'documents.templates.manage',
        ],
        [
            'label' => 'السياسات والمهام',
            'route' => 'documents.policies',
            'icon' => 'fa-balance-scale',
            'permission' => 'documents.policies.manage',
        ],
        [
            'label' => 'العقود',
            'route' => 'contracts.index',
            'icon' => 'fa-file-contract',
            'permission' => 'partnerships.contracts.view',
        ],
        [
            'label' => 'التقارير',
            'route' => 'reports.index',
            'icon' => 'fa-chart-bar',
            'permission' => 'reports.view',
        ],
        [
            'label' => 'الأقسام',
            'route' => 'departments.index',
            'icon' => 'fa-sitemap',
            'permission' => 'structure.departments.view',
        ],
        [
            'label' => 'الرواتب',
            'route' => 'payroll.index',
            'icon' => 'fa-wallet',
            'permission' => 'hr.salaries.view',
        ],
        [
            'label' => 'الأدوار والصلاحيات',
            'route' => 'settings.roles',
            'icon' => 'fa-shield-halved',
            'permission' => 'roles.view',
        ],
    ],

];
