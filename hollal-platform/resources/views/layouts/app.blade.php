<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'الرئيسية' }} — منصة حلل</title>
    <link rel="stylesheet" href="{{ asset('css/hollal-ds.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @livewireStyles
</head>
<body>
    @include('partials.navbar')

    <div class="ds-sidebar-backdrop" id="ds-sidebar-backdrop" aria-hidden="true"></div>

    <div class="ds-main-layout">
        @include('partials.sidebar')

        <main class="ds-content">
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
