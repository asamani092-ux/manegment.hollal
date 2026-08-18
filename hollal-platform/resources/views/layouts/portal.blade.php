<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'بوابة الجهة' }} — منصة حلل</title>
    <link rel="stylesheet" href="{{ asset('css/hollal-ds.css') }}?v={{ max(@filemtime(public_path('css/components.css')) ?: 0, @filemtime(public_path('css/layout.css')) ?: 0) ?: '1' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @livewireStyles
</head>
<body class="ds-portal-body">
    <main class="ds-portal-shell">
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </main>
    <x-ds-toast />
    @livewireScripts
</body>
</html>
