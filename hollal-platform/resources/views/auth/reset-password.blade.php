@extends('layouts.guest')

@section('content')
    <div class="ds-login-card">
        <div class="ds-login-header">
            <h1>تعيين كلمة مرور جديدة</h1>
        </div>

        @if ($errors->any())
            <div class="ds-alert ds-alert-error ds-alert-spaced">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-ds-form-group label="الجوال أو البريد" for="identifier">
                <input type="text" id="identifier" name="identifier" class="ds-input"
                       value="{{ old('identifier', $identifier) }}" required autocomplete="username">
            </x-ds-form-group>
            <x-ds-form-group label="كلمة المرور الجديدة" for="password" :error="$errors->first('password')">
                <input type="password" id="password" name="password" class="ds-input"
                       required autocomplete="new-password">
            </x-ds-form-group>
            <x-ds-form-group label="تأكيد كلمة المرور" for="password_confirmation">
                <input type="password" id="password_confirmation" name="password_confirmation" class="ds-input"
                       required autocomplete="new-password">
            </x-ds-form-group>
            <button type="submit" class="ds-btn ds-btn-primary ds-login-button ds-btn-block">
                حفظ كلمة المرور
            </button>
        </form>
    </div>
@endsection
