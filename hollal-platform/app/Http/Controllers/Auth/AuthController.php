<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Manual authentication by phone — no Breeze, no Tailwind.
 */
class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $credentials['phone'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withErrors(['phone' => 'محاولات كثيرة. حاول لاحقاً.'])
                ->onlyInput('phone');
        }

        if (! Auth::attempt(
            ['phone' => $credentials['phone'], 'password' => $credentials['password'], 'is_active' => true],
            $request->boolean('remember')
        )) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['phone' => 'رقم الجوال أو كلمة المرور غير صحيحة.'])
                ->onlyInput('phone');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
