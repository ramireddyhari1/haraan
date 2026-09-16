<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level boolean member gate: `->middleware('member.entitled:ai.career_read')`.
 * Place after auth.jwt / auth.jwt.optional so `auth_user` is resolved. Denials render
 * EntitlementDenied's shared JSON.
 */
final class EnsureMemberEntitled
{
    public function __construct(private readonly MemberEntitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->attributes->get('auth_user');

        $this->entitlements->authorize($user instanceof User ? $user : null, $feature);

        return $next($request);
    }
}
