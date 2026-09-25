<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\User;
use App\Support\Membership\MemberFeature;
use App\Support\Membership\MembershipSettings;

/**
 * The two booking perks a plan can carry, read as plain numbers for the booking rules:
 *
 *  - events.early_access — hours before a ticket tier's `sales_start` a member may buy it.
 *  - venues.priority_booking_days — days beyond a venue's booking window a member may book.
 *
 * Off (or no user) is 0, so every caller can apply the number unconditionally and a regular
 * buyer sees exactly the rules they always had. "Unlimited" on a plan is bounded by the
 * admin's ceilings (Membership settings), because an unbounded head start would bypass a sale
 * or a venue's calendar entirely.
 */
final class MemberBookingPerks
{
    public function __construct(private readonly MemberEntitlements $entitlements) {}

    public function earlyAccessHours(?User $user): int
    {
        return $this->amount($user, MemberFeature::EVENTS_EARLY_ACCESS, MembershipSettings::int('early_access_max_hours'));
    }

    public function priorityBookingDays(?User $user): int
    {
        return $this->amount($user, MemberFeature::VENUES_PRIORITY_BOOKING, MembershipSettings::int('priority_booking_max_days'));
    }

    private function amount(?User $user, string $key, int $ceiling): int
    {
        if ($user === null || ! $this->entitlements->allows($user, $key)) {
            return 0;
        }

        $limit = $this->entitlements->limit($user, $key);

        return max(0, min($ceiling, $limit ?? $ceiling));
    }
}
