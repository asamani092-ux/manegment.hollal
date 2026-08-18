@props([
    'title' => '',
    'showButton' => false,
    'buttonLabel' => 'إضافة',
    'buttonIcon' => 'fa-plus',
    'buttonPermission' => null,
    'backUrl' => null,
    'backLabel' => 'رجوع',
])

<div class="ds-page-header-bar">
    <div>
        @if ($backUrl)
            <a href="{{ $backUrl }}" wire:navigate class="ds-btn ds-btn-sm" style="margin-bottom: 0.5rem;">
                <i class="fas fa-arrow-right" aria-hidden="true"></i> {{ $backLabel }}
            </a>
        @endif
        <h1 class="ds-page-title">{{ $title }}</h1>
    </div>
    @if (isset($actions))
        <div class="ds-toolbar-actions">{{ $actions }}</div>
    @elseif ($showButton && ($buttonPermission === null || auth()->user()->can($buttonPermission)))
        <button type="button" {{ $attributes->merge(['class' => 'ds-btn ds-btn-primary']) }}>
            <i class="fas {{ $buttonIcon }}"></i> {{ $buttonLabel }}
        </button>
    @endif
</div>
