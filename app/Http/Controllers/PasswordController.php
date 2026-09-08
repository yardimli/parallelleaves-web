<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordController extends Controller
{
    public function request()
    {
        return view('pages.forgot-password');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        try {
            Password::sendResetLink($data);
        } catch (Throwable $exception) {
            // Do not expose provider responses, credentials, or account existence.
            logger()->error('Password recovery email failed.', ['exception_type' => get_class($exception)]);
        }

        return back()->with('status', 'If an account uses this email, a password reset link will arrive shortly. Check your spam folder, or contact support if it does not arrive.');
    }

    public function form(Request $request, string $token)
    {
        return view('pages.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill([
                'password_hash' => Hash::make($password),
                'remember_token' => Str::random(60),
                'session_token' => null,
                'token_expires_at' => null,
            ])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This reset link is invalid or has expired. Request a new link.'])->withInput($request->only('email'));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login')->with('status', 'Password updated. Sign in with your new password.');
    }
}
