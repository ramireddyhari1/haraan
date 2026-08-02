<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\VenueReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * "How was it?" — the page the post-event WhatsApp message links to.
 *
 * Addressed by BOOKING CODE, not by session, for the same reason the ticket pass is:
 * the person who attended may not be the person who paid. A gift recipient and a
 * desk walk-in both have the code and neither has an account, and asking them to log
 * in to leave a rating is how you get no ratings.
 *
 * That makes the code the entire authorisation, so it also has to be the limit:
 *
 *  - **One review per booking**, enforced by a unique index rather than a check, so
 *    a double-submit or a re-opened link can't create two.
 *  - **Only after it happened.** A review written before the event is not a review.
 *  - **Only for confirmed bookings**, so a cancelled order can't leave feedback.
 *
 * Reviews land unpublished-safe: `is_active` is true (partners should see them
 * immediately — that's the point) but the partner console is read-only, so nobody
 * can quietly delete a bad one.
 */
class ReviewController extends Controller
{
    public function show(string $code): View
    {
        $booking = $this->booking($code);
        $existing = VenueReview::query()->where('booking_id', $booking->id)->first();

        return view('site.review', [
            'booking' => $booking,
            'title' => $this->title($booking),
            'existing' => $existing,
            'tooEarly' => $this->tooEarly($booking),
        ]);
    }

    public function store(Request $request, string $code): RedirectResponse
    {
        $booking = $this->booking($code);

        if ($this->tooEarly($booking)) {
            return back()->with('review_error', 'You can leave a review once it has taken place.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['nullable', 'string', 'max:1000'],
        ]);

        // firstOrCreate, not create: the unique index would otherwise turn a
        // double-tap on a slow connection into a 500 on a page whose whole job is
        // to be effortless.
        VenueReview::query()->firstOrCreate(
            ['booking_id' => (int) $booking->id],
            [
                'venue_id' => $booking->venue_id,
                'event_id' => $booking->event_id,
                'name' => $this->reviewerName($booking),
                'rating' => (int) $data['rating'],
                'text' => $data['text'] ?? null,
                'is_active' => true,
            ],
        );

        return redirect()->route('review.show', $code)->with('review_saved', true);
    }

    /** A confirmed booking with this code, or a 404. */
    private function booking(string $code): Booking
    {
        $booking = Booking::query()
            ->with(['event', 'venue', 'user'])
            ->where('ticket_code', $code)
            // Booking status casing is inconsistent in this database
            // ('confirmed' and 'CONFIRMED' both exist), so never compare directly.
            ->whereRaw('lower(status) = ?', ['confirmed'])
            ->first();

        abort_if($booking === null, 404);

        return $booking;
    }

    private function title(Booking $booking): string
    {
        return (string) ($booking->event?->title ?? $booking->venue?->name ?? 'your booking');
    }

    /**
     * Whose name goes on the review.
     *
     * Falls back to "Guest" rather than leaving it blank — an unattributed review
     * reads like a system artefact, and the partner page already renders "Guest"
     * for the seeded rows.
     */
    private function reviewerName(Booking $booking): string
    {
        foreach ([$booking->attendee_name, $booking->user->name ?? null, $booking->guest_name] as $candidate) {
            $name = trim((string) $candidate);

            if ($name !== '') {
                return $name;
            }
        }

        return 'Guest';
    }

    /**
     * Has the thing being reviewed actually happened yet?
     *
     * Dates are stored as local wall clock, so they're read in the configured local
     * zone. A booking with no date at all is treated as reviewable — better to
     * accept a review we can't date than to refuse a genuine one.
     */
    private function tooEarly(Booking $booking): bool
    {
        $tz = (string) config('messaging.local_timezone', 'Asia/Kolkata');

        try {
            if ($booking->venue_id !== null && $booking->slot_date !== null) {
                $date = Carbon::parse($booking->slot_date)->format('Y-m-d');
                $time = trim((string) $booking->start_time) ?: '00:00';

                return Carbon::parse($date . ' ' . $time, $tz)->isFuture();
            }

            if ($booking->event?->date === null) {
                return false;
            }

            $date = Carbon::parse($booking->event->date)->format('Y-m-d');
            $time = trim((string) $booking->event->time) ?: '00:00';

            return Carbon::parse($date . ' ' . $time, $tz)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
