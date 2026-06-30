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

        // Bridge JWT auth user with standard Laravel auth guard context
        Auth::setUser($user);
        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}

