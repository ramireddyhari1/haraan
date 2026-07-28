<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Verifies a Firebase phone-auth ID token minted in the browser / app (Firebase SDK)
 * and returns the confirmed phone number.
 *
 * Fast path: the ID token is a Google-signed JWT, so we verify its RS256 signature
 * LOCALLY against Google's public certs (cached), plus the standard claims — no network
 * round-trip on the hot path. Only when local verification can't *run* (project id unset
 * or certs unreachable) do we fall back to Google's Identity Toolkit `accounts:lookup`
 * REST endpoint. A token that is present but cryptographically/claims invalid is REJECTED,
 * never passed through by the fallback — the browser/app can't forge it either way.
 */
final class FirebasePhoneVerifier
{
    /** Google's public x509 certs for Firebase ID tokens (securetoken). */
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /** Small clock-skew leeway for exp/iat checks (seconds). */
    private const LEEWAY = 60;

    /** RuntimeException code: token is invalid — reject, do NOT fall back. */
    private const CODE_INVALID = 422;

    /** RuntimeException code: local verification couldn't run — fall back to the lookup. */
    private const CODE_UNAVAILABLE = 503;

    /** True only when a web API key is configured. */
    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @return string the E.164 phone number the token was issued for
     *
     * @throws RuntimeException when the token is missing, rejected, or has no phone
     */
    public function phoneFromIdToken(string $idToken): string
    {
        try {
            return $this->phoneFromLocalVerification($idToken);
        } catch (RuntimeException $e) {
            // Local verification couldn't RUN (no project id / certs unreachable) — fall
            // back to the network lookup so sign-in still works.
            if ($e->getCode() === self::CODE_UNAVAILABLE) {
                Log::warning('Firebase local verify unavailable, using lookup: '.$e->getMessage());

                return $this->phoneFromLookup($idToken);
            }
            // Local verification RAN and rejected the token — do not fall back.
            if ($e->getCode() === self::CODE_INVALID) {
                throw new RuntimeException('We could not verify that code. Please try again.');
            }
            // A definitive, already user-facing result (e.g. a valid token with no phone).
            throw $e;
        }
    }

    /** Verify the ID token locally and return its phone_number claim. */
    private function phoneFromLocalVerification(string $idToken): string
    {
        $projectId = (string) config('services.firebase.project_id');
        if ($projectId === '') {
            throw new RuntimeException('project id not configured', self::CODE_UNAVAILABLE);
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new RuntimeException('malformed token', self::CODE_INVALID);
        }
        [$h, $p, $s] = $parts;

        $header  = json_decode($this->b64urlDecode($h), true);
        $payload = json_decode($this->b64urlDecode($p), true);
        $sig     = $this->b64urlDecode($s);

        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            throw new RuntimeException('bad token header', self::CODE_INVALID);
        }

        // Signature: RS256 over "header.payload" with the cert matching the token's kid.
        // A kid miss can mean Google rotated keys — refetch once past the cache before rejecting.
        $cert = $this->certFor((string) $header['kid'], false)
            ?? $this->certFor((string) $header['kid'], true);
        if ($cert === null) {
            throw new RuntimeException('no matching signing key', self::CODE_UNAVAILABLE);
        }

        $pubKey = openssl_pkey_get_public($cert);
        if ($pubKey === false || openssl_verify($h.'.'.$p, $sig, $pubKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('signature mismatch', self::CODE_INVALID);
        }

        // Standard Firebase ID-token claims.
        $now = time();
        if ((int) ($payload['exp'] ?? 0) < $now - self::LEEWAY) {
            throw new RuntimeException('token expired', self::CODE_INVALID);
        }
        if ((int) ($payload['iat'] ?? 0) > $now + self::LEEWAY) {
            throw new RuntimeException('token not yet valid', self::CODE_INVALID);
        }
        if (($payload['aud'] ?? null) !== $projectId) {
            throw new RuntimeException('wrong audience', self::CODE_INVALID);
        }
        if (($payload['iss'] ?? null) !== 'https://securetoken.google.com/'.$projectId) {
            throw new RuntimeException('wrong issuer', self::CODE_INVALID);
        }
        if (trim((string) ($payload['sub'] ?? '')) === '') {
            throw new RuntimeException('empty subject', self::CODE_INVALID);
        }

        $phone = $payload['phone_number'] ?? null;
        if (! is_string($phone) || $phone === '') {
            // Validly signed but not a phone sign-in — a real user error, not a fallback case.
            throw new RuntimeException('That sign-in did not include a phone number.');
        }

        return $phone;
    }

    /**
     * The PEM cert for a key id from Google's cert set, or null if absent.
     * Cached ~6h; {@code $fresh} busts the cache to pick up a key rotation.
     */
    private function certFor(string $kid, bool $fresh): ?string
    {
        $certs = $this->googleCerts($fresh);

        return isset($certs[$kid]) && is_string($certs[$kid]) ? $certs[$kid] : null;
    }

    /**
     * @return array<string, string> kid => PEM cert
     */
    private function googleCerts(bool $fresh): array
    {
        if ($fresh) {
            Cache::forget('firebase_securetoken_certs');
        }

        return Cache::remember('firebase_securetoken_certs', now()->addHours(6), function (): array {
            $res = Http::timeout(10)->get(self::CERTS_URL);
            if (! $res->successful() || ! is_array($res->json())) {
                throw new RuntimeException('certs unreachable', self::CODE_UNAVAILABLE);
            }

            return $res->json();
        });
    }

    /** URL-safe base64 decode. */
    private function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /**
     * Fallback: post the token to Google's Identity Toolkit `accounts:lookup`. That endpoint
     * only returns a user for a token it accepts, so a response with a phoneNumber is proof
     * the OTP was completed for that number.
     */
    private function phoneFromLookup(string $idToken): string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('Phone sign-in is not configured.');
        }

        $response = Http::timeout(15)->post(
            'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.$apiKey,
            ['idToken' => $idToken],
        );

        if (! $response->successful()) {
            throw new RuntimeException('We could not verify that code. Please try again.');
        }

        $user = $response->json('users.0');
        $phone = is_array($user) ? ($user['phoneNumber'] ?? null) : null;

        if (! is_string($phone) || $phone === '') {
            throw new RuntimeException('That sign-in did not include a phone number.');
        }

        return $phone;
    }

    private function apiKey(): ?string
    {
        $key = config('services.firebase.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
