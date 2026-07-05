<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Finance\Widgets;

use App\Models\Booking;
use App\Models\Payout;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FinanceStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    /** Booking statuses that represent money actually earned (case-insensitive). */
    private const PAID = ['confirmed', 'paid', 'completed'];

    protected function getStats(): array
    {
        // lower(status) so uppercase 'CONFIRMED' bookings (the DB default) are
        // counted — a bare whereIn on mixed-case data under-reports revenue.
        $revenue = (float) Booking::whereIn(DB::raw('lower(status)'), self::PAID)->sum('total_amount');
        $pendingPayouts = (float) Payout::where('status', 'pending')->sum('amount');
        $pendingCount = Payout::where('status', 'pending')->count();
        $processed = (float) Payout::where('status', 'processed')->sum('amount');
        $paidBookings = Booking::whereIn(DB::raw('lower(status)'), self::PAID)->count();
        $avgOrder = $paidBookings > 0 ? $revenue / $paidBookings : 0.0;

        return [
            Stat::make('Revenue (confirmed)', '₹' . number_format($revenue))
                ->description('Across confirmed/paid bookings')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Pending Payouts', '₹' . number_format($pendingPayouts))
                ->description("$pendingCount awaiting processing")
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'gray'),
            Stat::make('Settled Payouts', '₹' . number_format($processed))
                ->description('Processed to partners')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
            Stat::make('Paid Bookings', (string) $paidBookings)
                ->description('Avg order ₹' . number_format($avgOrder))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
        ];
    }
}
