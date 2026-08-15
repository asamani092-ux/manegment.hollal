@extends('layouts.guest')

@section('content')
    <div class="ds-login-card">
        <div class="ds-login-header">
            <h1>نسيت كلمة المرور</h1>
            <p>أدخل رقم الجوال أو البريد لإرسال رابط التعيين</p>
        </div>

        @if ($errors->any())
            <div class="ds-alert ds-alert-error ds-alert-spaced">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <x-ds-form-group label="الجوال أو البريد" for="identifier" :error="$errors->first('identifier')">
                <input type="text" id="identifier" name="identifier" class="ds-input"
                       value="{{ old('identifier') }}" required autofocus autocomplete="username">
            </x-ds-form-group>
            <button type="submit" class="ds-btn ds-btn-primary ds-login-button ds-btn-block">
                إرسال الرابط
            </button>
            <p class="ds-text-muted" style="margin-top: 1rem; text-align: center;">
                <a href="{{ route('login') }}">رجوع لتسجيل الدخول</a>
            </p>
        </form>
    </div>
@endsection
