<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the partner API to PARTNER users (super-admins pass too, for support).
 * Runs after auth.jwt, so the user is already resolved onto the request.
 */
final class EnsurePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user !== null
            && (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()
                || method_exists($user, 'hasRoleEither') && $user->hasRoleEither(['PARTNER']));

        if (! $allowed) {
            return new JsonResponse(['error' => 'Partner access required'], 403);
        }

        return $next($request);
    }
}
