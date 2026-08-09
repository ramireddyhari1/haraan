<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class JwtService
{
    /**
     * The ONLY way a login path should mint a session token.
     *
     * Every token must carry `tv` (the user's token_version) or the auth middleware
     * cannot tell a live session from a revoked one. Six separate login controllers
     * built their payloads by hand; a seventh that forgot the claim would silently
     * mint tokens that survive every logout. Issuing through here makes that
     * impossible to get wrong.
     */
    public static function issueForUser(User $user, string $secret, int $ttlSeconds = 604800): string
    {
        return self::issue([
            'sub' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'tv' => (int) ($user->token_version ?? 0),
        ], $secret, $ttlSeconds);
    }

    /**
     * Invalidate every token this user holds, on every device, by moving the version
     * their live tokens were stamped with. Returns the new version.
     */
    public static function revokeAllFor(User $user): int
    {
        $next = (int) ($user->token_version ?? 0) + 1;
        $user->forceFill(['token_version' => $next])->save();

        return $next;
    }

    /**
     * True when this decoded payload still matches the user's current version.
     *
     * Tokens minted before this column existed carry no `tv`. They are accepted while
     * the user is still at version 0 — otherwise deploying this would sign out every
     * existing session at once — and stop being accepted the moment that user logs out
     * of anything, which is exactly when we want them gone.
     */
    public static function versionMatches(array $payload, User $user): bool
    {
        $current = (int) ($user->token_version ?? 0);
        $claimed = array_key_exists('tv', $payload) ? (int) $payload['tv'] : 0;

        return $claimed === $current;
    }

    public static function issue(array $payload, string $secret, int $ttlSeconds = 604800): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();

        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        $headerEncoded = self::base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signatureEncoded;
    }

    public static function decode(string $jwt, string $secret): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        $signature = self::base64UrlDecode($signatureEncoded);
        if ($signature === null) {
            return null;
        }

        $expected = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $secret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadEncoded);
        if ($payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && is_numeric($payload['exp']) && time() >= (int) $payload['exp']) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $input): ?string
    {
        $remainder = strlen($input) % 4;
        if ($remainder > 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
