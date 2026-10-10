<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\MemberDevice;
use App\Models\User;
use App\Services\Membership\MemberDevices;
use App\Support\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the JWT user when a valid token is present, but — unlike auth.jwt —
 * never rejects the request when it is missing or invalid. Used by feeds that
 * serve guests (public/FEATURED content) while tailoring results for signed-in
 * users (their own district). Downstream code reads `auth_user`, which may be null.
 */
final class OptionalJwtAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            $token = trim($matches[1]);
            $secret = (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me'));
            $payload = JwtService::decode($token, $secret);

            if ($payload !== null && isset($payload['sub'])) {
                $user = User::query()->find($payload['sub']);
                // Revoked tokens must fall through to "guest" here rather than being
                // ignored: this middleware is what decides whether a viewer is the OWNER
                // of what they are looking at (posts `mine`, `social.is_following`), so a
                // revoked session that still resolved to a user would keep owner
                // affordances on a public page.
                // Same reasoning for a suspended account: it falls through to "guest"
                // rather than being resolved. This middleware never rejects, so a
                // suspended viewer still sees public pages — but as a stranger, without
                // the owner affordances a resolved user would carry.
                // A signed-out device, or one held at the device chooser, browses as a guest too.
                if ($user !== null && JwtService::versionMatches($payload, $user) && $user->isAccountActive()
                    && self::deviceAllowed($payload, $user, $request)) {
                    Auth::setUser($user);
                    $request->attributes->set('auth_user', $user);
                    $user->touchLastSeen();
                }
            }
        }

        return $next($request);
    }

    /** @param array<string, mixed> $payload */
    private static function deviceAllowed(array $payload, User $user, Request $request): bool
    {
        if (! isset($payload['sid'])) {
            return true;
        }

        $device = MemberDevice::query()
            ->where('public_id', (string) $payload['sid'])
            ->where('user_id', $user->id)
            ->first();
        if ($device === null || $device->isRevoked()) {
            return false;
        }

        $device->setRelation('user', $user);
        $devices = app(MemberDevices::class);
        if ($devices->evaluate($device) === MemberDevices::DECISION_LIMIT) {
            return false;
        }

        $request->attributes->set('member_device', $device);
        $devices->touch($device, $request->ip());

        return true;
    }
}

