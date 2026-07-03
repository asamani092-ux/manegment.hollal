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
        'label' => 'الأقسام',
        'route' => 'departments.index',
        'icon' => 'fa-sitemap',
        'permission' => 'departments.view',
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
