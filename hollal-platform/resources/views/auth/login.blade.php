@extends('layouts.guest')

@section('content')
    <div class="ds-login-card">
        <div class="ds-login-header">
            <img src="{{ asset('assets/logos/logo.svg') }}" alt="منصة حلل" class="ds-logo-img ds-login-logo">
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
            <div class="ds-form-group ds-inline-checkbox-row">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">تذكرني</label>
            </div>
            <button type="submit" class="ds-btn ds-btn-primary ds-login-button ds-btn-block">
                <i class="fas fa-sign-in-alt"></i> دخول
            </button>
        </form>
    </div>
@endsection
