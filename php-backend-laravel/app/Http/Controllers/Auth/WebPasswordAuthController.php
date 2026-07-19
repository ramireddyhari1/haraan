<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Email + password sign-in for the public website login modal (replaces the old
 * phone/WhatsApp-OTP field). Behaviour mirrors the existing "we'll create one
 * for you" copy: an unknown email signs up on the spot; a known email verifies
 * its password. Session auth is the same standard web guard the OTP and Google
 * flows use (Auth::login), so the rest of the site sees the user as logged in
 * exactly as before.
 *
 * NB: accounts created via OTP/Google hold a *random* password, so their owners
 * can't sign in here with a password — the error nudges them to Google. A proper
 * "set / reset password" email flow for those users is the natural follow-up.
 */
class WebPasswordAuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'name' => ['nullable', 'string', 'max:60'],
            // Optional demographics (sign-up only) — feed the per-event audience analytics.
            'age' => ['nullable', 'integer', 'min:5', 'max:120'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
        ], [
            'password.min' => 'Your password must be at least 6 characters.',
        ]);

        $email = Str::lower(trim($data['email']));

        // Match case-insensitively; emails are stored as entered.
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user) {
            // Existing account — the password must check out.
            if (! $user->password || ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => 'Incorrect email or password. If you signed up with Google, use "Continue with Google" above.',
                ]);
            }
        } else {
            // Unknown email — create the account, same as the OTP flow auto-creates.
            // Use the typed name when the "Create new account" form supplied one,
            // otherwise fall back to the email's local part.
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $name = Str::of($email)->before('@')->ucfirst()->value() ?: 'Member';
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($data['password']),
                'role' => 'user',
                'status' => 'active',
                'age' => $data['age'] ?? null,
                'gender' => $data['gender'] ?? null,
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        // Straight to where they were headed — never via cricket onboarding, for the
        // same reason as the OTP/Google flows (most web sign-ins are Events traffic).
        return redirect()->intended('/')->with('success', 'Logged in successfully.');
    }
}
