<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Sales pacing — the single most useful host question: "am I on track to sell out?"
 *
 * Compares how much of the inventory is sold against how much of the sale window (event
 * creation → event date) has elapsed, projects the final sell-through if the current pace
 * holds, and estimates a sell-out date. Read-only; the record is injected by the page.
 */
class EventSalesPacingWidget extends StatsOverviewWidget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected ?string $heading = 'Sales pacing';

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

        $capacity = max((int) $event->total_slots, 0);
        $available = max((int) $event->available_slots, 0);
        $sold = max($capacity - $available, 0);
        $fill = $capacity > 0 ? $sold / $capacity : 0.0;               // 0..1
        $fillPct = (int) round($fill * 100);

        // Sale window: created_at → event date. Guard missing/degenerate windows.
        $start = $event->created_at;
        $end = $event->date;
        $elapsedFrac = 0.0;
        $daysToEvent = null;
        $eventPassed = false;

        if ($start && $end) {
            $total = $start->diffInSeconds($end, false);
            $eventPassed = $end->isPast();
            $daysToEvent = (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false);
            if ($total > 0) {
                $elapsed = $start->diffInSeconds(now(), false);
                $elapsedFrac = max(0.0, min(1.0, $elapsed / $total));
            } else {
                $elapsedFrac = 1.0;
            }
        }
        $elapsedPct = (int) round($elapsedFrac * 100);

        // Projected final sell-through if the current daily pace holds.
        $projFrac = $elapsedFrac > 0 ? min($fill / $elapsedFrac, 5.0) : 0.0; // cap at 500% to avoid absurd labels
        $projPct = $capacity > 0 ? (int) round(min($projFrac, 1.0) * 100) : 0;

        // Pace verdict: sold-share vs time-share.
        if ($capacity <= 0) {
            [$verdict, $vColor, $vIcon] = ['Unlimited', 'gray', 'heroicon-m-infinity'];
        } elseif ($fillPct >= 100) {
            [$verdict, $vColor, $vIcon] = ['Sold out', 'success', 'heroicon-m-check-badge'];
        } elseif ($eventPassed) {
            [$verdict, $vColor, $vIcon] = ['Ended', 'gray', 'heroicon-m-flag'];
        } elseif ($fill >= $elapsedFrac + 0.1) {
            [$verdict, $vColor, $vIcon] = ['Ahead of pace', 'success', 'heroicon-m-rocket-launch'];
        } elseif ($fill >= $elapsedFrac - 0.05) {
            [$verdict, $vColor, $vIcon] = ['On track', 'success', 'heroicon-m-check-circle'];
        } else {
            [$verdict, $vColor, $vIcon] = ['Behind pace', 'warning', 'heroicon-m-exclamation-triangle'];
        }

        // Sell-out estimate from realised velocity (paid tickets ÷ elapsed days).
        $selloutLabel = $this->selloutEstimate($event, $sold, $available, $capacity, $eventPassed, $fillPct);

        $projColor = $projPct >= 100 ? 'success' : ($projPct >= 70 ? 'warning' : 'danger');

        return [
            Stat::make('Sold', $sold . ' / ' . ($capacity > 0 ? $capacity : '∞'))
                ->description($fillPct . '% of capacity')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($fillPct >= 80 ? 'success' : ($fillPct >= 40 ? 'primary' : 'gray')),

            Stat::make('Sale window elapsed', $elapsedPct . '%')
                ->description($daysToEvent === null ? 'No date set' : ($eventPassed ? 'Event passed' : $daysToEvent . ' days to event'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($elapsedPct >= 80 ? 'warning' : 'info'),

            Stat::make('Pace', $verdict)
                ->description($capacity > 0 ? $fillPct . '% sold vs ' . $elapsedPct . '% time' : 'No fixed capacity')
                ->descriptionIcon($vIcon)
                ->color($vColor),

            Stat::make('Projected sell-through', $capacity > 0 ? $projPct . '%' : '—')
                ->description($selloutLabel)
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color($capacity > 0 ? $projColor : 'gray'),
        ];
    }

    private function selloutEstimate(Event $event, int $sold, int $available, int $capacity, bool $eventPassed, int $fillPct): string
    {
        if ($capacity <= 0) {
            return 'Unlimited capacity';
        }
        if ($fillPct >= 100) {
            return 'Sold out 🎉';
        }
        if ($eventPassed) {
            return 'Closed at ' . $fillPct . '%';
        }

        // Realised velocity from the first paid booking to now.
        $firstPaid = Booking::where('event_id', $event->id)
            ->whereIn(\DB::raw('lower(status)'), self::PAID)
            ->min('created_at');

        if (! $firstPaid) {
            return 'No sales yet — needs a push';
        }

        $days = max(\Illuminate\Support\Carbon::parse($firstPaid)->diffInDays(now(), false), 0.5);
        $perDay = $sold / $days;
        if ($perDay <= 0) {
            return 'Stalled — no recent sales';
        }

        $daysToSellout = (int) ceil($available / $perDay);
        $selloutDate = now()->addDays($daysToSellout);

        if ($event->date && $selloutDate->gt($event->date)) {
            return 'Won\'t sell out at this pace';
        }

        return 'Sell-out ~' . $selloutDate->format('d M') . ' (' . round($perDay, 1) . '/day)';
    }
}
