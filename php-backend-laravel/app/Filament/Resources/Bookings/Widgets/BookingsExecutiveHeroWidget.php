<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Widgets\ListSummaryWidget;
use Illuminate\Support\Facades\DB;

/**
 * Bookings list summary — the last 30 days of orders, as recorded.
 */
class BookingsExecutiveHeroWidget extends ListSummaryWidget
{
    public function getSummary(): array
    {
        $since = now()->subDays(30);
        $base = fn () => BookingResource::getEloquentQuery()->where('created_at', '>=', $since);
        $paid = fn () => $base()->whereIn(DB::raw('lower(status)'), self::PAID);

        $total = $base()->count();
        $paidCount = $paid()->count();
        $cancelled = $base()->whereIn(DB::raw('lower(status)'), self::CANCELLED)->count();
        $revenue = (float) $paid()->sum('total_amount');
        $tickets = (int) $paid()->sum('quantity');

        // Walk-ins and desk sales carry channel = 'offline'; everything else came
        // through the app or the website.
        $desk = $paid()->where('channel', 'offline')->count();

        return [
            'title' => 'Bookings',
            'window' => 'Last 30 days',
            'stats' => [
                ['label' => 'Paid bookings', 'value' => number_format($paidCount), 'sub' => number_format($total) . ' placed in all'],
                ['label' => 'Revenue', 'value' => self::inr($revenue), 'tone' => $revenue > 0 ? 'good' : null],
                ['label' => 'Average order', 'value' => $paidCount > 0 ? self::inr($revenue / $paidCount) : '—',
                    'sub' => $paidCount > 0 ? round($tickets / $paidCount, 1) . ' tickets per order' : null],
                ['label' => 'Cancelled', 'value' => self::pct($cancelled, $total), 'sub' => number_format($cancelled) . ' of ' . number_format($total),
                    'tone' => $total >= 10 && $cancelled / $total >= 0.08 ? 'warn' : null],
            ],
            'split' => [
                'label' => 'Where paid bookings came from',
                'parts' => self::parts(['Online' => $paidCount - $desk, 'Desk & walk-in' => $desk], money: false),
            ],
        ];
    }
}
