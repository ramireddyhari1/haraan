<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Booking;
use App\Services\WaitlistService;

/**
 * Reacts to a venue booking being cancelled.
 *
 * An observer rather than a call at each cancel site because there are at least
 * three: the Filament bookings table, `BookingService::cancel()` behind the
 * partner API, and the Day-bookings grid. Hunting them all down leaves the fourth
 * one — the one someone adds next month — silently not offering the freed slot.
 */
class BookingObserver
{
    public function __construct(private readonly WaitlistService $waitlist)
    {
    }

    public function created(Booking $booking): void
    {
        $booking->user?->recalculateKpiMetrics();
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged(['status', 'total_amount', 'user_id'])) {
            $booking->user?->recalculateKpiMetrics();
        }

        if (! $this->justCancelled($booking)) {
            return;
        }

        // Only court time can be re-sold from a waitlist; event orders have their
        // own inventory model.
        if ($booking->booking_type !== 'venue' || $booking->venue_id === null) {
            return;
        }

        $this->waitlist->offerFreedSlot($booking);
    }

    public function deleted(Booking $booking): void
    {
        $booking->user?->recalculateKpiMetrics();
    }

    /**
     * True when this save is the transition INTO cancelled — not a later edit of an
     * already-cancelled row, which would re-offer a slot that is long gone.
     *
     * Status casing is mixed across the codebase ('CANCELLED' from the service,
     * 'cancelled' from Filament), so both sides are lowered.
     */
    private function justCancelled(Booking $booking): bool
    {
        if (! $booking->wasChanged('status')) {
            return false;
        }

        $now = strtolower((string) $booking->status);
        $before = strtolower((string) $booking->getOriginal('status'));

        return in_array($now, ['cancelled', 'canceled'], true)
            && ! in_array($before, ['cancelled', 'canceled'], true);
    }
}
