<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\MemberDevice;
use App\Models\User;
use App\Services\Membership\MemberDevices;
use App\Support\Membership\MembershipSettings;
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

        // A suspended account must stop working NOW, not when its token happens to
        // expire. Checked on every request rather than only at sign-in, because the
        // token outlives the decision to suspend by up to seven days.
        //
        // 403, not 401: 401 tells the app "your session ended, sign in again", and it
        // would loop straight back to a login that is also refused. 403 with an explicit
        // code lets the client say why.
        if (! $user->isAccountActive()) {
            return new JsonResponse([
                'error' => 'account_suspended',
                'message' => 'This account has been suspended. Contact support if you think this is a mistake.',
            ], 403);
        }

        // A token minted on a member-app sign-in names its device. A device signed out from
        // another phone, the website or /control ends here with 401 (the app's "signed out"
        // path); one waiting for a free slot gets 403 everywhere except the device chooser.
        if (isset($payload['sid'])) {
            $devices = app(MemberDevices::class);
            $device = MemberDevice::query()
                ->where('public_id', (string) $payload['sid'])
                ->where('user_id', $user->id)
                ->first();

            if ($device === null || $device->isRevoked()) {
                return new JsonResponse([
                    'error' => 'device_signed_out',
                    'message' => 'You were signed out on this device. Sign in again to continue.',
                ], 401);
            }

            $device->setRelation('user', $user);
            $request->attributes->set('member_device', $device);

            if (! MemberDevices::exemptFromLimit($request) && $devices->evaluate($device) === MemberDevices::DECISION_LIMIT) {
                return new JsonResponse([
                    'error' => 'device_limit_reached',
                    'message' => MembershipSettings::text('device_limit_title'),
                    'limit' => $devices->limitFor($user),
                ], 403);
            }

            $devices->touch($device, $request->ip());
        }

        // Bridge JWT auth user with standard Laravel auth guard context
        Auth::setUser($user);
        $request->attributes->set('auth_user', $user);

        // Activity heartbeat for /control (throttled internally to ~5 min).
        $user->touchLastSeen();

        return $next($request);
    }
}

