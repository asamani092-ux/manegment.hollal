@props(['name' => ''])

@php
    $labels = [
        'dashboard.view' => 'عرض الرئيسية',
        'users.view' => 'عرض المستخدمين',
        'users.create' => 'إنشاء مستخدم',
        'users.update' => 'تعديل مستخدم',
        'users.delete' => 'حذف مستخدم',
        'roles.view' => 'عرض الأدوار',
        'roles.create' => 'إنشاء دور',
        'roles.update' => 'تعديل دور',
        'roles.delete' => 'حذف دور',
        'departments.view' => 'عرض الأقسام',
        'departments.create' => 'إنشاء قسم',
        'departments.update' => 'تعديل قسم',
        'departments.delete' => 'حذف قسم',
        'projects.view' => 'عرض المشاريع',
        'projects.create' => 'إنشاء مشروع',
        'projects.update' => 'تعديل مشروع',
        'projects.delete' => 'حذف مشروع',
        'partnerships.view' => 'عرض الشراكات',
        'partnerships.create' => 'إنشاء شراكة',
        'partnerships.update' => 'تعديل شراكة',
        'partnerships.delete' => 'حذف شراكة',
        'tasks.view' => 'عرض المهام',
        'tasks.create' => 'إنشاء مهمة',
        'tasks.update' => 'تعديل مهمة',
        'tasks.delete' => 'حذف مهمة',
    ];
@endphp

{{ $labels[$name] ?? $name }}
