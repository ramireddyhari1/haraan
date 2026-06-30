<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Finance\Widgets;

use App\Models\Booking;
use App\Models\Payout;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $revenue = (float) Booking::whereIn('status', ['confirmed', 'paid', 'completed'])->sum('total_amount');
        $pendingPayouts = (float) Payout::where('status', 'pending')->sum('amount');
        $pendingCount = Payout::where('status', 'pending')->count();
        $processed = (float) Payout::where('status', 'processed')->sum('amount');
        $paidBookings = Booking::whereIn('status', ['confirmed', 'paid', 'completed'])->count();
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
