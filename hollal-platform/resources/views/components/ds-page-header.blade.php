@props([
    'title' => '',
    'showButton' => false,
    'buttonLabel' => 'إضافة',
    'buttonIcon' => 'fa-plus',
    'buttonPermission' => null,
])

<div class="ds-page-header-bar">
    <h1 class="ds-page-title">{{ $title }}</h1>
    @if ($showButton && ($buttonPermission === null || auth()->user()->can($buttonPermission)))
        <button type="button" {{ $attributes->merge(['class' => 'ds-btn ds-btn-primary']) }}>
            <i class="fas {{ $buttonIcon }}"></i> {{ $buttonLabel }}
        </button>
    @endif
</div>
