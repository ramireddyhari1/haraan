<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\LiveMatch;

/**
 * Haraan venue verification — the moat.
 *
 * A match attached to a CONFIRMED Haraan turf booking is trusted by the venue
 * itself: on completion it auto-settles to 'verified' (×1.25), bypassing the
 * captain-confirm window entirely. Competitors can copy scoring; they can't
 * copy a network of booked venues.
 */
final class VenueVerificationService
{
    /**
     * Resolve a booking that can verify a match: it must exist, be CONFIRMED,
     * and (when $ownerId is given) belong to that user.
     */
    public static function findValidBooking(?int $bookingId, ?int $ownerId = null): ?Booking
    {
        if (!$bookingId) {
            return null;
        }

        $booking = Booking::find($bookingId);
        if ($booking === null) {
            return null;
        }
        if (strtoupper((string) $booking->status) !== 'CONFIRMED') {
            return null;
        }
        if ($ownerId !== null && (int) $booking->user_id !== $ownerId) {
            return null;
        }

        return $booking;
    }

    /**
     * Route a just-completed match: auto-verify if it's on a valid Haraan turf
     * booking, otherwise open the normal 72h captain-confirmation window.
     */
    public static function onMatchCompleted(LiveMatch $match): void
    {
        // Private matches are scoreboards only — no verification, no XP.
        if ($match->is_private) {
            return;
        }

        $booking = self::findValidBooking($match->venue_booking_id);

        if ($booking !== null) {
            MatchVerificationService::verifyByVenue($match, (int) $booking->id);
        } else {
            MatchVerificationService::openVerification($match);
        }
    }
}
