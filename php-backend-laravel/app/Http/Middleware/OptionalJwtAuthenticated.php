<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
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
                if ($user !== null) {
                    Auth::setUser($user);
                    $request->attributes->set('auth_user', $user);
                    $user->touchLastSeen();
                }
            }
        }

        return $next($request);
    }
}

