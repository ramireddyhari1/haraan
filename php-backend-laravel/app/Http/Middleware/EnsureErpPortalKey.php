<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate access to ERP / admin portal routes.
 *
 * Compares the `key` query parameter against the configured
 * {@code app.erp_portal_key} value. Requests without a valid
 * key receive a 404 response so the routes stay invisible to
 * the public.
 */
final class EnsureErpPortalKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow access to authenticated users so they don't need the ERP key after login.
        if (Auth::check()) {
            return $next($request);
        }

        $expected = (string) config('app.erp_portal_key', 'letmein');

        if ($request->query('key') !== $expected) {
            abort(404);
        }

        return $next($request);
    }
}
