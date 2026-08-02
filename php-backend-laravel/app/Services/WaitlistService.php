<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turning a cancellation into revenue.
 *
 * When a court-hour frees up, everyone who wanted that window gets a time-boxed
 * offer, oldest request first. The offer expires on its own so a slot can never be
 * silently held by someone who is never going to pay — an un-expiring waitlist is
 * worse than no waitlist, because it looks sold while earning nothing.
 *
 * Matching, offering and expiry are automatic. *Sending* the offer is one tap in
 * the console for now (see {@see markNotified()}); wiring it to the WhatsApp
 * template stack is the next hook, not a rewrite.
 */
class WaitlistService
{
    /** How long someone gets to take a freed slot before it moves down the list. */
    public const OFFER_WINDOW_MINUTES = 90;

    /** How many people are offered a freed slot at once. */
    public const OFFERS_PER_SLOT = 3;

    /**
     * Everyone still waiting who would take this court-hour, oldest request first.
     *
     * Two deliberate looseness rules, because a waitlist that only matches exactly
     * never fires: an entry with no court wants any court, and an entry with no
     * time wants any time that day.
     *
     * @return Collection<int, WaitlistEntry>
     */
    public function matchesFor(Booking $booking): Collection
    {
        if ($booking->venue_id === null || $booking->slot_date === null) {
            return new Collection;
        }

        $candidates = WaitlistEntry::query()
            ->waiting()
            ->where('venue_id', $booking->venue_id)
            ->whereDate('wanted_on', $booking->slot_date)
            ->where(fn ($q) => $q
                ->whereNull('venue_court_id')
                ->orWhere('venue_court_id', $booking->venue_court_id))
            ->orderBy('created_at')
            ->get();

        $freedStart = $this->minutes($booking->start_time);
        $freedEnd = $this->minutes($booking->end_time);

        return $candidates->filter(function (WaitlistEntry $entry) use ($freedStart, $freedEnd): bool {
            // "Any time that day" always matches.
            if ($entry->start_time === null) {
                return true;
            }

            $wantStart = $this->minutes($entry->start_time);
            $wantEnd = $this->minutes($entry->end_time) ?? ($wantStart === null ? null : $wantStart + 60);

            // A booking with no window can't be reasoned about — offer it anyway
            // rather than silently dropping a real chance to re-sell.
            if ($freedStart === null || $freedEnd === null || $wantStart === null || $wantEnd === null) {
                return true;
            }

            return $wantStart < $freedEnd && $wantEnd > $freedStart;
        })->values();
    }

    /**
     * Offer a freed slot to the front of the queue.
     *
     * Several people are offered at once and the first to pay keeps it — offering
     * one at a time means a 90-minute wait per non-responder, and the slot is gone
     * by then anyway.
     *
     * @return Collection<int, WaitlistEntry>  the entries that were offered
     */
    public function offerFreedSlot(Booking $booking, int $limit = self::OFFERS_PER_SLOT): Collection
    {
        $matches = $this->matchesFor($booking)->take($limit);

        if ($matches->isEmpty()) {
            return new Collection;
        }

        return DB::transaction(function () use ($matches, $booking): Collection {
            $matches->each(function (WaitlistEntry $entry) use ($booking): void {
                $entry->forceFill([
                    'status' => WaitlistEntry::STATUS_OFFERED,
                    'offered_at' => now(),
                    'offer_expires_at' => now()->addMinutes(self::OFFER_WINDOW_MINUTES),
                    'freed_by_booking_id' => $booking->id,
                ])->save();
            });

            return $matches;
        });
    }

    /** Stamp that the customer was actually contacted. */
    public function markNotified(WaitlistEntry $entry): WaitlistEntry
    {
        $entry->forceFill(['notified_at' => now()])->save();

        return $entry;
    }

    /**
     * They took it. Links the entry to the booking that resulted so the venue can
     * see what the waitlist actually recovered.
     */
    public function convert(WaitlistEntry $entry, Booking $booking): WaitlistEntry
    {
        $entry->forceFill([
            'status' => WaitlistEntry::STATUS_CONVERTED,
            'converted_booking_id' => $booking->id,
        ])->save();

        return $entry;
    }

    /** They passed, or the desk is clearing the list. */
    public function cancel(WaitlistEntry $entry): WaitlistEntry
    {
        $entry->forceFill(['status' => WaitlistEntry::STATUS_CANCELLED])->save();

        return $entry;
    }

    /**
     * Expire offers nobody took, putting those people back in the queue rather
     * than dropping them — they still want the slot, they just missed this one.
     *
     * @return int  how many lapsed
     */
    public function releaseLapsedOffers(): int
    {
        $lapsed = WaitlistEntry::query()->lapsed()->get();

        foreach ($lapsed as $entry) {
            $entry->forceFill([
                'status' => WaitlistEntry::STATUS_WAITING,
                'offered_at' => null,
                'offer_expires_at' => null,
                'freed_by_booking_id' => null,
            ])->save();
        }

        return $lapsed->count();
    }

    /** Money the waitlist actually recovered, for a venue, since a date. */
    public function recovered(int $venueId, ?\DateTimeInterface $since = null): float
    {
        // Every column is table-qualified: `venue_id`, `status` and `updated_at`
        // all exist on both sides of this join.
        return (float) WaitlistEntry::query()
            ->join('bookings', 'bookings.id', '=', 'slot_waitlist.converted_booking_id')
            ->where('slot_waitlist.venue_id', $venueId)
            ->where('slot_waitlist.status', WaitlistEntry::STATUS_CONVERTED)
            ->when($since !== null, fn ($q) => $q->where('slot_waitlist.updated_at', '>=', $since))
            ->sum('bookings.total_amount');
    }

    /** "7:00 PM" / "19:00" → minutes from midnight. */
    private function minutes(?string $label): ?int
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $ts = strtotime(trim($label));

        return $ts === false ? null : (int) date('G', $ts) * 60 + (int) date('i', $ts);
    }
}
