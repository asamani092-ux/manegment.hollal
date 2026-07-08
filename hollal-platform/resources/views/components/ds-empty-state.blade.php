@props([
    'message' => 'لا توجد بيانات',
    'icon' => 'fa-inbox',
])

<div {{ $attributes->merge(['class' => 'ds-empty-state']) }} role="status">
    <i class="fas {{ $icon }} ds-empty-state-icon" aria-hidden="true"></i>
    <p>{{ $message }}</p>
</div>
