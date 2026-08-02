<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place `bookings.amount_paid` and `bookings.payment_status` are ever
 * written.
 *
 * Both columns are derived from the booking_payments ledger, so letting a caller
 * set them directly is how they drift. Everything goes through collect() or
 * refund(); the parent booking is recomputed inside the same transaction.
 *
 * The invariant this class exists to hold:
 *
 *     bookings.amount_paid === SUM(booking_payments.amount)
 *
 * {@see \Tests\Feature\BookingLedgerTest} asserts it across every booking after
 * every write path, so a future direct-write gets caught by the suite rather
 * than by a partner asking why their settlement is short.
 */
class BookingLedger
{
    public function __construct(private readonly ShiftService $shifts)
    {
    }

    /**
     * Record money coming in — a Razorpay capture, cash at the desk, a UPI
     * transfer, an advance against a balance.
     *
     * @param  string  $method  cash | upi | card | online | wallet | adjustment
     * @param  User|null  $collectedBy  the staff member who took it; null for gateway money
     */
    public function collect(
        Booking $booking,
        float $amount,
        string $method = 'cash',
        ?User $collectedBy = null,
        ?string $reference = null,
        ?string $note = null,
    ): BookingPayment {
        $payment = $this->record($booking, abs($amount), $method, $collectedBy, $reference, $note);

        // The receipt, deferred until after the response. Kept to one line and one
        // collaborator on purpose: this class's job is the amount_paid invariant,
        // and messaging must never be able to fail a payment being recorded.
        PaymentNotifier::dispatch($payment);

        return $payment;
    }

    /**
     * Record money going back out. Stored as a negative row rather than a
     * separate table, so amount_paid stays a single SUM and a partial refund
     * needs no new concept.
     */
    public function refund(
        Booking $booking,
        float $amount,
        string $method = 'online',
        ?User $collectedBy = null,
        ?string $reference = null,
        ?string $note = null,
    ): BookingPayment {
        return $this->record($booking, -abs($amount), $method, $collectedBy, $reference, $note);
    }

    /** Write one signed row and bring the parent booking back in line. */
    private function record(
        Booking $booking,
        float $amount,
        string $method,
        ?User $collectedBy,
        ?string $reference,
        ?string $note,
    ): BookingPayment {
        return DB::transaction(function () use ($booking, $amount, $method, $collectedBy, $reference, $note): BookingPayment {
            $payment = BookingPayment::query()->create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'method' => $method,
                'collected_by' => $collectedBy?->id,
                'shift_session_id' => $this->shiftFor($booking, $method, $collectedBy),
                'reference' => $reference,
                'note' => $note,
                'collected_at' => now(),
            ]);

            $this->recompute($booking);

            return $payment;
        });
    }

    /**
     * The shift this rupee belongs to, or null.
     *
     * Only over-the-counter money joins a drawer: gateway payments have no person
     * standing behind them, and a venueless booking (an event order) has no desk.
     * The shift **auto-opens** if the staff member hasn't started one — requiring a
     * deliberate "start shift" would mean the day someone forgets, the cash is
     * unattributed and the whole control is worthless.
     */
    private function shiftFor(Booking $booking, string $method, ?User $collectedBy): ?int
    {
        if ($collectedBy === null || $booking->venue_id === null) {
            return null;
        }

        if (! in_array($method, BookingPayment::CASH_METHODS, true)) {
            return null;
        }

        return $this->shifts->current($collectedBy, (int) $booking->venue_id)->id;
    }

    /**
     * Recompute amount_paid + payment_status from the ledger.
     *
     * Safe to call at any time — it is pure derivation, so it doubles as the
     * repair routine if rows are ever edited out of band.
     */
    public function recompute(Booking $booking): Booking
    {
        $rows = BookingPayment::query()->where('booking_id', $booking->id);

        $paid = (float) (clone $rows)->sum('amount');
        $hasRefund = (clone $rows)->where('amount', '<', 0)->exists();

        $booking->forceFill([
            'amount_paid' => round($paid, 2),
            'payment_status' => $this->statusFor($paid, (float) $booking->total_amount, $hasRefund),
        ])->save();

        return $booking;
    }

    /**
     * Derivation table. Refund states win over the plain ones so a fully
     * refunded booking never reads as merely "unpaid" — the distinction matters
     * to both the customer and the settlement report.
     */
    public function statusFor(float $paid, float $total, bool $hasRefund): string
    {
        if ($hasRefund) {
            return $paid <= 0.0 ? 'refunded' : 'part_refunded';
        }

        if ($paid <= 0.0) {
            return 'unpaid';
        }

        return $paid + 0.001 >= $total ? 'paid' : 'partial';
    }
}
