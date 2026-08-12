<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Support\MessageContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The receipt: "we've got your money, here's what's left to pay."
 *
 * Separate from {@see BookingNotifier} because it answers a different question.
 * The booking confirmation says *you're coming*; this says *you've paid*, and the
 * two come apart the moment money arrives in more than one piece — a deposit at
 * booking and the balance at the desk, a partner taking cash against an online
 * order, a top-up on a venue slot. Each of those is a payment event the customer
 * should see, and none of them is a new ticket.
 *
 * Deliberately quiet in the two cases where a receipt is noise rather than
 * service: a zero-rupee booking, and a refund (which is a negative ledger row and
 * gets its own conversation with the customer, not a "payment received").
 *
 * Best-effort throughout, and dispatched AFTER the response like the ticket is —
 * a receipt must never be the reason a desk payment fails to record.
 */
final class PaymentNotifier
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * Queue the receipt to go out once the response is flushed.
     *
     * Takes the payment rather than the booking so the message can quote the
     * amount that just arrived — after a partial payment the booking's
     * `amount_paid` is the running total, which is not what the customer just did.
     */
    public static function dispatch(?BookingPayment $payment): void
    {
        // Refunds and zero rows are ledger bookkeeping, not something to announce.
        if ($payment === null || (float) $payment->amount <= 0.0) {
            return;
        }

        $id = (int) $payment->id;

        \Illuminate\Support\defer(function () use ($id): void {
            $fresh = BookingPayment::query()->find($id);

            if ($fresh !== null) {
                app(self::class)->notify($fresh);
            }
        });
    }

    public function notify(BookingPayment $payment): void
    {
        try {
            $booking = Booking::query()->with(['event', 'venue', 'user'])->find($payment->booking_id);

            if ($booking === null) {
                return;
            }

            $phone = $this->phone($booking);

            if ($phone === null) {
                return;
            }

            $route = $this->templates->resolve('payment.success', 'whatsapp', $phone);

            // No approved template and no open window means the send would be
            // rejected. Skipping is right here in a way it isn't for a ticket: the
            // customer keeps their booking confirmation either way, and a receipt
            // isn't worth a guaranteed-failed send on every payment taken.
            if ($route['mode'] === TemplateResolver::MODE_BLOCKED) {
                return;
            }

            $title = (string) ($booking->event?->title ?? $booking->venue?->name ?? 'your booking');
            $paid = $this->money((float) $payment->amount);
            $reference = (string) ($booking->ticket_code ?: $booking->id);

            $context = MessageContext::forBooking($booking, MessageContext::UTILITY, 'payment.success');

            // Deliberately no outstanding balance. Haraan takes payment in full at
            // checkout, so a "balance due" line would read "Rs.0" on essentially
            // every receipt — a number that answers a question nobody asked, on the
            // one message that should be short enough to read at a glance.
            $sent = $route['mode'] === TemplateResolver::MODE_TEMPLATE
                ? $this->whatsapp->sendTemplate(
                    $phone,
                    (string) $route['name'],
                    [$title, $this->when($booking), $paid, $reference],
                    $context,
                    (string) $route['language'],
                )
                : $this->whatsapp->sendMessage(
                    $phone,
                    "We've received your payment for {$title}.\n{$this->when($booking)}\n\n"
                        . "Amount: Rs.{$paid}\nBooking ID: {$reference}\n\nThank you — Haraan.",
                    $context,
                );

            if (! $sent) {
                Log::info("Payment receipt not delivered for booking {$booking->id}.");
            }
        } catch (Throwable $e) {
            Log::warning('Payment receipt failed: ' . $e->getMessage());
        }
    }

    /**
     * When the thing they paid for happens — "12 Sep, 7:00 PM".
     *
     * The receipt names the date because a booking code alone doesn't tell anyone
     * WHICH booking they just paid for, and someone holding tickets to three things
     * shouldn't have to open a link to find out.
     *
     * Kept to one line and no newlines: this goes into a template parameter, and
     * WhatsApp rejects the whole message if one contains a line break.
     */
    private function when(Booking $booking): string
    {
        if ($booking->event !== null && $booking->event->date !== null) {
            $time = trim((string) $booking->event->time);

            return $booking->event->date->format('d M') . ($time !== '' ? ', ' . $time : '');
        }

        $parts = array_filter([
            $booking->slot_date?->format('d M'),
            $booking->start_time ? substr((string) $booking->start_time, 0, 5) : null,
        ]);

        // Never empty: an empty template parameter is rejected outright.
        return implode(', ', $parts) ?: 'See your booking';
    }

    /** Whole rupees where it's whole rupees — "500" reads as money, "500.00" reads as a system. */
    private function money(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** The same ladder the ticket uses, so the receipt reaches whoever the ticket did. */
    private function phone(Booking $booking): ?string
    {
        foreach ([$booking->attendee_phone, $booking->user->phone ?? null, $booking->guest_phone] as $candidate) {
            $digits = (string) preg_replace('/[^0-9]/', '', (string) $candidate);

            if (strlen($digits) >= 10) {
                return $digits;
            }
        }

        return null;
    }
}
