<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A signed-in player rating a venue from its page — the one write path the app's RATE
 * VENUE sheet and the website's review form share. (The booking-link page /r/{code}
 * stays separate: its author is a guest identified by the booking alone.)
 *
 * One review per person per venue: rating again edits it, so a venue can't be stacked
 * by one account. When the player has a past confirmed booking there, the review is
 * tied to it and reads "Played here". The owner can't rate their own venue. A review
 * /control has hidden stays hidden when its author edits it — the edit doesn't reopen it.
 */
final class VenueReviewService
{
    public function submit(User $user, Venue $venue, int $rating, ?string $text, string $source): VenueReview
    {
        if ((int) $venue->partner_id === (int) $user->id) {
            throw ValidationException::withMessages(['rating' => 'You can’t rate your own venue.']);
        }
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['rating' => 'Pick 1 to 5 stars.']);
        }
        $text = trim((string) $text);
        $text = $text === '' ? null : mb_substr($text, 0, 1000);

        return DB::transaction(function () use ($user, $venue, $rating, $text, $source): VenueReview {
            $review = VenueReview::withTrashed()
                ->where('venue_id', $venue->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first() ?? new VenueReview(['venue_id' => $venue->id, 'user_id' => $user->id]);

            // A review /control deleted is not resurrected by writing again — the
            // player gets a fresh one only if an admin restores or purges the old.
            if ($review->exists && $review->trashed()) {
                throw ValidationException::withMessages(['rating' => 'This review was removed by Haraan and can’t be edited.']);
            }

            $booking = $review->booking_id ? null : $this->pastBooking($user, $venue);

            $review->fill([
                'name' => $this->displayName($user),
                'rating' => $rating,
                'text' => $text,
                'source' => $review->source ?: $source,
                'is_active' => $review->exists ? $review->is_active : true,
            ]);
            if ($booking !== null) {
                $review->booking_id = $booking->id;
            }
            $review->save();

            return $review;
        });
    }

    /**
     * The player's most recent confirmed court booking here that has already started and
     * isn't already carrying a review (booking_id is unique on venue_reviews).
     */
    private function pastBooking(User $user, Venue $venue): ?Booking
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->where('venue_id', $venue->id)
            ->whereRaw('upper(status) in (?, ?)', ['CONFIRMED', 'COMPLETED'])
            ->where('slot_date', '<=', \App\Support\BusinessClock::today())
            ->whereNotIn('id', VenueReview::withTrashed()->whereNotNull('booking_id')->select('booking_id'))
            ->orderByDesc('slot_date')
            ->first();
    }

    /** "Sai K." — a first name and an initial, never a phone number or e-mail. */
    private function displayName(User $user): string
    {
        $name = trim((string) $user->name);
        if ($name === '' || str_contains($name, '@') || preg_match('/^\+?\d[\d\s]{6,}$/', $name)) {
            return 'Haraan player';
        }
        $parts = preg_split('/\s+/', $name) ?: [$name];

        return count($parts) > 1
            ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
            : $parts[0];
    }
}
