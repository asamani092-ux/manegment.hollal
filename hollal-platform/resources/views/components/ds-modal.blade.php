@props([
    'show' => true,
    'title' => '',
    'size' => '',
])

@if ($show)
    <div {{ $attributes->merge(['class' => 'ds-modal-overlay']) }} dir="rtl">
        <div @class(['ds-modal', 'ds-modal-lg' => $size === 'lg']) role="dialog" dir="rtl">
            @if (isset($header))
                <div class="ds-modal-header">{{ $header }}</div>
            @endif
            <div class="ds-modal-body">{{ $body ?? $slot }}</div>
            @if (isset($footer))
                <div class="ds-modal-footer">{{ $footer }}</div>
            @endif
        </div>
    </div>
@endif
