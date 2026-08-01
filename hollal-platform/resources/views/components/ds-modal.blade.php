@props([
    'show' => true,
    'title' => '',
    'size' => '',
    'closeAction' => null,
])

@if ($show)
    <div {{ $attributes->merge(['class' => 'ds-modal-overlay']) }}
         dir="rtl"
         @if ($closeAction)
             wire:click.self="{{ $closeAction }}"
             wire:keydown.escape.window="{{ $closeAction }}"
         @endif
    >
        <div @class(['ds-modal', 'ds-modal-lg' => $size === 'lg'])
             role="dialog"
             aria-modal="true"
             @if ($title) aria-label="{{ $title }}" @endif
             dir="rtl"
        >
            @if (isset($header))
                <div class="ds-modal-header">
                    {{ $header }}
                    @if ($closeAction)
                        <button type="button" class="ds-modal-close" aria-label="إغلاق" wire:click="{{ $closeAction }}">&times;</button>
                    @endif
                </div>
            @elseif ($title)
                <div class="ds-modal-header">
                    <h3>{{ $title }}</h3>
                    @if ($closeAction)
                        <button type="button" class="ds-modal-close" aria-label="إغلاق" wire:click="{{ $closeAction }}">&times;</button>
                    @endif
                </div>
            @endif
            <div class="ds-modal-body">{{ $body ?? $slot }}</div>
            @if (isset($footer))
                <div class="ds-modal-footer">{{ $footer }}</div>
            @endif
        </div>
    </div>
@endif
