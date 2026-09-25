<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Widgets;

use App\Models\BookingPayment;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * The Venue 360 command hero: today's sheet, this week's shape, and the money
 * that actually moved — for one venue.
 *
 * Everything here is counted from rows. Occupancy is stated as "booked court-hours
 * against court-hours offered today", and the denominator is spelled out in the
 * caption, because an occupancy percentage whose basis is hidden is the kind of
 * number people stop trusting the first time it disagrees with the day sheet.
 */
class VenueCommandHeroWidget extends Widget
{
    /** Injected by ViewVenue via the record-bound widget data. */
    public ?Venue $record = null;

    protected string $view = 'filament.resources.venues.widgets.venue-command-hero';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Money statuses. Prod has mixed casing on `status`, so compare lowered. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function getTelemetry(): array
    {
        $venue = $this->record;

        if (! $venue instanceof Venue) {
            return ['ready' => false];
        }

        $today = now()->startOfDay();
        $bookingIds = $venue->bookings()->select('bookings.id');

        // --- Today -----------------------------------------------------------
        $todayBookings = (int) $venue->bookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDate('slot_date', $today->toDateString())
            ->count();

        $offeredToday = $this->courtHoursOfferedOn($venue, $today);
        $occupancy = $offeredToday > 0
            ? (int) round(min(100, $todayBookings / $offeredToday * 100))
            : 0;

        // --- Money actually collected, from the ledger ------------------------
        $collectedToday = (float) BookingPayment::whereIn('booking_id', $bookingIds)
            ->whereDate('collected_at', $today->toDateString())
            ->sum('amount');

        $collected30d = (float) BookingPayment::whereIn('booking_id', $bookingIds)
            ->where('collected_at', '>=', $today->copy()->subDays(30))
            ->sum('amount');

        // Outstanding on bookings that have not been played yet.
        $upcoming = $venue->bookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDate('slot_date', '>=', $today->toDateString())
            ->get(['id', 'total_amount', 'amount_paid']);

        $balanceDue = (float) $upcoming->sum(
            static fn ($b): float => max(0.0, (float) $b->total_amount - (float) $b->amount_paid),
        );
        $owingCount = $upcoming->filter(
            static fn ($b): bool => (float) $b->total_amount - (float) $b->amount_paid > 0.009,
        )->count();

        // --- The week ahead ---------------------------------------------------
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->addDays($i);
            $offered = $this->courtHoursOfferedOn($venue, $date);

            $booked = (int) $venue->bookings()
                ->whereIn(DB::raw('lower(status)'), self::PAID)
                ->whereDate('slot_date', $date->toDateString())
                ->count();

            $closed = ! $venue->isOpenOn($date)
                || $venue->blockedDates()->whereDate('date', $date->toDateString())->exists();

            $days[] = [
                'label'   => $i === 0 ? 'Today' : ($i === 1 ? 'Tmrw' : $date->format('D')),
                'date'    => $date->format('d M'),
                'booked'  => $booked,
                'offered' => $offered,
                'closed'  => $closed,
                'percent' => ($closed || $offered === 0) ? 0 : (int) round(min(100, $booked / $offered * 100)),
            ];
        }

        return [
            'ready'          => true,
            'name'           => $venue->name,
            'branch'         => $venue->branchName(),
            'city'           => (string) ($venue->city ?: $venue->location ?: ''),
            'sports'         => $venue->sportsList(),
            'onSale'         => $venue->is_active && $venue->is_bookable,
            'statusLabel'    => $this->statusLabel($venue),
            'courtsActive'   => (int) $venue->courts()->where('is_active', true)->count(),
            'courtsTotal'    => (int) $venue->courts()->count(),
            'todayBookings'  => $todayBookings,
            'offeredToday'   => $offeredToday,
            'occupancy'      => $occupancy,
            'openToday'      => $venue->isOpenOn($today),
            'hoursToday'     => $venue->hoursForWeekday($today->format('D')),
            'collectedToday' => $collectedToday,
            'collected30d'   => $collected30d,
            'balanceDue'     => $balanceDue,
            'owingCount'     => $owingCount,
            'days'           => $days,
        ];
    }

    /**
     * Court-hours on sale for one date: active courts × available weekly slots for
     * that weekday, minus nothing. It is the honest denominator for occupancy —
     * the same grid the day-bookings screen paints.
     */
    private function courtHoursOfferedOn(Venue $venue, CarbonInterface $date): int
    {
        if (! $venue->isOpenOn($date)) {
            return 0;
        }

        $courts = (int) $venue->courts()->where('is_active', true)->count();

        if ($courts === 0) {
            return 0;
        }

        $slots = (int) $venue->slots()
            ->where('is_available', true)
            ->where('day', $date->format('l'))
            ->count();

        return $courts * $slots;
    }

    private function statusLabel(Venue $venue): string
    {
        if (! $venue->is_active) {
            return 'Listing hidden';
        }

        if (! $venue->is_bookable) {
            return 'Visible, not bookable';
        }

        return 'On sale';
    }
}
