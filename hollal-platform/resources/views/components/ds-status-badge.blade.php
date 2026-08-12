@props([
    'status' => '',
    'variant' => null,
])

@php
    $palette = [
        'معتمد' => 'success',
        'معتمدة' => 'success',
        'منشور' => 'success',
        'مدفوع' => 'paid',
        'مدفوعة' => 'paid',
        'مسددة' => 'paid',
        'مكتملة' => 'success',
        'منفذ' => 'success',
        'منفذة' => 'success',
        'معاد_للتصحيح' => 'warning',
        'مُعاد للتصحيح' => 'warning',
        'مؤكدة' => 'success',
        'نشط' => 'success',
        'نشطة' => 'success',
        'سارية' => 'success',
        'مقدم' => 'pending',
        'مقدمة' => 'pending',
        'قيد المراجعة' => 'pending',
        'بانتظار الاعتماد' => 'pending',
        'قيد التنفيذ' => 'info',
        'مخططة' => 'info',
        'مسودة' => 'muted',
        'مغلقة' => 'muted',
        'مؤرشفة' => 'muted',
        'مرفوض' => 'danger',
        'مرفوضة' => 'danger',
        'ملغاة' => 'danger',
        'متأخرة' => 'danger',
        'موقوفة' => 'danger',
        'منتهية' => 'danger',
        'منتهية_علاقته' => 'danger',
    ];

    $tone = $variant ?? ($palette[trim((string) $status)] ?? 'info');
@endphp

<span {{ $attributes->merge(['class' => 'ds-badge ds-badge-'.$tone]) }}>{{ $status !== '' ? $status : '—' }}</span>
