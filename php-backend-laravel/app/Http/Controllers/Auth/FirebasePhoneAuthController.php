<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebasePhoneVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Phone-number sign-in for the website (Firebase SMS OTP).
 *
 * The browser runs the Firebase phone-auth flow (send code → confirm), which hands
 * it a Firebase ID token; the login posts that here. We confirm it with
 * {@see FirebasePhoneVerifier} to get the verified E.164 number, then find-or-create
 * the member and open a normal Laravel session — the same shape as
 * {@see GoogleWebAuthController}, so the rest of the site sees a standard login.
 *
 * An unknown number signs up on the spot (matching the "we'll create one for you"
 * copy); a known number just logs in.
 */
final class FirebasePhoneAuthController extends Controller
{
    public function __construct(private readonly FirebasePhoneVerifier $verifier)
    {
    }

    /** POST /auth/firebase-phone  { id_token: string, name?: string } */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'name'     => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $phone = $this->verifier->phoneFromIdToken($data['id_token']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name'     => trim((string) ($data['name'] ?? '')) ?: 'Member',
                'password' => bcrypt(Str::random(32)),
                'role'     => 'user',
                'status'   => 'active',
            ],
        );

        // Partners sign in on the console's own page, not the public site.
        if ($user->hasRoleEither(['PARTNER'])) {
            return response()->json([
                'redirect' => route('filament.partner.auth.login'),
                'message'  => 'Partner accounts sign in at /partner/login.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        // Straight to where they were headed — district/state are collected later at
        // the point of use, same as the Google/email flows.
        $redirect = $request->session()->pull('url.intended', '/');

        return response()->json(['redirect' => $redirect]);
    }
}
