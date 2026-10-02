<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'الرئيسية' }} — منصة حلل</title>
    <link rel="stylesheet" href="{{ asset('css/hollal-ds.css') }}?v={{ max(@filemtime(public_path('css/components.css')) ?: 0, @filemtime(public_path('css/layout.css')) ?: 0) ?: '1' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @livewireStyles
</head>
<body>
    @include('partials.navbar')

    <div class="ds-sidebar-backdrop" id="ds-sidebar-backdrop" aria-hidden="true"></div>

    <div class="ds-main-layout">
        @include('partials.sidebar')

        <main class="ds-content ds-page-rtl" dir="rtl">
            @auth
                @php
                    $activeDelegation = \App\Models\Delegation::query()
                        ->where('status', \App\Models\Delegation::STATUS_ACTIVE)
                        ->where(function ($q) {
                            $q->where('delegate_id', auth()->id())->orWhere('delegator_id', auth()->id());
                        })
                        ->whereDate('starts_on', '<=', today())
                        ->whereDate('ends_on', '>=', today())
                        ->with(['delegator:id,name', 'delegate:id,name'])
                        ->first();
                @endphp
                @if ($activeDelegation && (int) $activeDelegation->delegate_id === (int) auth()->id())
                    <p class="ds-badge ds-badge-warning">أنت تعمل بالإنابة عن {{ $activeDelegation->delegator?->name }} حتى {{ $activeDelegation->ends_on?->toDateString() }}</p>
                @elseif ($activeDelegation && (int) $activeDelegation->delegator_id === (int) auth()->id())
                    <p class="ds-badge ds-badge-warning">إنابتك سارية حتى {{ $activeDelegation->ends_on?->toDateString() }} لصالح {{ $activeDelegation->delegate?->name }}</p>
                @endif
            @endauth
            @hasSection('content')
                @yield('content')
            @else
                {{ $slot ?? '' }}
            @endif
        </main>
    </div>

    <x-ds-toast />

    @livewireScripts
    @include('partials.app-shell-scripts')
    @stack('scripts')
</body>
</html>
