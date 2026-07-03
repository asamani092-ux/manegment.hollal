@php
    $class = match ($priority) {
        'low' => 'ds-badge-code',
        'medium' => 'ds-badge-pending',
        'high' => 'ds-badge-paid',
        'urgent' => 'ds-badge-danger',
        default => 'ds-badge-code',
    };
    $labels = [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'مرتفع',
        'urgent' => 'عاجل',
    ];
@endphp
<span class="ds-badge {{ $class }}">{{ $labels[$priority] ?? $priority }}</span>
