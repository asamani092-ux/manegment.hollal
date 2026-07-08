@php
    $nav = config('navigation');
    $secondaryRoutes = collect($nav['secondary'] ?? [])->pluck('route')->all();
    $secondaryActive = collect($secondaryRoutes)->contains(fn (string $route) => request()->routeIs($route));
@endphp

<aside class="ds-sidebar">
    @foreach ($nav['top'] ?? [] as $item)
        @can($item['permission'])
            <a href="{{ route($item['route']) }}"
               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endcan
    @endforeach

    @foreach ($nav['primary'] ?? [] as $item)
        @can($item['permission'])
            <a href="{{ route($item['route']) }}"
               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endcan
    @endforeach

    @php
        $hasSecondary = collect($nav['secondary'] ?? [])->contains(
            fn (array $item): bool => auth()->user()->can($item['permission'])
        );
    @endphp

    @if ($hasSecondary)
        <div class="ds-sidebar-more {{ $secondaryActive ? 'is-open' : '' }}" id="ds-sidebar-more">
            <button type="button"
                    class="ds-sidebar-more-toggle"
                    id="ds-sidebar-more-toggle"
                    aria-expanded="{{ $secondaryActive ? 'true' : 'false' }}"
                    aria-controls="ds-sidebar-more-panel">
                <i class="fas fa-ellipsis-h ds-sidebar-icon" aria-hidden="true"></i>
                <span class="ds-sidebar-label">المزيد</span>
                <svg class="ds-icon ds-icon-sm ds-sidebar-more-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
            <div class="ds-sidebar-more-panel" id="ds-sidebar-more-panel">
                @foreach ($nav['secondary'] ?? [] as $item)
                    @can($item['permission'])
                        <a href="{{ route($item['route']) }}"
                           class="ds-sidebar-item ds-sidebar-item-nested {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                            <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                            <span class="ds-sidebar-label">{{ $item['label'] }}</span>
                        </a>
                    @endcan
                @endforeach
            </div>
        </div>
    @endif
</aside>
