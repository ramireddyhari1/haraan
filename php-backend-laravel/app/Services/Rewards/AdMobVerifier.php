<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies Google AdMob rewarded-ad server-side verification (SSV) callbacks.
 *
 * Google signs every callback with ECDSA (SHA-256) using a private key only Google holds; we
 * check it against Google's PUBLISHED public keys. There is no shared secret to store, leak or
 * expose in /control. The signed message is the raw query string up to (not including)
 * "&signature=" — it must be the exact bytes Google sent, so the raw QUERY_STRING is used, never
 * a re-built/sorted one.
 */
final class AdMobVerifier
{
    private const CACHE_KEY = 'rewards.admob.verifier_keys';

    /**
     * @return array<string, string> the callback's parameters, once the signature is valid
     *
     * @throws InvalidAdSignature
     */
    public function verify(string $rawQuery): array
    {
        $cut = strpos($rawQuery, '&signature=');
        if ($cut === false || $cut === 0) {
            throw new InvalidAdSignature('missing signature');
        }
        $message = substr($rawQuery, 0, $cut);

        parse_str($rawQuery, $params);
        $params = array_map(fn ($v) => is_string($v) ? $v : '', $params);

        $signature = self::base64UrlDecode((string) ($params['signature'] ?? ''));
        $keyId = (string) ($params['key_id'] ?? '');
        if ($signature === null || $signature === '' || $keyId === '' || ! ctype_digit($keyId)) {
            throw new InvalidAdSignature('malformed signature or key id');
        }

        $pem = $this->publicKey($keyId);
        if ($pem === null) {
            throw new InvalidAdSignature('unknown key id');
        }

        $key = openssl_pkey_get_public($pem);
        if ($key === false || openssl_verify($message, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new InvalidAdSignature('signature does not verify');
        }

        return $params;
    }

    /** The PEM for a key id; refetches once when an unknown id arrives (Google rotates keys). */
    private function publicKey(string $keyId): ?string
    {
        $keys = Cache::get(self::CACHE_KEY);
        // An unknown id triggers at most one fetch per 5 minutes, so forged callbacks with
        // random key ids can't make us hammer Google.
        if ((! is_array($keys) || ! isset($keys[$keyId])) && Cache::add(self::CACHE_KEY.'.fetching', 1, now()->addMinutes(5))) {
            $keys = $this->fetchKeys();
            if ($keys !== []) {
                Cache::put(self::CACHE_KEY, $keys, now()->addMinutes((int) config('rewards.admob.keys_cache_minutes', 1440)));
            }
        }

        return $keys[$keyId] ?? null;
    }

    /** @return array<string, string> key id => PEM */
    private function fetchKeys(): array
    {
        try {
            $response = Http::timeout(5)->acceptJson()->get((string) config('rewards.admob.verifier_keys_url'));
            if (! $response->successful()) {
                return [];
            }
            $out = [];
            foreach ((array) $response->json('keys', []) as $k) {
                if (is_array($k) && isset($k['keyId'], $k['pem']) && is_string($k['pem'])) {
                    $out[(string) $k['keyId']] = $k['pem'];
                }
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('AdMob verifier keys fetch failed: '.$e->getMessage());

            return [];
        }
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $b64 = strtr($value, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($b64, true);

        return $out === false ? null : $out;
    }
}
