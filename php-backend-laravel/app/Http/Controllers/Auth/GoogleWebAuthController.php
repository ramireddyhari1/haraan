<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleAuthException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * "Continue with Google" for the website.
 *
 * The login modal renders Google Identity Services, which hands the browser an ID
 * token; the modal posts it here. We verify it with {@see GoogleIdTokenVerifier} —
 * the same checks the app's JWT flow uses — then start a normal Laravel session.
 *
 * Where the app's twin ({@see \App\Http\Controllers\Api\GoogleAuthController}) returns
 * a JWT, this returns a redirect target: profile setup for accounts still missing a
 * district/state, otherwise wherever the guest was originally headed. That mirrors
 * {@see WhatsAppAuthController::verifyOtp()} so both web logins land the same way.
 *
 * NB: GIS only issues tokens to origins registered on the OAuth client, and Google
 * requires https for non-localhost origins — so this works on https://haraan.app and
 * not on the bare-IP http:// host.
 */
final class GoogleWebAuthController extends Controller
{
    public function __construct(private readonly GoogleIdTokenVerifier $verifier)
    {
    }

    /** POST /auth/google  { credential: string } — the GIS callback's token. */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        try {
            $claims = $this->verifier->verify($validated['credential']);
        } catch (GoogleAuthException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        }

        $email = (string) $claims['email'];

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

        // Backfill only what's missing — never overwrite what the user already set.
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

        Auth::login($user, true);
        $request->session()->regenerate();

        // Partners (event hosts / venue owners) go straight to their /partner console.
        // Otherwise: straight to where they were headed — never via cricket onboarding.
        // Most people signing in here came for Events, and district/state are an
        // ActionBoard concern: EnsureActionboardProfile collects them at the point of use.
        $redirect = $user->hasRoleEither(['PARTNER'])
            ? '/partner'
            : $request->session()->pull('url.intended', '/');

        return response()->json(['redirect' => $redirect]);
    }
}
