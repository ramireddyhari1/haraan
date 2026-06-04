<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class EnsureRole
{
    /**
     * Handle an incoming request.
     * Roles are provided as comma-separated values.
     */
    public function handle(Request $request, Closure $next, string $roles = '')
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();
        $allowed = array_filter(array_map('trim', explode(',', $roles)));

        if (empty($allowed)) {
            return $next($request);
        }

        $role = strtoupper((string) ($user->role ?? ''));

        $upperAllowed = array_map('strtoupper', $allowed);

        $hasLegacyRole = in_array($role, $upperAllowed, true);
        $hasSpatieRole = method_exists($user, 'hasAnyRole') && $user->hasAnyRole($upperAllowed);

        if (! $hasLegacyRole && ! $hasSpatieRole) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
