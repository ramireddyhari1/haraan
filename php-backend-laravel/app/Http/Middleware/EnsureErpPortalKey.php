<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the legacy ERP portal and Blade admin (/erp, /admin/*). /control is the real
 * front door; this stays hidden from everyone else.
 *
 * Used to wave through ANY signed-in user (a player logged in on the website counted)
 * and to fall back to a built-in key ('letmein') when ERP_PORTAL_KEY was unset — which
 * it was on prod. Now:
 *  - a signed-in user passes only if they are an active super-admin (ADMIN/COADMIN);
 *  - otherwise the request needs ERP_PORTAL_KEY, which must be set and at least 24
 *    characters; there is no default, so an unset key closes the portal entirely.
 */
final class EnsureErpPortalKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user !== null && $user->isSuperAdmin() && $user->isAccountActive()) {
            return $next($request);
        }

        $expected = (string) config('app.erp_portal_key', '');
        $given = (string) $request->query('key', '');

        if (strlen($expected) < 24 || ! hash_equals($expected, $given)) {
            abort(404);
        }

        return $next($request);
    }
}
