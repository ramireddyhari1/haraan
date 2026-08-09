<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\JwtService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureJwtAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $token = trim($matches[1]);
        $secret = (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me'));
        $payload = JwtService::decode($token, $secret);

        if ($payload === null || !isset($payload['sub'])) {
            return new JsonResponse(['error' => 'Invalid or expired token'], 401);
        }

        $user = User::query()->find($payload['sub']);
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // A signature that verifies only proves the token was minted by us — it says
        // nothing about whether the session behind it is still wanted. Tokens are
        // stateless with a 7-day TTL, so without this check a "signed out" token keeps
        // working for a week. Same 401 shape as an expired token: clients already
        // handle that by sending the user back to sign in.
        if (!JwtService::versionMatches($payload, $user)) {
            return new JsonResponse(['error' => 'Invalid or expired token'], 401);
        }

        // Bridge JWT auth user with standard Laravel auth guard context
        Auth::setUser($user);
        $request->attributes->set('auth_user', $user);

        // Activity heartbeat for /control (throttled internally to ~5 min).
        $user->touchLastSeen();

        return $next($request);
    }
}

