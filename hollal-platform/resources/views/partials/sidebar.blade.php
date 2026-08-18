@php
    $nav = config('navigation');
@endphp

<aside class="ds-sidebar">
    <div class="ds-sidebar-search">
        <i class="fas fa-magnifying-glass ds-sidebar-search-icon" aria-hidden="true"></i>
        <input type="search"
               id="ds-nav-search"
               class="ds-sidebar-search-input"
               placeholder="ابحث في القوائم…"
               aria-label="بحث في القوائم"
               autocomplete="off">
    </div>

    @foreach ($nav['top'] ?? [] as $item)
        @if (\App\Support\NavigationHelper::itemIsVisible($item) && \App\Support\NavigationHelper::userCanSee($item['permission']))
            <a href="{{ route($item['route']) }}"
               wire:navigate
               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}"
               data-nav-label="{{ $item['label'] }}">
                <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach

    @foreach ($nav['groups'] ?? [] as $group)
        @if (\App\Support\NavigationHelper::userCanSeeGroup($group))
            @php
                $groupIsActive = collect($group['items'] ?? [])
                    ->contains(fn ($item) => request()->routeIs($item['route']));
            @endphp
            <div class="ds-sidebar-group {{ $groupIsActive ? 'is-open is-active' : '' }}"
                 data-nav-group="{{ $group['label'] }}">
                <button type="button"
                        class="ds-sidebar-group-label"
                        aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}">
                    <span>{{ $group['label'] }}</span>
                    <i class="fas fa-chevron-down ds-sidebar-group-chevron" aria-hidden="true"></i>
                </button>
                <div class="ds-sidebar-group-items">
                    @foreach ($group['items'] ?? [] as $item)
                        @if (\App\Support\NavigationHelper::userCanSee($item['permission']))
                            <a href="{{ route($item['route']) }}"
                               wire:navigate
                               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}"
                               data-nav-label="{{ $item['label'] }}">
                                <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    <p class="ds-sidebar-no-results" hidden>لا توجد أداة بهذا الاسم</p>

    @foreach ($nav['primary'] ?? [] as $item)
        @if (\App\Support\NavigationHelper::userCanSee($item['permission']))
            <a href="{{ route($item['route']) }}"
               wire:navigate
               class="ds-sidebar-item {{ request()->routeIs($item['route']) ? 'active' : '' }}"
               data-nav-label="{{ $item['label'] }}">
                <i class="fas {{ $item['icon'] }} ds-sidebar-icon" aria-hidden="true"></i>
                <span class="ds-sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach
</aside>
