<aside class="ds-sidebar">
    @foreach (config('navigation') as $item)
        @can($item['permission'])
            <a href="{{ route($item['route']) }}"
               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="fas {{ $item['icon'] }} ds-sidebar-icon"></i>
                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endcan
    @endforeach
</aside>
