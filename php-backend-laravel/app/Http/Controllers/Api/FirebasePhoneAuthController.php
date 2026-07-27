<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FirebasePhoneVerifier;
use App\Support\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Continue with phone" sign-in for the Android app (Firebase SMS OTP).
 *
 * The app runs the Firebase phone-auth flow (send code → confirm) and obtains a
 * Firebase ID token, which it posts here. We confirm it with
 * {@see FirebasePhoneVerifier} to get the verified E.164 number, find-or-create the
 * member, and issue the same app JWT the Google/email flows return.
 *
 * This is the token-based twin of {@see \App\Http\Controllers\Auth\FirebasePhoneAuthController}
 * (the website's session-based version): same verifier, same find-or-create rule, so
 * both surfaces resolve to the exact same account for a given number.
 *
 * An unknown number signs up on the spot; a known number just logs in.
 */
final class FirebasePhoneAuthController extends Controller
{
    public function __construct(private readonly FirebasePhoneVerifier $verifier)
    {
    }

    /** POST /api/auth/firebase-phone  { id_token: string, name?: string } */
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

        $isNew = ! User::query()->where('phone', $phone)->exists();

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => trim((string) ($data['name'] ?? '')) ?: 'Member',
                // users.email is NOT NULL; phone sign-ups have no address, so synthesize a
                // unique placeholder from the (unique) E.164 number — same convention the
                // website's phone controller uses, so both surfaces land on one account.
                'email'    => $phone.'@phone.haraan.local',
                'password' => bcrypt(Str::random(32)),
                'role'     => 'user',
                'status'   => 'active',
            ],
        );

        return response()->json([
            'message' => $isNew ? 'Welcome to Haraan!' : 'Welcome back!',
            'newUser' => $isNew,
            'token' => JwtService::issue([
                'sub' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ], (string) config('app.jwt_secret')),
            'user' => new UserResource($user),
        ]);
    }
}
