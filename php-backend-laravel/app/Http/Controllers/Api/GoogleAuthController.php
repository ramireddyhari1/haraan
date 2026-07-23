<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\GoogleAuthException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use App\Support\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Continue with Google" sign-in. The app obtains a Google ID token via the
 * Credential Manager / Sign in with Google flow and posts it here; we verify it
 * with Google, then log the user in (creating the account on first sign-in) and
 * issue the same app JWT the email-OTP flow returns.
 *
 * Token verification lives in {@see GoogleIdTokenVerifier}, shared with the website's
 * session-based sign-in so both surfaces enforce the same checks.
 */
final class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleIdTokenVerifier $verifier)
    {
    }

    /** POST /api/auth/google  { id_token: string } */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $claims = $this->verifier->verify($validated['id_token']);
        } catch (GoogleAuthException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $email = (string) $claims['email'];
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
