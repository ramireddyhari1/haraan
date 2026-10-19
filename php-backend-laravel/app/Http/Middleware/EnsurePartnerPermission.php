<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a partner API action to users holding a given capability. Owners hold
 * every capability; desk persons only those in their staff_permissions.
 * Usage: ->middleware('partner.can:pricing')
 *        ->middleware('partner.can:bookings|reports')  // either one is enough
 */
final class EnsurePartnerPermission
{
    public function handle(Request $request, Closure $next, string $permission = ''): Response
    {
        $user = $request->user();

        $allowed = $permission === ''
            || collect(explode('|', $permission))->contains(fn (string $p): bool => $user?->hasPartnerPermission(trim($p)) === true);

        if ($user === null || ! $allowed) {
            return new JsonResponse(['error' => 'Your role does not allow this action'], 403);
        }

        return $next($request);
    }
}
