<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberDevice;
use App\Services\Membership\MemberDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * /account/devices — the web twin of the app's Signed-in devices screen. A browser held at
 * the device limit is redirected here by EnforceMemberDevice and is let back in as soon as a
 * slot frees.
 */
class MemberDevicesController extends Controller
{
    public function __construct(private readonly MemberDevices $devices) {}

    public function show(Request $request): View|RedirectResponse
    {
        $current = $this->current($request);

        return view('site.account-devices', [
            'title' => 'Signed-in devices',
            'state' => $this->devices->state($request->user(), $current),
        ]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $device = $this->devices->findLive($request->user(), $id);
        if ($device === null) {
            return redirect()->route('site.account.devices')->with('success', 'That device is already signed out.');
        }

        $current = $this->current($request);

        if ($current !== null && $current->id === $device->id) {
            // "Sign out of this browser instead": the Logout listener revokes the device.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/')->with('success', 'Signed out.');
        }

        $this->devices->revoke($device, MemberDevice::REASON_REMOVED);

        // Freed a slot for a browser that was waiting: straight back to where it was going.
        if ($current !== null && $current->refresh()->isPending() && $this->devices->evaluate($current) === MemberDevices::DECISION_OK) {
            return redirect()->route('site.profile')->with('success', "Signed out {$device->name}. You're in on this browser.");
        }

        return redirect()->route('site.account.devices')->with('success', "Signed out {$device->name}.");
    }

    private function current(Request $request): ?MemberDevice
    {
        $device = $request->attributes->get('member_device');

        return $device instanceof MemberDevice ? $device : null;
    }
}
