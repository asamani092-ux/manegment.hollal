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
        'مؤرشف' => 'muted',
        'مفتوحة' => 'info',
        'قيد_التقييم' => 'info',
        'مكتمل' => 'success',
        'غير مكتمل' => 'warning',
        'مرفوض' => 'danger',
        'مرفوضة' => 'danger',
        'ملغاة' => 'danger',
        'متأخرة' => 'danger',
        'موقوفة' => 'danger',
        'منتهية' => 'danger',
        'منتهية_علاقته' => 'danger',

        // 04-B5 / Wave D-deep — asset condition colors: جيد=أخضر · صيانة=أصفر · تالف=أحمر · مستبعد=بني
        'جيد' => 'success',
        'صيانة' => 'warning',
        'تالف' => 'danger',
        'مستبعد' => 'brown',
    ];

    $tone = $variant ?? ($palette[trim((string) $status)] ?? 'info');
@endphp

<span {{ $attributes->merge(['class' => 'ds-badge ds-badge-'.$tone]) }}>{{ $status !== '' ? $status : '—' }}</span>
