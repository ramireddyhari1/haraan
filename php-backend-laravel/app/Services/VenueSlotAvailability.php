<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueBlock;
use App\Models\VenueBlockedDate;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Whether each of a venue's start times can actually be booked on a given date.
 *
 * A slot row's `is_available` is only the venue's own on/off switch on a template — it
 * never knew about bookings, so the app used to show a taken 7 PM as open and the player
 * found out at checkout. This answers the question the way checkout does: it reads the
 * same occupying bookings ({@see BookingService::occupyingStatuses()} — confirmed, paid,
 * checked-in, plus live payment holds) and the same court blocks, with the same overlap
 * rules as {@see BookingService::assertCourtHourFree()}, for a one-hour window from each
 * start time (the shortest thing a player can book).
 *
 * Read-only and query-bounded: courts, bookings and blocks for the day are loaded once,
 * then every slot × court is decided in PHP.
 */
final class VenueSlotAvailability
{
    public const OPEN = 'open';

    public const BOOKED = 'booked';

    public const CLOSED = 'closed';

    /**
     * @return list<array{id:int, state:string, courts_free:int, courts_total:int}>
     */
    public function forDate(Venue $venue, Carbon $date): array
    {
        $day = $date->toDateString();
        $slots = VenueSlot::query()->where('venue_id', $venue->id)->get();

        // The same whole-day refusals checkout makes before it ever looks at a court.
        $dayClosed = ! $venue->is_bookable
            || ! $venue->isOpenOn($date)
            || VenueBlockedDate::query()->where('venue_id', $venue->id)->whereDate('date', $day)->exists();

        $courts = VenueCourt::query()->where('venue_id', $venue->id)->where('is_active', true)->get();
        // Composite/sub-courts share floor space: booking the full turf takes its halves.
        $related = $courts->mapWithKeys(fn (VenueCourt $c) => [$c->id => $c->allRelatedCourtIds()]);

        $bookings = $dayClosed ? collect() : Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $venue->id)
            ->whereDate('slot_date', $day)
            ->where(fn ($q) => BookingService::occupyingStatuses($q))
            ->get(['start_time', 'end_time', 'venue_court_id', 'venue_slot_id']);

        $blocks = $dayClosed ? collect() : VenueBlock::query()->applyingOn($venue->id, $date)->get();

        return $slots->map(function (VenueSlot $slot) use ($dayClosed, $courts, $related, $bookings, $blocks): array {
            $eligible = $courts->filter(fn (VenueCourt $c) => $slot->allowsCourt($c))->values();
            $total = max(1, $eligible->count());

            if ($dayClosed || ! $slot->is_available) {
                return $this->row($slot, self::CLOSED, 0, $total);
            }

            $start = BookingService::timeToMinutes($slot->time);
            $end = $start !== null ? $start + 60 : null;

            // Venues that don't model courts (or whose courts can't host this slot's sports)
            // book by slot alone — the legacy one-booking-per-slot rule checkout still applies.
            if ($eligible->isEmpty()) {
                if ($this->blockedBy($blocks, null, $start, $end)) {
                    return $this->row($slot, self::CLOSED, 0, 1);
                }
                $taken = $bookings->contains(fn (Booking $b) => (int) $b->venue_slot_id === (int) $slot->id);

                return $this->row($slot, $taken ? self::BOOKED : self::OPEN, $taken ? 0 : 1, 1);
            }

            $free = 0;
            $blockedCourts = 0;
            foreach ($eligible as $court) {
                if ($this->blockedBy($blocks, (int) $court->id, $start, $end)) {
                    $blockedCourts++;

                    continue;
                }
                if (! $this->bookedOn($bookings, $related[$court->id] ?? [(int) $court->id], $start, $end)) {
                    $free++;
                }
            }

            $state = match (true) {
                $free > 0 => self::OPEN,
                // Every court is under maintenance/a holiday/a private hire: not "booked" by a
                // player, the venue simply isn't selling it.
                $blockedCourts === $eligible->count() => self::CLOSED,
                default => self::BOOKED,
            };

            return $this->row($slot, $state, $free, $total);
        })->values()->all();
    }

    /** True when an occupying booking on any of these court ids overlaps [start, end). */
    private function bookedOn(Collection $bookings, array $courtIds, ?int $start, ?int $end): bool
    {
        foreach ($bookings as $b) {
            if ($b->venue_court_id === null || ! in_array((int) $b->venue_court_id, $courtIds, true)) {
                continue;
            }
            $bs = BookingService::timeToMinutes($b->start_time);
            $be = BookingService::timeToMinutes($b->end_time);

            // A window we can't reason about holds the whole day — checkout refuses it too.
            if ($start === null || $end === null || $bs === null || $be === null) {
                return true;
            }
            if ($start < $be && $end > $bs) {
                return true;
            }
        }

        return false;
    }

    /** True when a block covering this court (null = venue-wide only) overlaps [start, end). */
    private function blockedBy(Collection $blocks, ?int $courtId, ?int $start, ?int $end): bool
    {
        foreach ($blocks as $block) {
            /** @var VenueBlock $block */
            if (! $block->coversCourt($courtId)) {
                continue;
            }
            if ($block->isAllDay() || $start === null || $end === null) {
                return true;
            }
            $bs = BookingService::timeToMinutes($block->start_time);
            $be = BookingService::timeToMinutes($block->end_time);
            if ($bs === null || $be === null || ($start < $be && $end > $bs)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{id:int, state:string, courts_free:int, courts_total:int} */
    private function row(VenueSlot $slot, string $state, int $free, int $total): array
    {
        return [
            'id' => (int) $slot->id,
            'state' => $state,
            'courts_free' => $free,
            'courts_total' => $total,
        ];
    }
}
