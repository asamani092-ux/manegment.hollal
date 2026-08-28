@props([
    'title' => '',
    'open' => false,
])

<details {{ $attributes->class(['ds-collapsible-card']) }} @if ($open) open @endif>
    <summary class="ds-collapsible-card__summary">
        <span>{{ $title }}</span>
        @isset($actions)
            <span class="ds-collapsible-card__actions" @click.stop>
                {{ $actions }}
            </span>
        @endisset
    </summary>
    <div class="ds-collapsible-card__body">
        {{ $slot }}
    </div>
</details>
