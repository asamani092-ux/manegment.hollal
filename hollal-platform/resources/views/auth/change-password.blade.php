@extends('layouts.app')

@section('title', 'تغيير كلمة المرور')

@section('content')
    <h1 class="ds-page-title">تغيير كلمة المرور</h1>
    <p class="ds-text-muted ds-section-spaced">يجب تغيير كلمة المرور قبل متابعة استخدام المنصة.</p>

    <div class="ds-login-card ds-change-password-card">
        @if ($errors->any())
            <div class="ds-alert ds-alert-error ds-alert-spaced">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}">
            @csrf
            <x-ds-form-group label="كلمة المرور الحالية" for="current_password" :error="$errors->first('current_password')">
                <input type="password" id="current_password" name="current_password" class="ds-input"
                       placeholder="••••••••" required autofocus autocomplete="current-password">
            </x-ds-form-group>
            <x-ds-form-group label="كلمة المرور الجديدة" for="password" :error="$errors->first('password')">
                <input type="password" id="password" name="password" class="ds-input"
                       placeholder="••••••••" required autocomplete="new-password">
            </x-ds-form-group>
            <x-ds-form-group label="تأكيد كلمة المرور" for="password_confirmation">
                <input type="password" id="password_confirmation" name="password_confirmation" class="ds-input"
                       placeholder="••••••••" required autocomplete="new-password">
            </x-ds-form-group>
            <button type="submit" class="ds-btn ds-btn-primary ds-login-button ds-btn-block">
                <i class="fas fa-key"></i> حفظ كلمة المرور
            </button>
        </form>
    </div>
@endsection
