@php
    $class = match ($status) {
        'new' => 'ds-badge-code',
        'in_progress' => 'ds-badge-pending',
        'pending_review' => 'ds-badge-pending',
        'completed' => 'ds-badge-success',
        'overdue' => 'ds-badge-danger',
        default => 'ds-badge-code',
    };
    $labels = [
        'new' => 'جديدة',
        'in_progress' => 'قيد التنفيذ',
        'pending_review' => 'بانتظار المراجعة',
        'completed' => 'مكتملة',
        'overdue' => 'متأخرة',
    ];
@endphp
<span class="ds-badge {{ $class }}">{{ $labels[$status] ?? $status }}</span>
