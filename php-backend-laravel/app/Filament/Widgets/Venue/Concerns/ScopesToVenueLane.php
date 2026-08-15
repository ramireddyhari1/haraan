<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Venue\Concerns;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\Booking;
use App\Models\Venue;
use App\Support\PartnerBranchContext;
use App\Support\PartnerLane;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared scoping for the venue-lane dashboard widgets.
 *
 * Two rules every widget on this dashboard has to obey:
 *
 *  1. **Venue lane only.** These widgets are the GameHub half of the partner
 *     console; an event host must never see them. The events lane keeps the ten
 *     widgets it already has, untouched.
 *  2. **Read through VenueResource, never `Venue::` / `Booking::` directly.**
 *     Reading the model directly bypasses ScopesToOrganization and leaks
 *     platform-wide totals to a partner — the exact bug class already fixed once
 *     on the Events KPI header and GameHubStatsWidget.
 */
trait ScopesToVenueLane
{
    /** Bookings that represent money actually collected, historically. */
    protected const PAID_STATUSES = ['confirmed', 'paid', 'completed', 'checked_in'];

    /** Statuses that mean the slot was genuinely sold (not cancelled/expired). */
    protected const LIVE_STATUSES = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * The branch-operating lanes — sports venues AND cafés, never event hosts.
     *
     * These widgets are shared by both because their arithmetic is identical:
     * money collected, bookings taken, peak hours, who is coming. Only the NOUN
     * differs (a court vs a table), and that resolves through
     * {@see resourceNoun()} rather than by forking four widgets — a fork would
     * mean every future fix has to be made twice, and one copy would rot.
     *
     * What must never happen is a café silently inheriting SPORTS language. That
     * is why the lane is separate and the noun is looked up per lane, instead of
     * "not the events lane" standing in for "a turf".
     */
    public static function canView(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return false;
        }

        $lane = auth()->user()?->partnerLane();

        return $lane !== null && PartnerLane::isBranchLane($lane);
    }

    /** This viewer's lane. Widgets use it to pick their vocabulary. */
    protected function lane(): string
    {
        return auth()->user()?->partnerLane() ?? PartnerLane::GAMEHUB;
    }

    /** "court" / "table" — what a bookable unit is called in this lane. */
    protected function resourceNoun(bool $plural = false): string
    {
        return PartnerLane::resourceNoun($this->lane(), $plural);
    }

    /** "court-hours" / "table-hours" — the occupancy denominator's unit. */
    protected function resourceHours(): string
    {
        return PartnerLane::resourceHours($this->lane());
    }

    /**
     * The venues these widgets read, already org/partner-scoped by the resource
     * and then narrowed to the branch the topbar switcher has selected.
     *
     * The switcher would be decoration without this line: every number on this
     * dashboard flows through here, so selecting "Koramangala" changes the whole
     * page rather than just the label above it. No selection means all branches,
     * which is both the default and what a single-branch partner always gets.
     *
     * The narrowing is a filter on an already-scoped query, never a replacement
     * for it — a tampered session value can only ever select a branch the
     * resource query would have returned anyway.
     */
    protected function venueIds(): Builder
    {
        $query = VenueResource::getEloquentQuery()->select('venues.id');
        $branchId = PartnerBranchContext::currentId();

        return $branchId === null ? $query : $query->where('venues.id', $branchId);
    }

    /** @return \Illuminate\Support\Collection<int, Venue> */
    protected function venues()
    {
        return Venue::query()->whereIn('id', $this->venueIds())->get();
    }

    /** Every venue booking belonging to this partner. */
    protected function bookings(): Builder
    {
        return Booking::query()
            ->where('booking_type', 'venue')
            ->whereIn('venue_id', $this->venueIds());
    }

    /** Bookings that count as sold — the denominator-safe set. */
    protected function liveBookings(): Builder
    {
        return $this->bookings()->whereIn(
            \Illuminate\Support\Facades\DB::raw('lower(status)'),
            self::LIVE_STATUSES,
        );
    }

    /** ₹18,42,900 — Indian digit grouping, whole rupees. */
    protected function inr(float $n): string
    {
        $n = (int) round($n);
        $sign = $n < 0 ? '-' : '';
        $str = (string) abs($n);

        if (strlen($str) <= 3) {
            return $sign . '₹' . $str;
        }

        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return $sign . '₹' . $rest . ',' . $last3;
    }

    /** Percentage change, guarding the divide-by-zero that reads as "+100%". */
    protected function growth(float $now, float $before): ?float
    {
        if ($before <= 0.0) {
            return null;
        }

        return round(($now - $before) / $before * 100, 1);
    }
}
