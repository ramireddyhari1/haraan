<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * "Continue with Google" sign-in. The app obtains a Google ID token via the
 * Credential Manager / Sign in with Google flow and posts it here; we verify it
 * with Google, then log the user in (creating the account on first sign-in) and
 * issue the same app JWT the email-OTP flow returns.
 *
 * Verification uses Google's tokeninfo endpoint so we don't have to bundle a JWT
 * crypto/JWKS library — Google validates the signature and expiry; we then check
 * the audience is *our* OAuth client and that the email is verified.
 */
final class GoogleAuthController extends Controller
{
    /** POST /api/auth/google  { id_token: string } */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $expectedAud = (string) config('services.google.client_id');
        if ($expectedAud === '') {
            return response()->json(['error' => 'Google sign-in is not configured.'], 503);
        }

        try {
            $resp = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $validated['id_token'],
            ]);
        } catch (ConnectionException) {
            return response()->json(['error' => "Couldn't reach Google. Please try again."], 502);
        }

        if (! $resp->ok()) {
            return response()->json(['error' => 'That Google sign-in could not be verified.'], 401);
        }

        $claims = $resp->json();

        // The token must have been minted for OUR OAuth client, or it's a substitution attack.
        if (! is_array($claims) || ($claims['aud'] ?? null) !== $expectedAud) {
            return response()->json(['error' => 'This sign-in was not issued for Haraan.'], 401);
        }

        // Google issues these; be strict about a verified email since we key accounts on it.
        $iss = (string) ($claims['iss'] ?? '');
        $emailVerified = ($claims['email_verified'] ?? 'false') === 'true' || ($claims['email_verified'] ?? false) === true;
        $email = mb_strtolower(trim((string) ($claims['email'] ?? '')));

        if (! in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true) || $email === '' || ! $emailVerified) {
            return response()->json(['error' => 'Your Google account email could not be verified.'], 401);
        }

        $isNew = ! User::query()->where('email', $email)->exists();

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) ($claims['name'] ?? '')) ?: Str::before($email, '@'),
                'avatar' => (string) ($claims['picture'] ?? '') ?: null,
                'password' => bcrypt(Str::random(32)),
                'role' => 'user',
                'status' => 'active',
            ],
        );

        // Backfill a name/avatar for a pre-existing account that never had one (e.g. created
        // via email OTP before setting a name). Never overwrite what the user already has.
        $fill = [];
        if (blank($user->name) && ! blank($claims['name'] ?? null)) {
            $fill['name'] = (string) $claims['name'];
        }
        if (blank($user->avatar) && ! blank($claims['picture'] ?? null)) {
            $fill['avatar'] = (string) $claims['picture'];
        }
        if ($fill !== []) {
            $user->fill($fill)->save();
        }

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
