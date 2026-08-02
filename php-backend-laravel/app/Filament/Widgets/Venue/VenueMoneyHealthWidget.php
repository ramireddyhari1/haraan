<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Venue;

use App\Filament\Widgets\Venue\Concerns\ScopesToVenueLane;
use Illuminate\Support\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * The leaks, in rupees.
 *
 * Pending payments is the one that pays for the software: every booking that
 * took an advance and never settled the balance. Before the payment ledger this
 * number could not be computed at all — a CONFIRMED booking was assumed paid —
 * which is precisely why venues chase balances on WhatsApp from memory.
 */
class VenueMoneyHealthWidget extends StatsOverviewWidget
{
    use ScopesToVenueLane;

    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $due = $this->balanceDue();
        $refunds = $this->refundsOut();
        [$growth, $thisMonth, $lastMonth] = $this->monthGrowth();
        $cancelled = $this->cancelledThisMonth();

        return [
            Stat::make('Pending payments', $this->inr($due['amount']))
                ->description($due['count'] === 0
                    ? 'Every booking is settled'
                    : sprintf('%d booking%s with a balance due', $due['count'], $due['count'] === 1 ? '' : 's'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($due['amount'] > 0 ? 'warning' : 'success'),

            Stat::make('Refunds', $this->inr($refunds['amount']))
                ->description($refunds['count'] === 0
                    ? 'None this month'
                    : sprintf('%d refunded this month', $refunds['count']))
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($refunds['amount'] > 0 ? 'danger' : 'gray'),

            Stat::make('Revenue growth', $growth === null
                    ? '—'
                    : sprintf('%s%s%%', $growth >= 0 ? '+' : '', $growth))
                ->description($growth === null
                    ? 'Needs a full month to compare'
                    : sprintf('%s this month vs %s last', $this->inr($thisMonth), $this->inr($lastMonth)))
                ->descriptionIcon($growth !== null && $growth < 0
                    ? 'heroicon-m-arrow-trending-down'
                    : 'heroicon-m-arrow-trending-up')
                ->color(match (true) {
                    $growth === null => 'gray',
                    $growth < 0 => 'danger',
                    default => 'success',
                }),

            Stat::make('Cancellations', (string) $cancelled['count'])
                ->description($cancelled['count'] === 0
                    ? 'None this month'
                    : sprintf('%s of slot value lost', $this->inr($cancelled['amount'])))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($cancelled['count'] > 0 ? 'warning' : 'success'),
        ];
    }

    /**
     * Money owed on bookings that are still live.
     *
     * Computed in SQL rather than by walking models — this is a dashboard tile,
     * and the chase list itself lives on the Bookings page.
     *
     * @return array{amount: float, count: int}
     */
    private function balanceDue(): array
    {
        $rows = (clone $this->liveBookings())
            ->whereNotIn('payment_status', ['refunded', 'part_refunded'])
            ->whereRaw('coalesce(amount_paid, 0) < total_amount')
            ->get(['total_amount', 'amount_paid']);

        return [
            'amount' => (float) $rows->sum(fn ($b): float => max(0.0, (float) $b->total_amount - (float) $b->amount_paid)),
            'count' => $rows->count(),
        ];
    }

    /**
     * Refunds paid out this month — negative ledger rows, reported positive.
     *
     * @return array{amount: float, count: int}
     */
    private function refundsOut(): array
    {
        $rows = DB::table('booking_payments')
            ->whereIn('booking_id', $this->bookings()->select('bookings.id'))
            ->where('amount', '<', 0)
            ->where('collected_at', '>=', Carbon::now()->startOfMonth())
            ->get(['amount']);

        return [
            'amount' => abs((float) $rows->sum('amount')),
            'count' => $rows->count(),
        ];
    }

    /**
     * This month's collections against last month's, from the ledger.
     *
     * @return array{0: float|null, 1: float, 2: float}
     */
    private function monthGrowth(): array
    {
        $collected = fn (Carbon $from, Carbon $to): float => (float) DB::table('booking_payments')
            ->whereIn('booking_id', $this->bookings()->select('bookings.id'))
            ->where('amount', '>', 0)
            ->whereBetween('collected_at', [$from, $to])
            ->sum('amount');

        $startThis = Carbon::now()->startOfMonth();
        $startLast = $startThis->copy()->subMonth();

        $thisMonth = $collected($startThis, Carbon::now());
        $lastMonth = $collected($startLast, $startThis->copy()->subSecond());

        return [$this->growth($thisMonth, $lastMonth), $thisMonth, $lastMonth];
    }

    /** @return array{amount: float, count: int} */
    private function cancelledThisMonth(): array
    {
        $rows = $this->bookings()
            ->whereIn(DB::raw('lower(status)'), ['cancelled', 'canceled'])
            ->where('updated_at', '>=', Carbon::now()->startOfMonth())
            ->get(['total_amount']);

        return [
            'amount' => (float) $rows->sum('total_amount'),
            'count' => $rows->count(),
        ];
    }
}
