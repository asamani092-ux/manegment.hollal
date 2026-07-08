@props(['name' => ''])

@php
    $labels = [
        'Super Admin' => 'مدير النظام',
        'General Manager' => 'المدير العام',
        'Executive Manager' => 'المدير التنفيذي',
        'Project Manager' => 'مدير مشروع',
        'Finance' => 'المالية',
        'Employee' => 'موظف',
    ];
@endphp

{{ $labels[$name] ?? $name }}
