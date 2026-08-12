<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Venue\Concerns;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\Booking;
use App\Models\Venue;
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
     * The venue lane, and only the venue lane.
     *
     * Partners without a `partner_type` fall back to venue — the historical
     * default — so legacy accounts aren't stranded with a blank dashboard.
     */
    public static function canView(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return false;
        }

        return auth()->user()?->partner_type !== 'event';
    }

    /** This partner's venues, already org/partner-scoped by the resource. */
    protected function venueIds(): Builder
    {
        return VenueResource::getEloquentQuery()->select('venues.id');
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
