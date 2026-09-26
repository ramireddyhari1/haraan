<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\WhatsAppAuditLog;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntentExtraction;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppPaymentLink;
use App\Support\BusinessClock;
use App\Support\MessageContext;
use App\Support\PlatformRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Turns a WhatsApp chat into a booking: hold a court, send a payment link, settle.
 *
 * The desk owns none of the booking rules. A hold is an ordinary venue booking made by
 * {@see BookingService::createDeskHold()} — PENDING with a `reserved_until` — so the
 * app, the web checkout and the partner's day grid all see the court as taken while
 * it's live, and as free the moment it lapses. Money goes through {@see BookingLedger},
 * payment links are real Razorpay links, and a link paid after its hold lapsed goes
 * through the same reclaim-or-refund path as a late online payment.
 *
 * Every customer-facing message here rides inside the 24-hour window the customer
 * opened by writing to us, which is the only reason free text may be sent at all.
 */
final class WhatsAppReservationService
{
    /** The default for /control → Platform rules → Bookings → WhatsApp reservation hold. */
    public const HOLD_DURATION_MINUTES = 5;

    public static function holdMinutes(): int
    {
        return max(1, PlatformRules::int('bookings.whatsapp_hold_minutes'));
    }

    public function __construct(
        private readonly BookingService $bookings,
        private readonly BookingLedger $ledger,
        private readonly RazorpayGateway $razorpay,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * Hold a court for this chat's customer. A live hold the chat already had is let go
     * first — one conversation, one hold.
     *
     * @throws ValidationException    When the time has already passed.
     * @throws ConflictHttpException  When the court is taken or the venue is closed.
     */
    public function holdSlot(
        Venue $venue,
        WhatsAppConversation $conversation,
        VenueCourt $court,
        Carbon $date,
        string $startTime,
        string $endTime,
        ?User $actor = null,
    ): Booking {
        $startMin = BookingService::timeToMinutes(substr($startTime, 0, 5));
        $endMin = BookingService::endMinutes(substr($endTime, 0, 5));

        if ($startMin === null || $endMin === null || $endMin <= $startMin) {
            throw ValidationException::withMessages(['end_time' => ['The slot must end after it starts.']]);
        }

        $startsAt = BusinessClock::at($date->toDateString(), substr($startTime, 0, 5));
        if ($startsAt === null || $startsAt->lte(BusinessClock::now())) {
            throw ValidationException::withMessages(['start_time' => ['That time has already passed.']]);
        }

        return DB::transaction(function () use ($venue, $conversation, $court, $date, $startMin, $endMin, $actor): Booking {
            $this->dropLiveHold($conversation);

            $booking = $this->bookings->createDeskHold(
                $actor ?? $conversation->partner,
                (int) $venue->id,
                (int) $court->id,
                $date->toDateString(),
                $startMin,
                $endMin - $startMin,
                $conversation->customer_name ?: null,
                $conversation->phone_number,
                self::holdMinutes(),
            );

            $when = $this->when($booking);
            $minutes = self::holdMinutes();

            $conversation->update([
                'active_booking_id'    => $booking->id,
                'status'               => 'hold_active',
                'last_message_at'      => now(),
                'last_message_preview' => "Held {$court->name} · {$when}",
                'last_message_sender'  => 'system',
            ]);

            $this->systemNote($conversation, $actor,
                "Held {$court->name} · {$when} · ₹".number_format($booking->amountCharged())." · {$minutes} min to pay");

            WhatsAppIntentExtraction::query()
                ->where('conversation_id', $conversation->id)
                ->where('action_state', 'suggested')
                ->update(['action_state' => 'hold_created']);

            WhatsAppAuditLog::log($venue->id, 'hold_created', $conversation->id, $actor?->id, $actor?->name ?? 'System', [
                'booking_id'     => $booking->id,
                'court_id'       => $court->id,
                'date'           => $booking->slot_date,
                'start_time'     => $booking->start_time,
                'end_time'       => $booking->end_time,
                'amount'         => $booking->amountCharged(),
                'reserved_until' => $booking->reserved_until?->toIso8601String(),
            ]);

            return $booking;
        });
    }

    /**
     * Let the chat's hold go. If a payment link went out, Razorpay is asked first: a
     * customer who already paid gets their booking, not a released court.
     *
     * @return string 'released' | 'paid'
     *
     * @throws ValidationException  When Razorpay can't say whether the link was paid.
     */
    public function releaseHold(Venue $venue, WhatsAppConversation $conversation, ?User $actor = null): string
    {
        $booking = $conversation->active_booking_id !== null ? Booking::query()->find($conversation->active_booking_id) : null;

        if ($booking !== null && $this->hasIssuedLink($booking)) {
            $paid = $this->checkLinks($booking);

            if ($paid === null) {
                throw ValidationException::withMessages([
                    'booking' => ['Couldn’t check whether the customer has paid the link. Try again in a moment.'],
                ]);
            }

            if ($paid) {
                return 'paid';
            }
        }

        DB::transaction(function () use ($venue, $conversation, $booking, $actor): void {
            if ($booking !== null) {
                $this->cancelHold($booking);
            }

            $conversation->update([
                'active_booking_id'    => null,
                'status'               => 'active',
                'last_message_at'      => now(),
                'last_message_preview' => 'Hold released',
                'last_message_sender'  => 'system',
            ]);

            $this->systemNote($conversation, $actor, 'Hold released — the court is free again.');

            WhatsAppAuditLog::log($venue->id, 'hold_released', $conversation->id, $actor?->id, $actor?->name ?? 'System', [
                'booking_id' => $booking?->id,
            ]);
        });

        return 'released';
    }

    /**
     * Create a Razorpay payment link for the held booking and send it in the chat.
     * Sending restarts the hold clock, so the customer gets the full hold to pay.
     *
     * @throws ValidationException  No live hold, window closed, or Razorpay refused.
     */
    public function sendPaymentLink(Venue $venue, WhatsAppConversation $conversation, Booking $booking, ?User $actor = null, ?int $holdMinutes = null): WhatsAppPaymentLink
    {
        if (strtoupper((string) $booking->status) !== 'PENDING'
            || $booking->reserved_until === null
            || $booking->reserved_until->isPast()) {
            throw ValidationException::withMessages(['booking' => ['The hold has lapsed. Hold the slot again, then send the link.']]);
        }

        if (! $conversation->isWindowActive()) {
            throw ValidationException::withMessages([
                'message' => ['The customer hasn’t messaged in 24 hours, so WhatsApp won’t deliver a link. Ask them to message you first.'],
            ]);
        }

        $amount = $booking->amountCharged();
        $courtName = $booking->venueCourt?->name ?? 'Court';

        try {
            $link = $this->razorpay->createPaymentLink(
                (int) round($amount * 100),
                "{$venue->name} · {$courtName} · ".$this->when($booking),
                $conversation->customer_name ?: null,
                $conversation->phone_number,
                [
                    'booking_id'      => (string) $booking->id,
                    'conversation_id' => (string) $conversation->id,
                    'source'          => 'whatsapp_desk',
                ],
            );
        } catch (\Throwable $e) {
            Log::warning("WhatsApp desk: payment link for booking {$booking->id} failed: ".$e->getMessage());

            throw ValidationException::withMessages(['payment' => ['Couldn’t create the payment link: '.$e->getMessage()]]);
        }

        $shortUrl = (string) ($link['short_url'] ?? '');
        if ($link['id'] === '' || $shortUrl === '') {
            throw ValidationException::withMessages(['payment' => ['Razorpay didn’t return a payment link. Try again.']]);
        }

        $minutes = $holdMinutes ?? self::holdMinutes();
        $reservedUntil = now()->addMinutes($minutes);

        return DB::transaction(function () use ($venue, $conversation, $booking, $actor, $link, $shortUrl, $amount, $courtName, $minutes, $reservedUntil): WhatsAppPaymentLink {
            $booking->update(['reserved_until' => $reservedUntil]);

            $paymentLink = WhatsAppPaymentLink::query()->create([
                'conversation_id'          => $conversation->id,
                'booking_id'               => $booking->id,
                'venue_id'                 => $venue->id,
                'razorpay_payment_link_id' => $link['id'],
                'short_url'                => $shortUrl,
                'amount'                   => $amount,
                'status'                   => 'issued',
                'expires_at'               => $reservedUntil,
            ]);

            $text = "{$courtName} is held for you on ".$this->when($booking).'. '
                .'Pay ₹'.number_format($amount)." within {$minutes} minutes to confirm:\n{$shortUrl}";

            $sent = $this->whatsapp->sendMessage($conversation->phone_number, $text, $this->context($conversation));

            WhatsAppMessage::query()->create([
                'conversation_id' => $conversation->id,
                'direction'       => 'outbound',
                'sender_type'     => 'partner',
                'sender_id'       => $actor?->id,
                'message_type'    => 'payment_link',
                'body'            => $text,
                'delivery_status' => $sent ? 'sent' : 'failed',
                'raw_payload'     => ['payment_link_id' => $paymentLink->id, 'short_url' => $shortUrl, 'amount' => $amount],
            ]);

            $conversation->update([
                'last_message_at'      => now(),
                'last_message_preview' => 'Payment link sent · ₹'.number_format($amount),
                'last_message_sender'  => 'partner',
            ]);

            WhatsAppAuditLog::log($venue->id, 'payment_link_sent', $conversation->id, $actor?->id, $actor?->name ?? 'Partner staff', [
                'booking_id' => $booking->id,
                'amount'     => $amount,
                'link_id'    => $link['id'],
            ]);

            return $paymentLink;
        });
    }

    /**
     * The desk took the money in person (cash / UPI to the counter).
     *
     * @throws ConflictHttpException  When the hold lapsed and someone else took the court.
     */
    public function markPaidManual(Venue $venue, WhatsAppConversation $conversation, Booking $booking, string $method, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($venue, $conversation, $booking, $method, $actor): Booking {
            $booking = $this->bookings->confirmDeskHold($booking);

            $this->ledger->collect($booking, $booking->amountCharged(), $method, $actor, null, 'WhatsApp Desk');

            WhatsAppPaymentLink::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'issued')
                ->update(['status' => 'cancelled']);

            $this->convert($venue, $conversation, $booking->refresh(), $method, $actor);

            return $booking;
        });
    }

    /**
     * A payment link for a desk booking was paid — from the Razorpay webhook, the desk's
     * "has it been paid?" check, or the hold sweep. Idempotent on the payment id.
     *
     * @return string what happened, for logs
     */
    public function settlePaidLink(Booking $booking, ?string $paymentId): string
    {
        if ($paymentId !== null && $paymentId !== '' && $booking->payments()->where('reference', $paymentId)->exists()) {
            return 'already_recorded';
        }

        $conversation = WhatsAppConversation::query()
            ->whereIn('id', WhatsAppPaymentLink::query()->where('booking_id', $booking->id)->select('conversation_id'))
            ->first();

        try {
            // Confirms a live hold, reclaims a lapsed one if the court is still free, and
            // refunds the customer automatically if it isn't — the online path's rules.
            $this->bookings->confirmReservation([(int) $booking->id], $paymentId ?: null);
        } catch (ConflictHttpException $e) {
            WhatsAppPaymentLink::query()->where('booking_id', $booking->id)->update(['status' => 'refunded']);

            if ($conversation !== null) {
                if ((int) $conversation->active_booking_id === (int) $booking->id) {
                    $conversation->update(['active_booking_id' => null, 'status' => 'needs_action']);
                }
                $this->systemNote($conversation, null,
                    'The customer paid after the hold lapsed and the court had been booked by someone else. Razorpay is refunding them automatically — message them another time.');
            }

            return 'refunded_overbooked';
        }

        WhatsAppPaymentLink::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['issued', 'expired'])
            ->update(['status' => 'paid', 'paid_at' => now()]);

        if ($conversation !== null) {
            $this->convert($conversation->venue, $conversation, $booking->refresh(), 'razorpay', null);
        }

        return 'link_paid';
    }

    /**
     * Ask Razorpay about the booking's outstanding links and settle one that's paid.
     *
     * @return bool|null true = paid (and settled), false = not paid, null = couldn't tell
     */
    public function checkLinks(Booking $booking): ?bool
    {
        $links = WhatsAppPaymentLink::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['issued', 'expired'])
            ->whereNotNull('razorpay_payment_link_id')
            ->get();

        $unknown = false;

        foreach ($links as $link) {
            try {
                $status = $this->razorpay->paymentLinkStatus((string) $link->razorpay_payment_link_id);
            } catch (\Throwable $e) {
                Log::warning("WhatsApp desk: couldn't read payment link {$link->razorpay_payment_link_id}: ".$e->getMessage());
                $unknown = true;

                continue;
            }

            if ($status['paid']) {
                $this->settlePaidLink($booking, $status['payment_id']);

                return true;
            }
        }

        return $unknown ? null : false;
    }

    /**
     * The sweep behind `whatsapp:expire-holds`: settle lapsed holds whose link was paid,
     * expire the rest, and put their chats back to "active". A hold whose link status
     * Razorpay can't confirm is left alone and asked about again next minute.
     *
     * @return array{expired: int, paid: int, unknown: int}
     */
    public function expireLapsedHolds(): array
    {
        $result = ['expired' => 0, 'paid' => 0, 'unknown' => 0];

        $lapsed = Booking::query()
            // Desk holds, and booking-bot holds (channel 'online') paid by a chat link.
            ->where(fn ($q) => $q->where('channel', 'whatsapp')
                ->orWhereIn('id', WhatsAppPaymentLink::query()->select('booking_id')))
            ->whereRaw('upper(status) = ?', ['PENDING'])
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->get();

        foreach ($lapsed as $booking) {
            if ($this->hasIssuedLink($booking)) {
                $paid = $this->checkLinks($booking);

                if ($paid === true) {
                    $result['paid']++;

                    continue;
                }

                if ($paid === null) {
                    $result['unknown']++;

                    continue;
                }
            }

            DB::transaction(function () use ($booking): void {
                // EXPIRED, with the same guards as every other lapsed hold.
                $this->bookings->releaseReservation([(int) $booking->id]);

                WhatsAppPaymentLink::query()
                    ->where('booking_id', $booking->id)
                    ->where('status', 'issued')
                    ->update(['status' => 'expired']);

                $conversation = WhatsAppConversation::query()->where('active_booking_id', $booking->id)->first();

                if ($conversation !== null) {
                    $conversation->update([
                        'active_booking_id'    => null,
                        'status'               => 'active',
                        'last_message_at'      => now(),
                        'last_message_preview' => 'Hold lapsed',
                        'last_message_sender'  => 'system',
                    ]);

                    $this->systemNote($conversation, null, 'The hold lapsed without payment — the court is free again.');

                    WhatsAppAuditLog::log((int) $conversation->venue_id, 'hold_expired', $conversation->id, null, 'System', [
                        'booking_id' => $booking->id,
                    ]);
                }
            });

            $result['expired']++;
        }

        return $result;
    }

    /** Booked: tell the customer (with their QR) and close the chat's booking loop. */
    private function convert(Venue $venue, WhatsAppConversation $conversation, Booking $booking, string $method, ?User $actor): void
    {
        $code = (string) $booking->ticket_code;
        $courtName = $booking->venueCourt?->name ?? 'Court';

        $text = "Booking confirmed ✅\n\n{$venue->name}\n{$courtName} · ".$this->when($booking)
            ."\nBooking code: {$code}\n\nShow this QR at the desk when you arrive.";

        $sent = false;
        if ($conversation->isWindowActive()) {
            $sent = $this->whatsapp->sendMedia(
                $conversation->phone_number,
                $text,
                route('ticket.qr', ['code' => $code]),
                $this->context($conversation),
            );
        }

        WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'sender_type'     => 'system',
            'sender_id'       => $actor?->id,
            'message_type'    => 'ticket_card',
            'body'            => $text,
            'delivery_status' => $sent ? 'sent' : 'failed',
            'raw_payload'     => ['ticket_code' => $code, 'booking_id' => $booking->id],
        ]);

        $conversation->update([
            'active_booking_id'    => null,
            'status'               => 'converted',
            'last_message_at'      => now(),
            'last_message_preview' => "Booked · {$courtName} · ".$this->when($booking),
            'last_message_sender'  => 'system',
        ]);

        WhatsAppIntentExtraction::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('action_state', ['suggested', 'hold_created'])
            ->update(['action_state' => 'converted']);

        // A booking-bot booking is an app-style booking the venue didn't make: tell the
        // owner, as the app checkout does. (Desk bookings are the venue's own.)
        if (! $booking->isDeskBooking()) {
            try {
                app(BookingNotifier::class)->notifyPartnerOnNewBooking($booking);
            } catch (\Throwable $e) {
                Log::warning("Owner alert for bot booking {$booking->id} failed: ".$e->getMessage());
            }

            \App\Models\WhatsAppBotSession::query()
                ->where('phone', $conversation->phone_number)
                ->where('booking_id', $booking->id)
                ->update(['state' => 'done', 'last_activity_at' => now()]);
        }

        WhatsAppAuditLog::log($venue->id, 'booking_converted', $conversation->id, $actor?->id, $actor?->name ?? 'System', [
            'booking_id' => $booking->id,
            'amount'     => $booking->amountCharged(),
            'method'     => $method,
            'delivered'  => $sent,
        ]);
    }

    /** Cancel a live hold this chat still has, so a new one can take its place. */
    private function dropLiveHold(WhatsAppConversation $conversation): void
    {
        if ($conversation->active_booking_id === null) {
            return;
        }

        $old = Booking::query()->find($conversation->active_booking_id);

        if ($old !== null) {
            $this->cancelHold($old);
        }
    }

    /** A hold the desk let go: CANCELLED, never one that carries a payment. */
    private function cancelHold(Booking $booking): void
    {
        if (strtoupper((string) $booking->status) !== 'PENDING' || trim((string) $booking->razorpay_payment_id) !== '') {
            return;
        }

        $booking->update(['status' => 'CANCELLED', 'reserved_until' => null]);

        WhatsAppPaymentLink::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'issued')
            ->update(['status' => 'cancelled']);
    }

    private function hasIssuedLink(Booking $booking): bool
    {
        return WhatsAppPaymentLink::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'issued')
            ->exists();
    }

    /** A line in the thread only staff see (never sent to the customer). */
    private function systemNote(WhatsAppConversation $conversation, ?User $actor, string $body): void
    {
        WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'sender_type'     => 'system',
            'sender_id'       => $actor?->id,
            'message_type'    => 'text',
            'body'            => $body,
            'delivery_status' => 'internal',
        ]);
    }

    /** "Sat 27 Sep, 6:00 – 7:30 PM" */
    private function when(Booking $booking): string
    {
        $date = Carbon::parse($booking->slot_date)->format('D j M');
        $start = $booking->start_time ? Carbon::parse($booking->start_time)->format('g:i') : '';
        $end = $booking->end_time ? Carbon::parse($booking->end_time)->format('g:i A') : '';

        return trim("{$date}, {$start} – {$end}", ', –');
    }

    private function context(WhatsAppConversation $conversation): MessageContext
    {
        return new MessageContext((int) $conversation->partner_id, MessageContext::SERVICE, 'desk.reply');
    }
}
