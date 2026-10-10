<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\MemberDevice;
use App\Models\User;
use App\Services\Membership\MemberDevices;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The website half of device limits. A signed-in browser is a device: the first request after
 * any sign-in (password, OTP, Google, or a remember-me cookie) enrolls it, so no login path
 * can forget to. A browser signed out from elsewhere is logged out here; one past the plan's
 * limit is sent to /account/devices until a slot frees.
 *
 * Staff surfaces (/control, /partner, /employee) never enroll — device limits are for members.
 */
final class EnforceMemberDevice
{
    public const SESSION_KEY = 'member_device_sid';

    /** Names this browser across sessions, so signing in again reuses its slot. */
    public const BROWSER_COOKIE = 'haraan_browser';

    private const SKIP = [
        'control', 'control/*', 'partner', 'partner/*', 'employee', 'employee/*', 'admin/*', 'erp', 'erp/*',
        'livewire/*', 'filament/*', 'broadcasting/*', 'storage/*', 'build/*', 'css/*', 'js/*', 'up',
    ];

    /** Pages a browser held at the device chooser can still open. */
    private const OPEN_WHEN_HELD = [
        'account/devices', 'account/devices/*', 'logout', 'membership', 'membership/*', 'legal/*', 'support', 'support/*',
    ];

    public function __construct(private readonly MemberDevices $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::SKIP) || ! $request->hasSession()) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $sid = $request->session()->get(self::SESSION_KEY);
        $device = is_string($sid) && $sid !== ''
            ? MemberDevice::query()->where('public_id', $sid)->where('user_id', $user->id)->first()
            : null;

        if ($device !== null && $device->isRevoked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'You were signed out of this browser from another device.';

            return $request->expectsJson()
                ? response()->json(['error' => 'device_signed_out', 'message' => $message], 401)
                : redirect('/')->with('error', $message)->with('show_login', true);
        }

        if ($device === null || $this->devices->hasLapsed($device)) {
            $device = $this->devices->enrollWeb($user, $request, $this->browserId($request));
            $request->session()->put(self::SESSION_KEY, $device->public_id);
        }

        $device->setRelation('user', $user);
        $request->attributes->set('member_device', $device);

        if (! $request->is(...self::OPEN_WHEN_HELD) && $this->devices->evaluate($device) === MemberDevices::DECISION_LIMIT) {
            return $request->expectsJson()
                ? response()->json(['error' => 'device_limit_reached', 'message' => 'Too many devices are signed in.'], 403)
                : redirect()->route('site.account.devices');
        }

        $this->devices->touch($device, $request->ip());

        return $next($request);
    }

    private function browserId(Request $request): string
    {
        $id = $request->cookie(self::BROWSER_COOKIE);
        if (is_string($id) && preg_match('/^[A-Za-z0-9]{20,64}$/', $id) === 1) {
            return $id;
        }

        $id = Str::random(40);
        // Five years, HTTP-only. Not tied to the account — it only lets this browser keep its slot.
        Cookie::queue(self::BROWSER_COOKIE, $id, 60 * 24 * 365 * 5, null, null, null, true, false, 'lax');

        return $id;
    }
}
