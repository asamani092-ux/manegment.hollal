@extends('layouts.guest')

@section('content')
    <div class="ds-login-card">
        <div class="ds-login-header">
            <img src="{{ asset('brand/logos/logo.svg') }}" alt="منصة حلل" class="ds-logo-img ds-login-logo">
            <h1>منصة حلل للإدارة</h1>
            <p>تسجيل الدخول إلى حسابك</p>
        </div>

        @if ($errors->any())
            <div class="ds-alert ds-alert-error ds-alert-spaced">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <x-ds-form-group label="رقم الجوال" for="phone">
                <input type="tel" id="phone" name="phone" class="ds-input"
                       value="{{ old('phone') }}" placeholder="05xxxxxxxx" required autofocus
                       inputmode="tel" autocomplete="tel">
            </x-ds-form-group>
            <x-ds-form-group label="كلمة المرور" for="password">
                <input type="password" id="password" name="password" class="ds-input"
                       placeholder="••••••••" required autocomplete="current-password">
            </x-ds-form-group>
            <div class="ds-form-group">
                <label class="ds-checkbox-label" for="remember">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <span>تذكرني</span>
                </label>
            </div>
            <button type="submit" class="ds-btn ds-btn-primary ds-login-button ds-btn-block" id="login-submit">
                <span class="ds-login-btn-label"><i class="fas fa-sign-in-alt"></i> دخول</span>
                <span class="ds-login-btn-busy" hidden><i class="fas fa-spinner fa-spin"></i> جاري تسجيل الدخول</span>
            </button>
            <p class="ds-text-muted" style="margin-top: 1rem; text-align: center;">
                <a href="{{ route('password.request') }}">نسيت كلمة المرور؟</a>
            </p>
        </form>
        <script>
            document.querySelector('form')?.addEventListener('submit', function () {
                var btn = document.getElementById('login-submit');
                if (!btn) { return; }
                btn.disabled = true;
                var label = btn.querySelector('.ds-login-btn-label');
                var busy = btn.querySelector('.ds-login-btn-busy');
                if (label) { label.hidden = true; }
                if (busy) { busy.hidden = false; }
            });
        </script>
    </div>
@endsection
