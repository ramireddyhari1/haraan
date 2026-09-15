<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a JSON API route to platform administrators (ADMIN / COADMIN).
 *
 * Runs after auth.jwt, so the user is already on the request. A valid JWT on its own
 * proves only that someone is signed in — every member has one — so any route that
 * reads or rewrites OTHER people's accounts must pass through here as well.
 */
final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'isSuperAdmin') || ! $user->isSuperAdmin()) {
            return new JsonResponse(['error' => 'Administrator access required'], 403);
        }

        return $next($request);
    }
}
