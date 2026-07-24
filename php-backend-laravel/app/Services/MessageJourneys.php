<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\MessagingOptOut;
use App\Models\ScheduledMessage;
use App\Support\MessageContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outbound journeys: the messages Haraan sends on a schedule rather than in
 * response to something the customer just did.
 *
 *   event.reminder_24h  — the day before, so they don't forget
 *   event.reminder_2h   — on the day, with the ticket link
 *   review.request      — after it's over, which is where venue reviews come from
 *
 * Two halves, both idempotent so they can run on a dumb cron tick:
 *
 *   enqueue()  — walks upcoming confirmed bookings and writes queue rows. The
 *                unique dedupe_key means running it every 10 minutes costs one
 *                failed insert per existing row, not a duplicate message.
 *   dispatch() — drains what's due. Re-reads the booking at send time, so a
 *                cancellation or a rescheduled event is honoured rather than
 *                sending yesterday's truth.
 */
final class MessageJourneys
{
    /** Steps, with how long BEFORE the start each fires (review is handled apart). */
    private const REMINDERS = [
        'event.reminder_24h' => 24,
        'event.reminder_2h' => 2,
    ];

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly JourneyTemplates $templates,
        private readonly PlanEntitlements $entitlements,
        private readonly TemplateResolver $resolver,
    ) {}

    // -------------------------------------------------------------------------
    // Enqueue
    // -------------------------------------------------------------------------

    /**
     * Queue every journey step for bookings starting inside the horizon.
     *
     * @return array{queued: int, scanned: int}
     */
    public function enqueue(): array
    {
        $horizon = Carbon::now()->addDays((int) config('messaging.journeys.horizon_days', 7));

        $bookings = Booking::query()
            ->with(['event', 'venue', 'user', 'ticketType'])
            // Booking status casing is inconsistent in this database ('confirmed'
            // and 'CONFIRMED' both exist), so never compare it directly.
            ->whereRaw('lower(status) = ?', ['confirmed'])
            ->where(function ($q): void {
                $q->whereNotNull('event_id')->orWhereNotNull('venue_id');
            })
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->get();

        $queued = 0;
        $scanned = 0;

        foreach ($bookings as $booking) {
            $start = $this->startsAt($booking);

            if ($start === null || $start->greaterThan($horizon)) {
                continue;
            }

            $scanned++;
            $queued += $this->enqueueForBooking($booking, $start);
        }

        return ['queued' => $queued, 'scanned' => $scanned];
    }

    /**
     * Queue the steps for one booking. Steps whose moment has already passed are
     * skipped outright — a reminder for an event that started an hour ago is
     * worse than no reminder.
     */
    public function enqueueForBooking(Booking $booking, ?Carbon $start = null): int
    {
        $start ??= $this->startsAt($booking);

        if ($start === null) {
            return 0;
        }

        $phone = $this->phone($booking);

        if ($phone === null) {
            return 0;
        }

        $context = MessageContext::forBooking($booking);
        $now = Carbon::now();
        $queued = 0;

        foreach (self::REMINDERS as $template => $hoursBefore) {
            // An event with no start time only gets the day-before nudge; "2 hours
            // before midnight" is a guess dressed up as a service.
            if ($template === 'event.reminder_2h' && ! $this->hasExplicitTime($booking)) {
                continue;
            }

            $sendAt = $start->copy()->subHours($hoursBefore);

            if ($sendAt->lessThan($now)) {
                continue;
            }

            $queued += $this->queue($booking, $context, $phone, $template, $sendAt) ? 1 : 0;
        }

        $reviewAt = $this->endsAt($booking, $start)
            ->addHours((int) config('messaging.journeys.review_delay_hours', 3));

        if ($reviewAt->greaterThan($now)) {
            $queued += $this->queue($booking, $context, $phone, 'review.request', $reviewAt) ? 1 : 0;
        }

        return $queued;
    }

    /** Insert one queue row; a duplicate key means it's already scheduled. */
    private function queue(
        Booking $booking,
        MessageContext $context,
        string $phone,
        string $template,
        Carbon $sendAt,
    ): bool {
        $dedupe = $template . ':booking:' . $booking->id;

        try {
            ScheduledMessage::create([
                'partner_id' => $context->partnerId,
                'channel' => 'whatsapp',
                'recipient' => $phone,
                'template_key' => $template,
                'category' => MessageContext::UTILITY,
                'payload' => ['booking_id' => (int) $booking->id],
                'context_type' => 'booking',
                'context_id' => (int) $booking->id,
                'dedupe_key' => $dedupe,
                'send_after' => $sendAt,
            ]);

            return true;
        } catch (QueryException $e) {
            // Unique violation = already queued. That's the normal path on every
            // run after the first, so it isn't worth a log line.
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    /**
     * Send everything that's due.
     *
     * @return array{sent: int, skipped: int, failed: int, held: int}
     */
    public function dispatch(int $limit = 200): array
    {
        $due = ScheduledMessage::query()
            ->where('status', ScheduledMessage::STATUS_PENDING)
            ->where('send_after', '<=', Carbon::now())
            ->orderBy('send_after')
            ->limit($limit)
            ->get();

        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'held' => 0];

        foreach ($due as $message) {
            $outcome = $this->dispatchOne($message);
            $result[$outcome]++;
        }

        return $result;
    }

    /** @return 'sent'|'skipped'|'failed'|'held' */
    private function dispatchOne(ScheduledMessage $message): string
    {
        // Quiet hours hold rather than drop: a 3am review request becomes an 8am
        // review request, not a lost one.
        if ($this->inQuietHours()) {
            return 'held';
        }

        $booking = Booking::query()->with(['event', 'venue', 'ticketType'])->find($message->context_id);

        if ($booking === null || strtolower((string) $booking->status) !== 'confirmed') {
            $this->skip($message, $booking === null ? 'cancelled' : 'cancelled');

            return 'skipped';
        }

        if (MessagingOptOut::blocks($message->channel, $message->recipient, $message->partner_id)) {
            $this->skip($message, 'opted_out');

            return 'skipped';
        }

        // Re-check the clock against fresh data: an event moved a week later
        // shouldn't fire the reminder that was queued for the old date.
        $start = $this->startsAt($booking);

        if ($start !== null && str_starts_with($message->template_key, 'event.reminder')
            && $start->lessThan(Carbon::now())) {
            $this->skip($message, 'too_late');

            return 'skipped';
        }

        // Plan gate. Journeys are a paid feature; ticket delivery is not, and
        // BookingNotifier deliberately never comes through here.
        $entitlement = $this->entitlements->canAutomate(
            $message->partner_id,
            \App\Models\PartnerPlan::FEATURE_JOURNEYS,
            $message->channel,
        );

        if (! $entitlement['allowed']) {
            $this->skip($message, (string) $entitlement['reason']);

            return 'skipped';
        }

        $body = $this->templates->render($message->template_key, $booking);

        if ($body === null) {
            $this->skip($message, 'no_template');

            return 'skipped';
        }

        // The master switch. The queue still fills and drains its bookkeeping, but
        // nothing reaches a customer until journeys are deliberately switched on.
        if (! (bool) config('messaging.journeys.enabled', false)) {
            return 'held';
        }

        $context = new MessageContext(
            $message->partner_id,
            $message->category,
            $message->template_key,
            'booking',
            (int) $booking->id,
        );

        // A journey is business-initiated, so free text only works inside a window
        // the customer opened. Outside one, an approved template is the only legal
        // send — and if there isn't one, say so rather than letting Twilio reject it.
        $route = $this->resolver->resolve($message->template_key, $message->channel, $message->recipient);

        if ($route['mode'] === TemplateResolver::MODE_BLOCKED) {
            $this->skip($message, (string) $route['reason']);

            return 'skipped';
        }

        try {
            $ok = $route['mode'] === TemplateResolver::MODE_TEMPLATE
                ? $this->whatsapp->sendTemplate(
                    $message->recipient,
                    (string) $route['sid'],
                    $this->templates->variables($message->template_key, $booking),
                    $context,
                )
                : $this->whatsapp->sendMessage($message->recipient, $body, $context);
        } catch (Throwable $e) {
            $ok = false;
            $message->error = mb_substr($e->getMessage(), 0, 2000);
        }

        $message->attempts++;

        if ($ok) {
            $message->status = ScheduledMessage::STATUS_SENT;
            $message->sent_at = Carbon::now();
            $message->message_log_id = DB::table('message_log')->max('id');
            $message->save();

            return 'sent';
        }

        // Retry a couple of times before giving up — a Twilio blip shouldn't cost
        // the customer their reminder, but a rejected template will never work.
        if ($message->attempts >= (int) config('messaging.journeys.max_attempts', 3)) {
            $message->status = ScheduledMessage::STATUS_FAILED;
        } else {
            $message->send_after = Carbon::now()->addMinutes(15);
        }

        $message->save();

        return $message->status === ScheduledMessage::STATUS_FAILED ? 'failed' : 'held';
    }

    private function skip(ScheduledMessage $message, string $reason): void
    {
        $message->status = ScheduledMessage::STATUS_SKIPPED;
        $message->skip_reason = $reason;
        $message->save();
    }

    private function inQuietHours(): bool
    {
        $hour = (int) Carbon::now()->setTimezone($this->timezone())->format('G');
        $start = (int) config('messaging.journeys.quiet_hours.start', 21);
        $end = (int) config('messaging.journeys.quiet_hours.end', 8);

        // The window wraps midnight, so it's "at or after 9pm OR before 8am".
        return $start <= $end ? ($hour >= $start && $hour < $end) : ($hour >= $start || $hour < $end);
    }

    // -------------------------------------------------------------------------
    // Clock
    // -------------------------------------------------------------------------

    private function timezone(): string
    {
        return (string) config('messaging.local_timezone', 'Asia/Kolkata');
    }

    /**
     * When the thing being booked starts, as UTC.
     *
     * Stored dates/times are local wall clock (an admin typing "19:00" means 7pm
     * in India) while the app runs on UTC, so they're parsed in the configured
     * local zone and converted. Returns null when there's nothing to anchor to.
     */
    public function startsAt(Booking $booking): ?Carbon
    {
        try {
            if ($booking->venue_id !== null && $booking->slot_date !== null) {
                $date = Carbon::parse($booking->slot_date)->format('Y-m-d');
                $time = $this->normaliseTime((string) $booking->start_time) ?? '00:00';

                return Carbon::parse($date . ' ' . $time, $this->timezone())->utc();
            }

            $event = $booking->event;

            if ($event?->date === null) {
                return null;
            }

            $date = Carbon::parse($event->date)->format('Y-m-d');
            $time = $this->normaliseTime((string) $event->time) ?? '00:00';

            return Carbon::parse($date . ' ' . $time, $this->timezone())->utc();
        } catch (Throwable $e) {
            Log::warning('Journey could not resolve a start time for booking ' . $booking->id . ': ' . $e->getMessage());

            return null;
        }
    }

    /** Venue slots carry a real end time; events don't, so assume a duration. */
    private function endsAt(Booking $booking, Carbon $start): Carbon
    {
        if ($booking->venue_id !== null && ($time = $this->normaliseTime((string) $booking->end_time)) !== null) {
            try {
                $date = Carbon::parse($booking->slot_date)->format('Y-m-d');
                $end = Carbon::parse($date . ' ' . $time, $this->timezone())->utc();

                // A slot ending "before" it starts has crossed midnight.
                return $end->lessThan($start) ? $end->addDay() : $end;
            } catch (Throwable) {
                // fall through to the assumed duration
            }
        }

        return $start->copy()->addHours((int) config('messaging.journeys.assumed_duration_hours', 3));
    }

    /** Whether we know the actual clock time, or are falling back to midnight. */
    private function hasExplicitTime(Booking $booking): bool
    {
        return $booking->venue_id !== null
            ? $this->normaliseTime((string) $booking->start_time) !== null
            : $this->normaliseTime((string) ($booking->event->time ?? '')) !== null;
    }

    /** Accepts "19:00", "7:00 PM", "19:00:00"; returns "H:i" or null. */
    private function normaliseTime(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('H:i');
        } catch (Throwable) {
            return null;
        }
    }

    /** Same ladder BookingNotifier uses, so journeys reach whoever the ticket did. */
    private function phone(Booking $booking): ?string
    {
        foreach ([$booking->attendee_phone, $booking->user->phone ?? null, $booking->guest_phone] as $candidate) {
            $digits = preg_replace('/[^0-9]/', '', (string) $candidate);

            if ($digits !== null && strlen($digits) >= 10) {
                return $digits;
            }
        }

        return null;
    }
}
