<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Widgets;

use App\Filament\Widgets\ListSummaryWidget;
use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Venues list summary: how many venues are live and taking bookings, their
 * courts, where they are, and which ones are busiest in the coming week.
 */
class VenuesExecutiveHeroWidget extends ListSummaryWidget
{
    public function getSummary(): array
    {
        $total = Venue::count();
        $live = Venue::where('is_active', true)->where('status', 'published')->count();
        $bookable = Venue::where('is_active', true)->where('status', 'published')->where('is_bookable', true)->count();
        $courts = VenueCourt::where('is_active', true)->count();
        $peakCourts = VenueCourt::where('is_active', true)->where('peak_price', '>', 0)->count();

        $byCity = Venue::where('is_active', true)
            ->selectRaw("coalesce(nullif(city, ''), 'No city set') as c, COUNT(*) as n")
            ->groupBy('c')
            ->pluck('n', 'c')
            ->all();

        $today = Carbon::today();
        $busiest = Booking::query()
            ->where('booking_type', 'venue')
            ->whereNotNull('venue_id')
            ->whereNotIn(DB::raw('lower(status)'), [...self::CANCELLED, 'expired'])
            ->whereBetween('slot_date', [$today->toDateString(), $today->copy()->addDays(6)->toDateString()])
            ->selectRaw('venue_id, COUNT(*) as n')
            ->groupBy('venue_id')
            ->orderByDesc('n')
            ->limit(5)
            ->pluck('n', 'venue_id');
        $names = Venue::whereIn('id', $busiest->keys())->get(['id', 'name', 'city'])->keyBy('id');

        return [
            'title' => 'Venues',
            'stats' => [
                ['label' => 'Live venues', 'value' => number_format($live), 'sub' => number_format($total) . ' in all'],
                ['label' => 'Taking bookings', 'value' => number_format($bookable), 'sub' => $live > $bookable ? number_format($live - $bookable) . ' live but not bookable' : null],
                ['label' => 'Active courts', 'value' => number_format($courts)],
                ['label' => 'Peak pricing', 'value' => number_format($peakCourts), 'sub' => $courts > 0 ? 'of ' . number_format($courts) . ' courts' : null],
            ],
            'split' => [
                'label' => 'Active venues by city',
                'parts' => self::parts($byCity, money: false),
            ],
            'list' => [
                'title' => 'Busiest in the next 7 days',
                'rows' => $busiest->map(fn ($n, $id) => [
                    'primary' => $names[$id]->name ?? "Venue #{$id}",
                    'secondary' => $names[$id]->city ?? null,
                    'trailing' => number_format((int) $n) . ' ' . str('slot')->plural((int) $n) . ' booked',
                ])->values()->all(),
                'empty' => 'No slots booked at any venue for the coming week.',
            ],
        ];
    }
}
