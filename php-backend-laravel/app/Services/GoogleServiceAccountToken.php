<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * An OAuth2 access token for a Google service account, minted from its JSON key.
 *
 * Hand-rolled rather than pulled in with google/auth, for the same reason
 * App\Support\JwtService is hand-rolled: it is forty lines of well-specified protocol,
 * PHP ships the crypto, and prod installs dependencies with `composer install --no-dev`
 * on the box — so every added package is another thing that has to survive a deploy.
 *
 * The flow is the standard JWT bearer grant:
 *   1. Build a JWT asserting "I am this service account and I want this scope".
 *   2. Sign it RS256 with the service account's private key.
 *   3. Exchange it at Google's token endpoint for an access token.
 *
 * Tokens last an hour; this caches for slightly less, so a request never picks up a
 * token that expires mid-flight.
 */
final class GoogleServiceAccountToken
{
    /** Google issues for 3600s. Refresh at 55 minutes so nothing is ever used cold. */
    private const CACHE_SECONDS = 3300;

    /**
     * @param string $keyPath absolute path to the service account JSON
     * @param string $scope   e.g. https://www.googleapis.com/auth/cloud-platform
     *
     * @return string|null the access token, or null if it could not be obtained
     */
    public function get(string $keyPath, string $scope): ?string
    {
        if ($keyPath === '' || ! is_readable($keyPath)) {
            return null;
        }

        // Keyed on the file AND its mtime: rotating the credential must invalidate the
        // cached token immediately, not an hour later.
        $cacheKey = 'gsa_token:' . md5($keyPath . '|' . $scope . '|' . (string) @filemtime($keyPath));

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->mint($keyPath, $scope);
        if ($token !== null) {
            Cache::put($cacheKey, $token, self::CACHE_SECONDS);
        }

        return $token;
    }

    private function mint(string $keyPath, string $scope): ?string
    {
        try {
            $json = json_decode((string) file_get_contents($keyPath), true);
            if (! is_array($json) || empty($json['private_key']) || empty($json['client_email'])) {
                return null;
            }

            $now = time();
            $claims = [
                'iss' => $json['client_email'],
                'scope' => $scope,
                'aud' => $json['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                // 3600 is the maximum Google accepts for this assertion.
                'exp' => $now + 3600,
            ];

            $unsigned = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES))
                . '.' . $this->b64(json_encode($claims, JSON_UNESCAPED_SLASHES));

            $signature = '';
            if (! openssl_sign($unsigned, $signature, $json['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $assertion = $unsigned . '.' . $this->b64($signature);

            $response = Http::asForm()->timeout(10)->post($claims['aud'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $token = (string) data_get($response->json(), 'access_token', '');

            return $token !== '' ? $token : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** base64url, the JWT alphabet: no padding, - and _ instead of + and /. */
    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
