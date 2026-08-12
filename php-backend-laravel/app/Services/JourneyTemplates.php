<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;

/**
 * The words each journey step sends, rendered fresh from the booking at dispatch
 * time so a rescheduled event quotes the new time.
 *
 * Free-text for now, which only works inside an open 24h window. Before these go
 * out to a cold recipient each needs a template approved in WhatsApp Manager;
 * message_templates maps a key to that template's name and variables.
 *
 * Every journey message carries the STOP line: it's how someone leaves, and the
 * inbound handler that honours it is the next slice of work.
 */
final class JourneyTemplates
{
    /** Render a step, or null if the key is unknown. */
    public function render(string $key, Booking $booking): ?string
    {
        $title = $this->title($booking);
        $when = $this->when($booking);
        $passUrl = url('/bookings/' . $booking->id . '/pass');

        return match ($key) {
            'event.reminder_24h' => $this->sign(
                "Tomorrow: {$title}\n{$when}\n\nYour ticket & QR: {$passUrl}"
            ),
            'event.reminder_2h' => $this->sign(
                "Starting soon: {$title}\n{$when}\n\nHave your QR ready: {$passUrl}"
            ),
            'review.request' => $this->sign(
                "Hope you enjoyed {$title}.\n\nHow was it? Leave a quick rating here: "
                . $this->reviewUrl($booking)
                . "\n\nYour feedback helps the organiser and everyone booking next."
            ),
            default => null,
        };
    }

    /**
     * Where "how was it?" goes.
     *
     * Code-addressed and sessionless, like the ticket pass: the person who attended
     * often isn't the person who paid, and a login wall is how you get no ratings.
     * A booking with no code has nothing to address, so it falls back to the site
     * rather than linking to a guaranteed 404.
     */
    private function reviewUrl(Booking $booking): string
    {
        $code = trim((string) $booking->ticket_code);

        return $code !== '' ? url('/r/' . $code) : url('/');
    }

    /**
     * The positional variables for the approved WhatsApp template behind a key.
     *
     * These must line up with the {{1}}, {{2}} placeholders submitted to Meta —
     * the registry row (message_templates.variables) documents the order, and
     * this is what fills it. Kept beside render() on purpose: when the wording
     * of one changes, the other is right there.
     *
     * @return array<int, string>
     */
    public function variables(string $key, Booking $booking): array
    {
        $title = $this->title($booking);
        $when = $this->when($booking);
        $passUrl = url('/bookings/' . $booking->id . '/pass');

        return match ($key) {
            'event.reminder_24h', 'event.reminder_2h' => [$title, $when, $passUrl],
            'review.request' => [$title, $this->reviewUrl($booking)],
            default => [],
        };
    }

    /**
     * Messages go out from Haraan's shared number, not the partner's, so the
     * organiser is named in the body — otherwise the recipient has no idea who
     * is texting them about their Saturday.
     */
    private function sign(string $body): string
    {
        return $body . "\n\n— Haraan\nReply STOP to opt out.";
    }

    private function title(Booking $booking): string
    {
        return (string) ($booking->event?->title ?? $booking->venue?->name ?? 'your booking');
    }

    private function when(Booking $booking): string
    {
        $tz = (string) config('messaging.local_timezone', 'Asia/Kolkata');

        if ($booking->venue_id !== null && $booking->slot_date !== null) {
            $date = Carbon::parse($booking->slot_date)->format('D, d M');
            $slot = trim((string) ($booking->slot_label ?? ''));

            return $slot !== '' ? "{$date} · {$slot}" : $date;
        }

        $event = $booking->event;

        if ($event?->date === null) {
            return '';
        }

        $date = Carbon::parse($event->date)->format('D, d M');
        $time = trim((string) $event->time);

        if ($time === '') {
            return $date;
        }

        // Stored times are local wall clock; show them as typed rather than
        // round-tripping through UTC and shifting the hour.
        try {
            $time = Carbon::parse($time, $tz)->format('g:i A');
        } catch (\Throwable) {
            // leave the raw value — a weird string is better than a wrong time
        }

        return "{$date} · {$time}";
    }
}
