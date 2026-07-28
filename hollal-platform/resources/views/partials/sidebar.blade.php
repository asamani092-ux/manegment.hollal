@php
    $nav = config('navigation');
@endphp

<aside class="ds-sidebar">
    @foreach ($nav['top'] ?? [] as $item)
        @if (\App\Support\NavigationHelper::userCanSee($item['permission']))
            <a href="{{ route($item['route']) }}"
               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach

    @foreach ($nav['groups'] ?? [] as $group)
        @if (\App\Support\NavigationHelper::userCanSeeGroup($group))
            <div class="ds-sidebar-group">
                <div class="ds-sidebar-group-label">{{ $group['label'] }}</div>
                @foreach ($group['items'] ?? [] as $item)
                    @if (\App\Support\NavigationHelper::userCanSee($item['permission']))
                        <a href="{{ route($item['route']) }}"
                           class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                            <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                            <span class="ds-sidebar-label">{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    @endforeach

    @foreach ($nav['primary'] ?? [] as $item)
        @if (\App\Support\NavigationHelper::userCanSee($item['permission']))
            <a href="{{ route($item['route']) }}"
               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach
</aside>
