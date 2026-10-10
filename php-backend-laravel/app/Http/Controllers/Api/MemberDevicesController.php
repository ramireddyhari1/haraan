<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberDevice;
use App\Models\User;
use App\Services\Membership\MemberDevices;
use App\Support\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account → Signed-in devices in the app, and the device chooser a phone lands on when it
 * signs in past the plan's limit. These routes stay reachable for a device held at the
 * chooser (MemberDevices::exemptFromLimit) — they are how it gets out.
 */
class MemberDevicesController extends Controller
{
    public function __construct(private readonly MemberDevices $devices) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json($this->devices->state($user, $this->current($request)));
    }

    /**
     * Sign a device out. Signing out the device making the call is "sign out of this phone
     * instead" on the chooser; any other one frees its slot, and the caller (if it was held)
     * is let in by the fresh state this returns.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $this->user($request);
        $device = $this->devices->findLive($user, $id);

        if ($device === null) {
            return response()->json(['error' => 'not_found', 'message' => 'That device is already signed out.'], 404);
        }

        $current = $this->current($request);
        $this->devices->revoke($device, $current !== null && $current->id === $device->id
            ? MemberDevice::REASON_SIGNED_OUT
            : MemberDevice::REASON_REMOVED);

        if ($current !== null && $current->id === $device->id) {
            return response()->json(['signed_out' => true]);
        }

        return response()->json(['signed_out' => false] + $this->devices->state($user, $current?->refresh()));
    }

    /**
     * Give a session from before device limits a device of its own. The app calls this once
     * when its stored token has no `sid`, and swaps in the token this returns.
     */
    public function enroll(Request $request): JsonResponse
    {
        $user = $this->user($request);

        if (! MemberDevices::isMemberAppRequest($request)) {
            return response()->json(['error' => 'unsupported_client'], 422);
        }

        $ttl = 604800;
        $device = $this->current($request) ?? $this->devices->enrollApp($user, $request, $ttl);
        $device->forceFill(['expires_at' => now()->addSeconds($ttl)])->save();

        return response()->json([
            'token' => JwtService::issueForUser($user, (string) config('app.jwt_secret'), $ttl, $device),
        ] + $this->devices->state($user, $device));
    }

    private function user(Request $request): User
    {
        $user = $request->attributes->get('auth_user');
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function current(Request $request): ?MemberDevice
    {
        $device = $request->attributes->get('member_device');

        return $device instanceof MemberDevice ? $device : null;
    }
}
