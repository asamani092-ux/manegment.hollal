<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * GEN-7 — set a new password from the emailed/logged reset link.
 * Time: O(1) | Space: O(1).
 */
class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'identifier' => (string) $request->query('identifier', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()
            ->where('phone', $validated['identifier'])
            ->orWhere('email', $validated['identifier'])
            ->first();

        if (! $user) {
            return back()->withErrors(['identifier' => 'تعذر التحقق من الرابط.']);
        }

        $status = Password::broker()->reset(
            [
                'email' => $user->getEmailForPasswordReset(),
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $resetUser, string $password): void {
                $resetUser->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($resetUser));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['identifier' => 'الرابط غير صالح أو منتهٍ. اطلب رابطاً جديداً.']);
        }

        return redirect()->route('login')->with('success', 'تم تعيين كلمة المرور. يمكنك تسجيل الدخول الآن.');
    }
}
