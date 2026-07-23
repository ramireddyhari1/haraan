<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Refund & cancellation analytics for a single event — count, rate, and the revenue actually
 * reversed, from real booking statuses. Refund *reasons* aren't captured yet, so they are not
 * shown (no fabricated breakdown); add a reason field to surface them later.
 */
class EventRefundWidget extends StatsOverviewWidget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Refunds & cancellations';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $event = $this->record;
        if (! $event) {
            return [];
        }

        $for = fn (array $statuses) => Booking::where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), $statuses);

        $paidCount = (int) $for(self::PAID)->count();

        $refunded = $for(['refunded']);
        $refundCount = (int) (clone $refunded)->count();
        $refundValue = (float) (clone $refunded)->sum('total_amount');

        $cancelled = $for(['cancelled', 'canceled']);
        $cancelCount = (int) (clone $cancelled)->count();

        // Rate is refunds against all orders that were ever paid (paid now + refunded).
        $denom = $paidCount + $refundCount;
        $refundRate = $denom > 0 ? round($refundCount / $denom * 100, 1) : 0.0;

        return [
            Stat::make('Refunds', number_format($refundCount))
                ->description('Tickets refunded')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($refundCount > 0 ? 'danger' : 'gray'),

            Stat::make('Refund rate', $refundRate . '%')
                ->description('of all paid orders')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($refundRate <= 5 ? 'success' : ($refundRate <= 15 ? 'warning' : 'danger')),

            Stat::make('Revenue reversed', '₹' . number_format($refundValue))
                ->description('Returned to customers')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($refundValue > 0 ? 'warning' : 'gray'),

            Stat::make('Cancellations', number_format($cancelCount))
                ->description('Cancelled before payment / by admin')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color($cancelCount > 0 ? 'warning' : 'gray'),
        ];
    }
}
