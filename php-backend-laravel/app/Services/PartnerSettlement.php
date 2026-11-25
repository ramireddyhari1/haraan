<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What a partner is owed, computed for any partner id.
 *
 * The partner's own Payouts page derives this from the *auth-scoped* resource
 * queries, which only works for the logged-in partner. Admin needs the same
 * arithmetic for someone else, so the rules live here once and both sides call
 * it — otherwise the number a partner sees and the number an admin settles
 * against could drift apart.
 *
 * Money out is counted from two ledgers: the per-booking `payouts` rows (the
 * older reconciliation ledger) and `payout_batches` (the partner-facing
 * settlements admin creates). A batch that is still processing is *reserved*,
 * not yet paid — it comes off the available balance so the same rupees are
 * never batched twice.
 */
final class PartnerSettlement
{
    /** Booking statuses that mean the money was collected. */
    public const PAID_BOOKING = ['confirmed', 'paid', 'completed', 'checked_in'];

    /** Per-booking payout statuses that mean the partner was paid. */
    public const SETTLED_PAYOUT = ['paid', 'processed', 'completed'];

    /** Bookings belonging to a partner — their events OR their venues. */
    public function bookings(int $partnerId): Builder
    {
        $eventIds = Event::query()->select('id')->where('partner_id', $partnerId);
        $venueIds = Venue::query()->select('id')->where('partner_id', $partnerId);

        return Booking::query()->where(fn (Builder $q) => $q
            ->whereIn('event_id', $eventIds)
            ->orWhereIn('venue_id', $venueIds));
    }

    /**
     * What the partner has earned from attendees, all time: the ticket/slot value, less what
     * the platform keeps from it (`host_deduction` — host-paid platform/gateway fees on events,
     * Pulse commission on online venue bookings, both set in /control → Platform rules).
     */
    public function collected(int $partnerId): float
    {
        return $this->collectedBreakdown($partnerId)['net'];
    }

    /**
     * The payout basis, in the order a partner reads it: what came in through Haraan
     * (`gross`, the venue's share of it — tax excluded), less Haraan's commission / host
     * fees (`commission`), leaving what's owed (`net`). Same rule as
     * {@see Booking::partnerPayable()}: online bookings less the advance balance taken at
     * the venue; desk bookings (walk-in, WhatsApp) only for money paid through a Haraan QR
     * or link — their cash never reached Haraan.
     *
     * `tax` is the online tax customers paid on top (Haraan's, never the venue's): gross +
     * tax is what Razorpay collected on these bookings.
     *
     * @return array{gross: float, tax: float, commission: float, net: float}
     */
    public function collectedBreakdown(int $partnerId): array
    {
        $paid = $this->bookings($partnerId)->whereIn(DB::raw('lower(status)'), self::PAID_BOOKING);
        $desk = Booking::DESK_CHANNELS;
        $notDesk = fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->whereNull('channel')
            ->orWhereNotIn(DB::raw('lower(channel)'), $desk));

        $online = (clone $paid)->where($notDesk);
        // Slot money only: the online fees (convenience, gateway, named) and tax are
        // Haraan's — never paid out — so the partner's gross is the slot value online.
        $onlineGross = (float) (clone $online)->sum(DB::raw(Booking::SLOT_VALUE_SQL)) - (float) (clone $online)->sum('pay_at_venue');
        $onlineCommission = (float) (clone $online)->sum('host_deduction');
        $tax = 0.0;

        $deskRows = (clone $paid)->whereIn(DB::raw('lower(channel)'), $desk);
        $deskGross = (float) \App\Models\BookingPayment::query()
            ->whereIn('booking_id', (clone $deskRows)->select('bookings.id'))
            ->whereNotIn(DB::raw('lower(method)'), Booking::DESK_PAYMENT_METHODS)
            ->sum('amount');
        $deskCommission = (float) (clone $deskRows)->sum('host_deduction');

        $gross = max(0.0, $onlineGross + $deskGross);
        $commission = max(0.0, $onlineCommission + $deskCommission);

        return [
            'gross' => round($gross, 2),
            'tax' => round(max(0.0, $tax), 2),
            'commission' => round($commission, 2),
            'net' => round(max(0.0, $gross - $commission), 2),
        ];
    }

    /** The platform's share of this partner's paid bookings (host-paid fees + commission). */
    public function platformShare(int $partnerId): float
    {
        return (float) $this->bookings($partnerId)
            ->whereIn(DB::raw('lower(status)'), self::PAID_BOOKING)
            ->sum('host_deduction');
    }

    /**
     * Money no longer available for settlement. A batch's whole gross leaves the
     * available balance — the net payout to the partner's bank plus everything it
     * deducted (commission, its GST, TDS, TCS).
     */
    public function settled(int $partnerId): float
    {
        $perBooking = (float) Payout::query()
            ->whereIn('booking_id', $this->bookings($partnerId)->select('bookings.id'))
            ->whereIn(DB::raw('lower(status)'), self::SETTLED_PAYOUT)
            ->sum('amount');

        $batched = (float) PayoutBatch::query()
            ->where('partner_id', $partnerId)
            ->whereIn(DB::raw('lower(status)'), PayoutBatch::PAID)
            ->sum(DB::raw('COALESCE(gross_amount, amount + commission_amount)'));

        return $perBooking + $batched;
    }

    /** Batched but not yet paid — reserved, so it can't be batched again. */
    public function inFlight(int $partnerId): float
    {
        return (float) PayoutBatch::query()
            ->where('partner_id', $partnerId)
            ->whereIn(DB::raw('lower(status)'), ['processing', 'pending'])
            ->sum(DB::raw('COALESCE(gross_amount, amount + commission_amount)'));
    }

    /**
     * What actually reached (or is on its way to) the partner's bank, and what settlements
     * kept back as commission, GST and tax. The balance maths above works on gross; these
     * are the figures a partner should see as "in your bank" and "on the way".
     *
     * @return array{paidOut: float, inFlightNet: float, withheld: float}
     */
    public function netFigures(int $partnerId): array
    {
        $perBooking = (float) Payout::query()
            ->whereIn('booking_id', $this->bookings($partnerId)->select('bookings.id'))
            ->whereIn(DB::raw('lower(status)'), self::SETTLED_PAYOUT)
            ->sum('amount');
        $paid = PayoutBatch::query()->where('partner_id', $partnerId)->whereIn(DB::raw('lower(status)'), PayoutBatch::PAID);
        $open = PayoutBatch::query()->where('partner_id', $partnerId)->whereIn(DB::raw('lower(status)'), ['processing', 'pending']);

        return [
            'paidOut' => $perBooking + (float) (clone $paid)->sum('amount'),
            'inFlightNet' => (float) (clone $open)->sum('amount'),
            'withheld' => (float) (clone $paid)->sum(DB::raw('COALESCE(gross_amount, amount + commission_amount) - amount')),
        ];
    }

    /** Collected, minus what's paid, minus what's already in a batch. */
    public function available(int $partnerId): float
    {
        return max($this->collected($partnerId) - $this->settled($partnerId) - $this->inFlight($partnerId), 0);
    }

    /**
     * Every figure at once, for a balance card.
     *
     * @return array{collected: float, settled: float, inFlight: float, available: float}
     */
    public function summary(int $partnerId): array
    {
        $collected = $this->collected($partnerId);
        $settled = $this->settled($partnerId);
        $inFlight = $this->inFlight($partnerId);

        return [
            'collected' => $collected,
            'settled' => $settled,
            'inFlight' => $inFlight,
            'available' => max($collected - $settled - $inFlight, 0),
        ];
    }

    /** ₹1,84,290 — Indian grouping, whole rupees. */
    public static function inr(float $amount): string
    {
        $n = (int) round($amount);
        $sign = $n < 0 ? '-' : '';
        $s = (string) abs($n);

        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s = $rest . ',' . $last3;
        }

        return $sign . '₹' . $s;
    }
}
