<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Per-event host analytics. All figures are computed from real booking data
 * for the single event bound to the page; no placeholder numbers.
 *
 * Booking statuses are stored inconsistently (CONFIRMED / confirmed / PAID /
 * cancelled / REFUNDED …) so every filter matches case-insensitively.
 */
class EventAnalyticsStatsWidget extends StatsOverviewWidget
{
    /** Injected by the page via InteractsWithRecord::getWidgetData(). */
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    /** Statuses that represent money actually earned. */
    private const PAID = ['confirmed', 'paid', 'completed'];

    /** Statuses that represent lost / reversed revenue. */
    private const LOST = ['cancelled', 'refunded', 'failed'];

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

        $paid = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID);

        $revenue   = (float) (clone $paid)->sum('total_amount');
        $attendees = (int) (clone $paid)->sum('quantity');
        $orders    = (int) (clone $paid)->count();
        $checkedIn = (int) (clone $paid)->sum('checked_in_count');
        $discount  = (float) (clone $paid)->sum('discount');
        $coupons   = (int) (clone $paid)->whereNotNull('coupon_code')
            ->where('coupon_code', '!=', '')->count();

        $lost = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::LOST);
        $lostCount = (int) (clone $lost)->count();
        $lostValue = (float) (clone $lost)->sum('total_amount');

        $capacity  = max((int) $event->total_slots, 0);
        $available = max((int) $event->available_slots, 0);
        $sold      = max($capacity - $available, 0);
        $fill      = $capacity > 0 ? (int) round($sold / $capacity * 100) : 0;

        $avgPerAttendee = $attendees > 0 ? $revenue / $attendees : 0.0;

        $views      = max((int) $event->views, 0);
        $conversion = $views > 0 ? round($orders / $views * 100, 2) : 0.0;

        $showUp   = $attendees > 0 ? (int) round($checkedIn / $attendees * 100) : 0;
        $noShows  = max($attendees - $checkedIn, 0);

        [$repeatFans, $repeatPct, $repeatIds] = $this->repeatAttendees($event);

        // Split this event's revenue between returning fans and first-timers.
        $repeatRevenue = $repeatIds === [] ? 0.0 : (float) (clone $paid)
            ->whereIn('user_id', $repeatIds)
            ->sum('total_amount');
        $newRevenue = max($revenue - $repeatRevenue, 0.0);
        $repeatRevPct = $revenue > 0 ? (int) round($repeatRevenue / $revenue * 100) : 0;

        return [
            Stat::make('Event Views', number_format($views))
                ->description('Detail-page opens')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),

            Stat::make('Conversion Rate', $conversion . '%')
                ->description('Views → paid bookings')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($conversion >= 5 ? 'success' : ($conversion > 0 ? 'warning' : 'gray')),

            Stat::make('Total Revenue', $this->money($revenue))
                ->description("$orders paid " . str('order')->plural($orders))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Avg per Attendee', $this->money($avgPerAttendee))
                ->description('Revenue ÷ tickets sold')
                ->descriptionIcon('heroicon-m-user')
                ->color('primary'),

            Stat::make('Attendees', number_format($attendees))
                ->description('Tickets across paid orders')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Checked In', number_format($checkedIn))
                ->description($showUp . '% show-up rate')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($showUp >= 80 ? 'success' : ($showUp > 0 ? 'warning' : 'gray')),

            Stat::make('No-shows', number_format($noShows))
                ->description('Booked but not arrived')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($noShows > 0 ? 'danger' : 'gray'),

            Stat::make('Repeat Fans', number_format($repeatFans))
                ->description($repeatPct . '% booked a past event of yours')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($repeatPct >= 30 ? 'success' : ($repeatFans > 0 ? 'info' : 'gray')),

            Stat::make('Repeat-fan Revenue', $this->money($repeatRevenue))
                ->description($repeatRevPct . '% of revenue from returning fans')
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color($repeatRevenue > 0 ? 'success' : 'gray'),

            Stat::make('New-fan Revenue', $this->money($newRevenue))
                ->description((100 - $repeatRevPct) . '% of revenue from first-timers')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),

            Stat::make('Bookings', $sold . ' / ' . $capacity)
                ->description($fill . '% filled')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($fill >= 100 ? 'success' : ($fill >= 50 ? 'primary' : 'gray')),

            Stat::make('Sell-through', $fill . '%')
                ->description('Seats sold vs capacity')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($fill >= 80 ? 'danger' : ($fill >= 50 ? 'warning' : 'info')),

            Stat::make('Discounts Given', $this->money($discount))
                ->description($coupons . ' coupon ' . str('redemption')->plural($coupons))
                ->descriptionIcon('heroicon-m-tag')
                ->color($discount > 0 ? 'warning' : 'gray'),

            Stat::make('Refunds / Cancelled', number_format($lostCount))
                ->description($this->money($lostValue) . ' reversed')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($lostCount > 0 ? 'danger' : 'gray'),

            Stat::make('Days to Event', $this->daysToEvent($event))
                ->description($event->date?->format('D, d M Y') ?? 'Date TBD')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
        ];
    }

    /**
     * "Returning fans" for this event: of the unique attendees who paid for this
     * event, how many have also paid for a *different* event by the same host
     * before. Falls back to the owning organization when a host (partner) isn't
     * set. Returns [count, percentageOfThisEventsFanbase, repeatUserIds].
     *
     * @return array{0: int, 1: int, 2: list<int>}
     */
    private function repeatAttendees(Event $event): array
    {
        // The unique people who paid for THIS event.
        $fans = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->all();

        if ($fans === []) {
            return [0, 0, []];
        }

        // This host's other events (by partner, else by organization).
        $otherEvents = Event::where('id', '!=', $event->id)
            ->when(
                $event->partner_id !== null,
                fn ($q) => $q->where('partner_id', $event->partner_id),
                fn ($q) => $event->organization_id !== null
                    ? $q->where('organization_id', $event->organization_id)
                    : $q->whereRaw('1 = 0'), // no host key → no cross-event history
            )
            ->pluck('id');

        if ($otherEvents->isEmpty()) {
            return [0, 0, []];
        }

        // Of this event's fans, which ones paid for one of the host's past events.
        $repeatIds = Booking::whereIn('event_id', $otherEvents)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->whereIn('user_id', $fans)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->values()
            ->all();

        $repeat = count($repeatIds);
        $pct = count($fans) > 0 ? (int) round($repeat / count($fans) * 100) : 0;

        return [$repeat, $pct, $repeatIds];
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount);
    }

    private function daysToEvent(Event $event): string
    {
        if (! $event->date) {
            return '—';
        }

        $days = now()->startOfDay()->diffInDays($event->date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0  => abs((int) $days) . 'd ago',
            $days === 0 => 'Today',
            default    => (string) (int) $days,
        };
    }
}
