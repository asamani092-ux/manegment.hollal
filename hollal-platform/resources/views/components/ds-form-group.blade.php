@props([
    'label' => '',
    'for' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'ds-form-group']) }}>
    @if ($label)
        <label @if($for) for="{{ $for }}" @endif>{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($error)
        <span class="ds-alert ds-alert-error ds-field-error">{{ $error }}</span>
    @endif
</div>
