<?php

declare(strict_types=1);

namespace App\Support;

final class JwtService
{
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
