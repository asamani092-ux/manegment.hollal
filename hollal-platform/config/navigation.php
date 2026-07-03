<?php

return [
    [
        'label' => 'الرئيسية',
        'route' => 'dashboard',
        'icon' => 'fa-home',
        'permission' => 'dashboard.view',
    ],
    [
        'label' => 'المشاريع',
        'route' => 'projects.index',
        'icon' => 'fa-project-diagram',
        'permission' => 'projects.view',
    ],
    [
        'label' => 'إسناد',
        'route' => 'tasks.index',
        'icon' => 'fa-tasks',
        'permission' => 'tasks.view',
    ],
    [
        'label' => 'الاجتماعات',
        'route' => 'meetings.index',
        'icon' => 'fa-calendar-alt',
        'permission' => 'meetings.view',
    ],
    [
        'label' => 'الرواتب',
        'route' => 'payroll.index',
        'icon' => 'fa-money-bill-wave',
        'permission' => 'salaries.view',
    ],
    [
        'label' => 'المستندات',
        'route' => 'documents.index',
        'icon' => 'fa-folder-open',
        'permission' => 'documents.view',
    ],
    [
        'label' => 'المصروفات',
        'route' => 'expenses.index',
        'icon' => 'fa-money-bill-wave',
        'permission' => 'expenses.view',
    ],
    [
        'label' => 'الأقسام',
        'route' => 'departments.index',
        'icon' => 'fa-sitemap',
        'permission' => 'departments.view',
    ],
    [
        'label' => 'العقود',
        'route' => 'contracts.index',
        'icon' => 'fa-file-contract',
        'permission' => 'contracts.view',
    ],
    [
        'label' => 'التقارير',
        'route' => 'reports.index',
        'icon' => 'fa-chart-bar',
        'permission' => 'reports.view',
    ],
    [
        'label' => 'الفريق',
        'route' => 'users.index',
        'icon' => 'fa-users',
        'permission' => 'users.view',
    ],
    [
        'label' => 'الأدوار والصلاحيات',
        'route' => 'settings.roles',
        'icon' => 'fa-shield-halved',
        'permission' => 'roles.view',
    ],
];
