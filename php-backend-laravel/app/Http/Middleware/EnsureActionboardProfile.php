<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for ranked ActionBoard actions: requires a complete player profile.
 * Must run AFTER auth.jwt (which sets the auth_user attribute).
 *
 * Returns 403 with code 'profile_incomplete' so clients can launch the
 * profile-setup step instead of treating it as a generic error.
 */
final class EnsureActionboardProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        if (!$user->isActionboardProfileComplete()) {
            return new JsonResponse([
                'error' => 'Complete your ActionBoard player profile first.',
                'code'  => 'profile_incomplete',
            ], 403);
        }

        return $next($request);
    }
}
