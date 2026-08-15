@props([
    'label' => '',
    'for' => null,
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'ds-form-group']) }}>
    @if ($label)
        <label @if($for) for="{{ $for }}" @endif>{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($hint)
        <p class="ds-field-hint ds-text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <span class="ds-alert ds-alert-error ds-field-error">{{ $error }}</span>
    @endif
</div>
