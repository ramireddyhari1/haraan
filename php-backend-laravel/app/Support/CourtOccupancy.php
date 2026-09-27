<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\VenueSlot;
use App\Services\BookingService;
use Illuminate\Support\Collection;

/**
 * Which court-hours a booking takes. One rule for partner Home, the desk grid and the
 * Home insights, so "6 of 16" and a bar in the weekly chart can never disagree.
 *
 * App, web and WhatsApp bookings store start/end times and NO slot id — and at a
 * single-court venue no court id either. Matching on the ids alone is how a paid 7 AM
 * booking sat on the desk grid as "Open".
 */
final class CourtOccupancy
{
    /** Booking statuses that carry no money and hold no slot. */
    public const DEAD_STATUSES = ['cancelled', 'refunded', 'failed', 'expired'];

    /**
     * Cancelled, refunded, failed and expired rows are not part of any day's numbers.
     * `failed_overbooked` (a payment that cleared after its court was resold) is a
     * failure too, not a sale.
     */
    public static function isDead(Booking $b): bool
    {
        $status = strtolower((string) $b->status);

        return in_array($status, self::DEAD_STATUSES, true) || str_starts_with($status, 'failed');
    }

    /** A sale that holds a court: not dead, and not a checkout still on the payment screen. */
    public static function isLive(Booking $b): bool
    {
        return ! self::isDead($b) && strtolower((string) $b->status) !== 'pending';
    }

    /**
     * The day's slot rows a booking occupies: its own slot when it was sold against one,
     * otherwise every slot whose hour its start–end window overlaps.
     *
     * @param  Collection<int, VenueSlot>  $slots  the day's rows for this venue
     * @return list<int>
     */
    public static function slotIdsFor(Booking $b, Collection $slots, int $length = 60): array
    {
        if ($b->venue_slot_id !== null && $slots->contains('id', (int) $b->venue_slot_id)) {
            return [(int) $b->venue_slot_id];
        }

        $start = BookingService::timeToMinutes($b->start_time);
        if ($start === null) {
            return [];
        }
        $end = self::endMinutesOf($b->end_time) ?? $start + $length;

        return $slots
            ->filter(function (VenueSlot $s) use ($start, $end, $length): bool {
                $s0 = BookingService::timeToMinutes($s->time);

                return $s0 !== null && $s0 < $end && $s0 + $length > $start;
            })
            ->map(fn (VenueSlot $s): int => (int) $s->id)
            ->values()
            ->all();
    }

    /**
     * The court columns a booking occupies: its own court, or — for a booking with no
     * court, which is a booking of the whole venue — every court.
     *
     * @param  list<int>  $courtIds  the venue's active courts ([] when it has none)
     * @return list<int>
     */
    public static function courtIdsFor(Booking $b, array $courtIds): array
    {
        if ($courtIds === []) {
            return [0];
        }

        return $b->venue_court_id !== null ? [(int) $b->venue_court_id] : $courtIds;
    }

    /** Minutes-of-day a booking ends; midnight ("00:00" / "24:00") is the end of the day. */
    public static function endMinutesOf(?string $label): ?int
    {
        if ($label !== null && str_starts_with(trim($label), '24:')) {
            return 24 * 60;
        }
        $m = BookingService::timeToMinutes($label);

        return $m === 0 ? 24 * 60 : $m;
    }
}
