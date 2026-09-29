<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist\Widgets;

use App\Filament\Widgets\ListSummaryWidget;
use App\Models\Booking;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

/**
 * Waitlist summary: who is waiting, the offers out right now, and what the
 * last 30 days of offers turned into — including the money from waitlist
 * entries that became bookings.
 */
class WaitlistExecutiveHeroWidget extends ListSummaryWidget
{
    public function getSummary(): array
    {
        $since = now()->subDays(30);
        $status = fn (string $s) => WaitlistEntry::where('status', $s);

        $waiting = $status(WaitlistEntry::STATUS_WAITING)->count();
        $offered = $status(WaitlistEntry::STATUS_OFFERED)->count();
        $converted = $status(WaitlistEntry::STATUS_CONVERTED)->where('updated_at', '>=', $since)->count();
        $lapsed = $status(WaitlistEntry::STATUS_EXPIRED)->where('updated_at', '>=', $since)->count();

        $won = (float) Booking::query()
            ->whereIn('id', $status(WaitlistEntry::STATUS_CONVERTED)
                ->where('updated_at', '>=', $since)
                ->whereNotNull('converted_booking_id')
                ->select('converted_booking_id'))
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->sum('total_amount');

        $queue = WaitlistEntry::with(['venue:id,name', 'user:id,name'])
            ->where('status', WaitlistEntry::STATUS_WAITING)
            ->orderBy('wanted_on')
            ->orderBy('start_time')
            ->orderBy('created_at')
            ->limit(4)
            ->get();

        return [
            'title' => 'Waitlist',
            'stats' => [
                ['label' => 'Waiting', 'value' => number_format($waiting)],
                ['label' => 'Offers out', 'value' => number_format($offered), 'sub' => 'a freed slot, awaiting reply'],
                ['label' => 'Turned into bookings', 'value' => number_format($converted), 'sub' => 'last 30 days · ' . self::pct($converted, $converted + $lapsed) . ' of settled offers'],
                ['label' => 'Booked from the list', 'value' => self::inr($won), 'sub' => 'last 30 days', 'tone' => $won > 0 ? 'good' : null],
            ],
            'list' => [
                'title' => 'Next in line',
                'rows' => $queue->map(fn (WaitlistEntry $e): array => [
                    'primary' => $e->user?->name ?? $e->guest_name ?? 'Guest',
                    'secondary' => $e->venue?->name,
                    'trailing' => collect([
                        $e->wanted_on?->format('j M'),
                        $e->start_time ? substr((string) $e->start_time, 0, 5) : null,
                    ])->filter()->join(' · ') ?: '—',
                ])->all(),
                'empty' => 'Nobody is waiting for a slot.',
            ],
        ];
    }
}
