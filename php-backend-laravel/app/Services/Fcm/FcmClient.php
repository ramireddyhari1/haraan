<?php

declare(strict_types=1);

namespace App\Services\Fcm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal Firebase Cloud Messaging HTTP v1 client — no Firebase SDK.
 *
 * It mints a short-lived Google OAuth2 access token from the service-account JSON
 * (a JWT signed RS256 with the account's private key, exchanged at the token
 * endpoint) and posts one data-only message per device token to the v1 send API.
 *
 * Data-only (no `notification` block) is deliberate: it guarantees every push is
 * delivered to the app's HaraanMessagingService.onMessageReceived, which builds the
 * notification and owns the tap intent — so deep links route consistently whether
 * the app is foreground or background.
 */
final class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_CACHE_KEY = 'fcm.access_token';

    /** Outcome of a single send, so the caller can prune dead tokens. */
    public const OK = 'ok';
    public const INVALID = 'invalid'; // token is dead/unregistered — delete it
    public const ERROR = 'error';     // transient/other failure — keep the token

    /** True only when a service-account file is configured and present. */
    public function isConfigured(): bool
    {
        $path = $this->credentialsPath();

        return $path !== null && is_file($path);
    }

    /**
     * Send one data-only message to a single device token.
     *
     * @param array<string,string> $data extra key/value pairs (e.g. deep_link)
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): string
    {
        if (! $this->isConfigured()) {
            return self::ERROR;
        }

        try {
            $sa = $this->serviceAccount();
            $accessToken = $this->accessToken($sa);

            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    // Strings only — FCM v1 rejects non-string data values.
                    'data' => array_map(
                        static fn ($v): string => (string) $v,
                        array_merge(['title' => $title, 'body' => $body], $data),
                    ),
                    'android' => ['priority' => 'HIGH'],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send",
                    $payload,
                );

            if ($response->successful()) {
                return self::OK;
            }

            // 404 UNREGISTERED / 400 INVALID_ARGUMENT on the token => it's dead.
            $status = (string) $response->json('error.status', '');
            if (in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                return self::INVALID;
            }

            Log::warning('FCM send failed', [
                'code' => $response->status(),
                'status' => $status,
            ]);

            return self::ERROR;
        } catch (\Throwable $e) {
            Log::warning('FCM send threw', ['error' => $e->getMessage()]);

            return self::ERROR;
        }
    }

    /**
     * A valid OAuth2 access token for the messaging scope, cached just under its
     * one-hour lifetime so a burst of sends reuses one token.
     *
     * @param array<string,mixed> $sa
     */
    private function accessToken(array $sa): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () use ($sa): string {
            $jwt = $this->buildAssertion($sa);

            $response = Http::asForm()->timeout(15)->post($sa['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new RuntimeException('FCM: could not obtain access token ('.$response->status().')');
            }

            return $token;
        });
    }

    /**
     * A signed JWT bearer assertion for the token exchange.
     *
     * @param array<string,mixed> $sa
     */
    private function buildAssertion(array $sa): string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud' => $sa['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $signingInput = $this->b64url((string) json_encode($header))
            .'.'.$this->b64url((string) json_encode($claims));

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('FCM: failed to sign the JWT assertion.');
        }

        return $signingInput.'.'.$this->b64url($signature);
    }

    /** @return array{client_email:string,private_key:string,project_id:string,token_uri:string} */
    private function serviceAccount(): array
    {
        $raw = file_get_contents($this->credentialsPath());
        $sa = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['project_id'])) {
            throw new RuntimeException('FCM: service-account JSON is missing required fields.');
        }

        // token_uri is present in real service-account files; default it defensively.
        $sa['token_uri'] ??= 'https://oauth2.googleapis.com/token';

        return $sa;
    }

    private function credentialsPath(): ?string
    {
        $path = config('services.fcm.credentials');

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
