<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The small slice of LiveKit live commentary needs: access tokens and three server calls.
 *
 * A LiveKit access token is an HS256 JWT signed with the API secret, `iss` = API key, `sub` =
 * participant identity, and a `video` grant saying which room and what the holder may do. The
 * commentator's token may publish; a listener's may only subscribe — so a listener's phone
 * can never put audio into the room, whatever the app is changed to do.
 *
 * Server calls are LiveKit's Twirp API (POST JSON to /twirp/livekit.RoomService/<Method>). They
 * never throw: a LiveKit outage must not break scoring, the match page or /control. Failures
 * are logged and reported as null/false.
 */
final class LiveKit
{
    public static function configured(): bool
    {
        return filled(config('services.livekit.url'))
            && filled(config('services.livekit.key'))
            && filled(config('services.livekit.secret'));
    }

    /** The wss:// URL phones connect to. */
    public static function url(): string
    {
        return (string) config('services.livekit.url');
    }

    /**
     * @param  array<string, mixed>  $video  the LiveKit video grant
     */
    public static function token(string $identity, string $name, array $video, int $ttlSeconds): string
    {
        $now = time();
        $claims = [
            'iss' => (string) config('services.livekit.key'),
            'sub' => $identity,
            'name' => $name,
            'nbf' => $now - 5,
            'iat' => $now,
            'exp' => $now + max(60, $ttlSeconds),
            'jti' => bin2hex(random_bytes(8)),
            'video' => $video,
        ];

        return self::jwt($claims);
    }

    /** Publish-only (audio) token for the commentator. */
    public static function publisherToken(string $room, string $identity, string $name, int $ttlSeconds): string
    {
        return self::token($identity, $name, [
            'room' => $room,
            'roomJoin' => true,
            'canPublish' => true,
            'canPublishSources' => ['microphone'],
            'canSubscribe' => false,
            'canPublishData' => false,
        ], $ttlSeconds);
    }

    /** Listen-only token for anyone on the match detail screen. */
    public static function listenerToken(string $room, string $identity, int $ttlSeconds): string
    {
        return self::token($identity, 'Listener', [
            'room' => $room,
            'roomJoin' => true,
            'canPublish' => false,
            'canSubscribe' => true,
            'canPublishData' => false,
            // Listeners don't need to see each other; the server API still counts them.
            'hidden' => true,
        ], $ttlSeconds);
    }

    /** Create the room up front so its participant cap is set before anyone joins. */
    public static function createRoom(string $room, int $maxParticipants, int $emptyTimeoutSeconds = 120): bool
    {
        return self::call('CreateRoom', [
            'name' => $room,
            'max_participants' => $maxParticipants,
            'empty_timeout' => $emptyTimeoutSeconds,
        ], ['roomCreate' => true]) !== null;
    }

    /** Close the room: every listener and the commentator are disconnected at once. */
    public static function deleteRoom(string $room): bool
    {
        return self::call('DeleteRoom', ['room' => $room], ['roomCreate' => true]) !== null;
    }

    /** People in the room, the commentator included. Null when LiveKit can't be asked. */
    public static function participantCount(string $room): ?int
    {
        $res = self::call('ListParticipants', ['room' => $room], ['roomAdmin' => true, 'room' => $room]);

        return $res === null ? null : count((array) ($res['participants'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $grant
     * @return array<string, mixed>|null
     */
    private static function call(string $method, array $body, array $grant): ?array
    {
        if (! self::configured()) {
            return null;
        }

        try {
            $token = self::token('haraan-server', 'Haraan', $grant, 60);
            $response = Http::timeout(4)
                ->withToken($token)
                ->acceptJson()
                ->post(self::apiUrl().'/twirp/livekit.RoomService/'.$method, (object) $body);

            if (! $response->successful()) {
                Log::warning('livekit.call_failed', ['method' => $method, 'status' => $response->status()]);

                return null;
            }

            return (array) ($response->json() ?? []);
        } catch (Throwable $e) {
            Log::warning('livekit.call_error', ['method' => $method, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private static function apiUrl(): string
    {
        $api = (string) config('services.livekit.api_url');
        if ($api === '') {
            $api = preg_replace('#^ws(s?)://#', 'http$1://', self::url()) ?? '';
        }

        return rtrim($api, '/');
    }

    /** @param  array<string, mixed>  $claims */
    private static function jwt(array $claims): string
    {
        $b64 = static fn (string $raw): string => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        $head = $b64((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $b64((string) json_encode($claims, JSON_UNESCAPED_SLASHES));
        $sig = $b64(hash_hmac('sha256', $head.'.'.$body, (string) config('services.livekit.secret'), true));

        return $head.'.'.$body.'.'.$sig;
    }
}
