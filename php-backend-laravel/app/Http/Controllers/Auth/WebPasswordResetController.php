<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * "Forgot / set password" for public website users — the companion to
 * {@see WebPasswordAuthController}. Existing accounts made via OTP or Google
 * hold a random password, so this is how their owners set a real one and start
 * signing in with email + password.
 *
 * Isolated from the admin/Filament reset flows on purpose: it uses the standard
 * password broker for tokens (Password::broker()->createToken / Password::reset,
 * same password_reset_tokens table) but sends the link through the admin-managed
 * sender pool ({@see EmailOtpService}) — the only working mail transport here —
 * with a URL that points at the SITE reset page, never the admin one.
 */
class WebPasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('site.auth.forgot-password');
    }

    public function sendResetLink(Request $request, EmailOtpService $mailer): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $email = Str::lower(trim($request->input('email')));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        // Only actually send when the account exists — but always show the same
        // message so this can't be used to probe which emails are registered.
        if ($user) {
            $token = Password::broker()->createToken($user);
            $url = url(route('site.password.reset', ['token' => $token], false)) . '?email=' . urlencode($user->email);

            $mailer->send(
                $user->email,
                'Reset your Haraan password',
                "Hi {$user->name},\n\nTap the link below to set a new password. It expires in 60 minutes.\n\n{$url}\n\nIf you didn't request this, you can ignore this email.",
                $this->emailHtml($user->name, $url),
            );
        }

        return back()->with('status', "If that email has a Haraan account, we've sent a reset link. Check your inbox.");
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('site.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:6', 'max:255'],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'Your password must be at least 6 characters.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
                Auth::login($user, true);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            request()->session()->regenerate();

            return redirect()->intended('/')->with('success', "Password updated — you're signed in.");
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    /** Minimal branded HTML body for the reset email. */
    private function emailHtml(string $name, string $url): string
    {
        $safeName = e($name);
        $safeUrl = e($url);

        return <<<HTML
            <div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:460px;margin:0 auto;color:#0f172a;">
                <div style="background:linear-gradient(120deg,#2563EB,#12B76A);color:#fff;padding:22px;border-radius:16px 16px 0 0;text-align:center;">
                    <div style="font-size:22px;font-weight:800;letter-spacing:.02em;">Haraan</div>
                </div>
                <div style="border:1px solid #E9EDF3;border-top:0;border-radius:0 0 16px 16px;padding:26px 24px;">
                    <p style="margin:0 0 8px;font-size:15px;">Hi {$safeName},</p>
                    <p style="margin:0 0 18px;font-size:14px;color:#475569;line-height:1.6;">Tap the button below to set a new password. This link expires in 60 minutes.</p>
                    <a href="{$safeUrl}" style="display:inline-block;background:#2563EB;color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 22px;border-radius:12px;">Set new password</a>
                    <p style="margin:20px 0 0;font-size:12px;color:#94a3b8;line-height:1.6;">If you didn't request this, you can safely ignore this email — your password won't change.</p>
                </div>
            </div>
            HTML;
    }
}
