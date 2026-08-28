<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'تسجيل الدخول' }} — منصة حلل</title>
    @php
        $cssVer = max(
            @filemtime(public_path('css/components.css')) ?: 0,
            @filemtime(public_path('css/layout.css')) ?: 0
        ) ?: 1;
    @endphp
    <style>
        html, body { margin: 0; min-height: 100%; background: #0f172a; color: #f8fafc; font-family: system-ui, sans-serif; }
        .ds-login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .ds-login-container { width: 100%; max-width: 28rem; }
        .ds-login-card { background: #1e293b; border-radius: 1rem; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,.35); }
    </style>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ $cssVer }}">
    <link rel="stylesheet" href="{{ asset('css/tokens-light.css') }}?v={{ $cssVer }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}?v={{ $cssVer }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ $cssVer }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}?v={{ $cssVer }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @livewireStyles
</head>
<body>
    <div class="ds-login-page">
        <div class="ds-login-container">
            @hasSection('content')
                @yield('content')
            @else
                {{ $slot ?? '' }}
            @endif
        </div>
    </div>
    @livewireScripts
</body>
</html>
