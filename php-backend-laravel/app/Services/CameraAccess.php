<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\User;
use App\Models\Venue;
use App\Services\Membership\MemberEntitlements;
use App\Support\BusinessClock;
use App\Support\Membership\MemberFeature;
use App\Support\PlatformRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * May this match use second-screen cameras (and their live ball tracking) right now, and how many?
 *
 * Asked about the MATCH, not the camera phone: a camera joins with a pairing code and no
 * account, so what unlocks it is the person who owns the match. Two ways in:
 *
 *  · a plan whose "Camera angles per match" is above zero (Pro and Hero; per plan in /control), or
 *  · a confirmed venue booking whose slot covers this moment — a 6–7 PM slot works from 6:00
 *    to 7:00 that evening and not outside it, give or take the grace minutes in /control
 *    (0 by default). It unlocks the number of cameras set in /control.
 *
 * Cameras are told when the window shuts ([until]) so they pause on time between check-ins,
 * and when the next one opens ([opensAt]) so the scorer can be told.
 *
 * Desk walk-ins never count: their `user_id` is the venue owner who typed them in, not a
 * player — counting them would unlock cameras for every partner all day.
 */
final class CameraAccess
{
    public const VIA_PLAN = 'plan';

    public const VIA_BOOKING = 'booking';

    public function __construct(private readonly MemberEntitlements $entitlements)
    {
    }

    /**
     * @return array{allowed: bool, via: ?string, angles: ?int, until: ?string, opensAt: ?string, venue: ?string}
     */
    public function forMatch(?LiveMatch $match, ?Carbon $now = null): array
    {
        $owner = $match?->user_id ? User::find($match->user_id) : null;

        return $owner === null ? $this->answer(false) : $this->forOwner($owner, $now);
    }

    /**
     * @return array{allowed: bool, via: ?string, angles: ?int, until: ?string, opensAt: ?string, venue: ?string}
     */
    public function forOwner(User $owner, ?Carbon $now = null): array
    {
        $set = $this->entitlements->for($owner);
        if ($set->allows(MemberFeature::MATCHES_CAMERA_ANGLES)) {
            return $this->answer(true, self::VIA_PLAN, $set->limit(MemberFeature::MATCHES_CAMERA_ANGLES));
        }

        // The admin switched the booking rule off: the plan's own limit is the only answer.
        if (! PlatformRules::bool('creation.camera_booking_gate')) {
            return $this->answer(false);
        }

        return $this->fromBookings($owner, $now ?? BusinessClock::now());
    }

    /** @return array{allowed: bool, via: ?string, angles: ?int, until: ?string, opensAt: ?string, venue: ?string} */
    private function fromBookings(User $owner, Carbon $now): array
    {
        $now = $now->copy()->setTimezone(BusinessClock::zone());
        $grace = max(0, PlatformRules::int('creation.camera_booking_grace_minutes'));
        $today = $now->toDateString();
        // Yesterday's too: an 11 PM – 1 AM slot is still running after midnight.
        $dates = [$today, $now->copy()->subDay()->toDateString()];

        $rows = DB::table('bookings')
            ->where('user_id', $owner->id)
            ->where('booking_type', 'venue')
            ->whereRaw('UPPER(status) = ?', ['CONFIRMED'])
            ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', '!=', 'offline'))
            ->where(function ($q) use ($dates) {
                foreach ($dates as $d) {
                    $q->orWhereDate('slot_date', $d);
                }
            })
            ->get(['venue_id', 'slot_date', 'start_time', 'end_time']);

        /** @var list<array{0: Carbon, 1: Carbon, 2: ?int}> $windows */
        $windows = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->slot_date, 0, 10);
            $start = BusinessClock::at($date, (string) $row->start_time);
            $end = BusinessClock::at($date, (string) $row->end_time);
            if ($start === null || $end === null) {
                continue;
            }
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $windows[] = [$start->subMinutes($grace), $end->addMinutes($grace), $row->venue_id ? (int) $row->venue_id : null];
        }
        usort($windows, fn ($a, $b) => $a[0] <=> $b[0]);

        $angles = max(1, PlatformRules::int('creation.camera_booking_angles'));

        foreach ($windows as [$start, $end, $venueId]) {
            if ($now->lessThan($start) || $now->greaterThanOrEqualTo($end)) {
                continue;
            }
            // Back-to-back slots (6–7 then 7–8) are one stretch: the cameras shouldn't blink off at 7.
            $until = $end->copy();
            foreach ($windows as [$s, $e]) {
                if ($s->lessThanOrEqualTo($until) && $e->greaterThan($until)) {
                    $until = $e->copy();
                }
            }

            return $this->answer(true, self::VIA_BOOKING, $angles, $until, null, $this->venueName($venueId));
        }

        foreach ($windows as [$start, , $venueId]) {
            if ($start->greaterThan($now) && $start->toDateString() === $today) {
                return $this->answer(false, null, null, null, $start, $this->venueName($venueId));
            }
        }

        return $this->answer(false);
    }

    private function venueName(?int $venueId): ?string
    {
        if ($venueId === null) {
            return null;
        }

        $name = Venue::whereKey($venueId)->value('name');

        return $name ? (string) $name : null;
    }

    /** @return array{allowed: bool, via: ?string, angles: ?int, until: ?string, opensAt: ?string, venue: ?string} */
    private function answer(bool $allowed, ?string $via = null, ?int $angles = null, ?Carbon $until = null, ?Carbon $opensAt = null, ?string $venue = null): array
    {
        return [
            'allowed' => $allowed,
            'via' => $via,
            // Null with a plan = no ceiling; 0 when locked.
            'angles' => $allowed ? $angles : 0,
            'until' => $until?->copy()->utc()->toIso8601String(),
            'opensAt' => $opensAt?->copy()->utc()->toIso8601String(),
            'venue' => $venue,
        ];
    }
}
