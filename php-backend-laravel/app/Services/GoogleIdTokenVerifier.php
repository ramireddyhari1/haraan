<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GoogleAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Verifies a Google ID token and returns its claims.
 *
 * Shared by the app's JWT flow ({@see \App\Http\Controllers\Api\GoogleAuthController})
 * and the website's session flow ({@see \App\Http\Controllers\Auth\GoogleWebAuthController})
 * so both enforce identical checks — a token accepted by one is accepted by the other.
 *
 * Verification uses Google's tokeninfo endpoint so we don't have to bundle a JWT
 * crypto/JWKS library — Google validates the signature and expiry; we then check
 * the audience is *our* OAuth client and that the email is verified.
 */
final class GoogleIdTokenVerifier
{
    /**
     * @return array<string, mixed> the verified token claims
     *
     * @throws GoogleAuthException when the token is unusable, carrying the HTTP status to return
     */
    public function verify(string $idToken): array
    {
        $expectedAud = (string) config('services.google.client_id');
        if ($expectedAud === '') {
            throw new GoogleAuthException('Google sign-in is not configured.', 503);
        }

        try {
            $resp = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);
        } catch (ConnectionException) {
            throw new GoogleAuthException("Couldn't reach Google. Please try again.", 502);
        }

        if (! $resp->ok()) {
            throw new GoogleAuthException('That Google sign-in could not be verified.', 401);
        }

        $claims = $resp->json();

        // The token must have been minted for OUR OAuth client, or it's a substitution attack.
        if (! is_array($claims) || ($claims['aud'] ?? null) !== $expectedAud) {
            throw new GoogleAuthException('This sign-in was not issued for Haraan.', 401);
        }

        // Google issues these; be strict about a verified email since we key accounts on it.
        $iss = (string) ($claims['iss'] ?? '');
        $emailVerified = ($claims['email_verified'] ?? 'false') === 'true' || ($claims['email_verified'] ?? false) === true;
        $email = mb_strtolower(trim((string) ($claims['email'] ?? '')));

        if (! in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true) || $email === '' || ! $emailVerified) {
            throw new GoogleAuthException('Your Google account email could not be verified.', 401);
        }

        $claims['email'] = $email;

        return $claims;
    }
}
