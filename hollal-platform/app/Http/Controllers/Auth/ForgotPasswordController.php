<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * GEN-7 — request a reset link by phone or email. MAIL_MAILER=log in trial.
 * Time: O(1) lookup | Space: O(1).
 */
class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($validated['identifier']);

        $user = User::query()
            ->where('phone', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if ($user && $user->is_active) {
            $token = Password::broker()->createToken($user);
            $user->notify(new PasswordResetLink($token));
        }

        return back()->with('success', 'إن وُجد الحساب ستصلك رسالة برابط تعيين كلمة مرور جديدة.');
    }
}
