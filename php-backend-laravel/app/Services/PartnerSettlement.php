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

    /** Everything collected from attendees for this partner, all time. */
    public function collected(int $partnerId): float
    {
        return (float) $this->bookings($partnerId)
            ->whereIn(DB::raw('lower(status)'), self::PAID_BOOKING)
            ->sum('total_amount');
    }

    /** Money that has actually reached the partner (both ledgers). */
    public function settled(int $partnerId): float
    {
        $perBooking = (float) Payout::query()
            ->whereIn('booking_id', $this->bookings($partnerId)->select('bookings.id'))
            ->whereIn(DB::raw('lower(status)'), self::SETTLED_PAYOUT)
            ->sum('amount');

        $batched = (float) PayoutBatch::query()
            ->where('partner_id', $partnerId)
            ->whereIn(DB::raw('lower(status)'), PayoutBatch::PAID)
            ->sum('amount');

        return $perBooking + $batched;
    }

    /** Batched but not yet paid — reserved, so it can't be batched again. */
    public function inFlight(int $partnerId): float
    {
        return (float) PayoutBatch::query()
            ->where('partner_id', $partnerId)
            ->whereIn(DB::raw('lower(status)'), ['processing', 'pending'])
            ->sum('amount');
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
