<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Clusters\Events\Pages\TicketCheckIn;
use App\Filament\Clusters\GameHub\Pages\VenueBookings;
use App\Filament\Pages\Partner\PartnerEarnings;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The partner home's action bar — the two or three things an operator opens most,
 * one tap away, so the dashboard is a launchpad and not just a report. Lane-aware:
 * event organisers get create-event / bookings / check-in, venue owners get
 * add-venue / day-bookings / manage-venues.
 */
class PartnerQuickActionsWidget extends Widget
{
    use \App\Filament\Concerns\ScopesToPartnerEvents;
    use \App\Filament\Concerns\ScopesToPartnerVenues;

    protected string $view = 'filament.widgets.partner.quick-actions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 0;

    // No skeleton on this strip, so render eagerly (see the isLazy gotcha).
    protected static bool $isLazy = false;

    /** Statuses that represent money actually collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    private function laneBookings(): Builder
    {
        return $this->isEventLane() ? $this->scopedBookingQuery() : $this->scopedVenueBookingQuery();
    }

    /**
     * Live figures for the greeting hero: what's landed *today*, plus this-week
     * momentum vs the previous seven days. Lane-aware and partner-scoped, so it's
     * always the operator's own money.
     *
     * @return array{revenue:float, count:int, weekDelta:int|null, isEvent:bool}
     */
    public function getToday(): array
    {
        $startToday = now()->startOfDay();

        $today = (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $startToday)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as rev, COUNT(*) as cnt')
            ->first();

        // This week vs the seven days before it — one momentum read people love.
        $weekStart = now()->startOfDay()->subDays(6);
        $prevStart = $weekStart->copy()->subDays(7);
        $rows = (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $prevStart)
            ->get(['total_amount', 'created_at']);

        $cur = 0.0;
        $prev = 0.0;
        foreach ($rows as $row) {
            if ($row->created_at >= $weekStart) {
                $cur += (float) $row->total_amount;
            } else {
                $prev += (float) $row->total_amount;
            }
        }

        return [
            'revenue' => (float) ($today->rev ?? 0),
            'count' => (int) ($today->cnt ?? 0),
            'weekDelta' => $prev > 0 ? (int) round((($cur - $prev) / $prev) * 100) : null,
            'isEvent' => $this->isEventLane(),
        ];
    }

    /**
     * @return array<int, array{label:string, icon:string, url:string, primary?:bool}>
     */
    public function getActions(): array
    {
        if ($this->isEventLane()) {
            return array_values(array_filter([
                EventResource::canCreate() ? [
                    'label' => 'Create event',
                    'icon' => 'heroicon-o-plus-circle',
                    'url' => EventResource::getUrl('create'),
                    'primary' => true,
                ] : null,
                BookingResource::canAccess() ? [
                    'label' => 'View bookings',
                    'icon' => 'heroicon-o-calendar-days',
                    'url' => BookingResource::getUrl(),
                ] : null,
                TicketCheckIn::canAccess() ? [
                    'label' => 'Check-in',
                    'icon' => 'heroicon-o-qr-code',
                    'url' => TicketCheckIn::getUrl(),
                ] : null,
            ]));
        }

        return array_values(array_filter([
            VenueResource::canCreate() ? [
                'label' => 'Add venue',
                'icon' => 'heroicon-o-plus-circle',
                'url' => VenueResource::getUrl('create'),
                'primary' => true,
            ] : null,
            VenueBookings::canAccess() ? [
                'label' => 'Day bookings',
                'icon' => 'heroicon-o-calendar-days',
                'url' => VenueBookings::getUrl(),
            ] : null,
            VenueResource::canAccess() ? [
                'label' => 'Manage venues',
                'icon' => 'heroicon-o-map-pin',
                'url' => VenueResource::getUrl(),
            ] : null,
        ]));
    }

    public function getGreeting(): string
    {
        $name = auth()->user()?->name ?: 'there';
        $hour = (int) now()->format('G');
        $part = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        return "$part, " . str($name)->before(' ');
    }

    /**
     * The organiser's soonest upcoming published event, with sell-through — the
     * "what's next" context that turns the hero into a command bar. Event lane only.
     *
     * @return array{id:int, title:string, when:string, date:?string, pct:?int, sold:int, total:int, poster:?string, url:string, checkInUrl:?string}|null
     */
    public function getNextEvent(): ?array
    {
        if (! $this->isEventLane()) {
            return null;
        }

        $e = $this->scopedEventQuery()
            ->whereRaw('lower(status) = ?', ['published'])
            ->where('date', '>=', now()->startOfDay())
            ->orderBy('date')
            ->first();

        if (! $e) {
            return null;
        }

        $total = max(0, (int) $e->total_slots);
        $sold = $total > 0 ? max(0, $total - max(0, (int) $e->available_slots)) : 0;
        $days = (int) now()->startOfDay()->diffInDays($e->date, false);

        return [
            'id' => (int) $e->id,
            'title' => (string) $e->title,
            'when' => $days <= 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days"),
            'date' => $e->date ? \Illuminate\Support\Carbon::parse($e->date)->format('D, d M') : null,
            'pct' => $total > 0 ? (int) round($sold / $total * 100) : null,
            'sold' => $sold,
            'total' => $total,
            'poster' => method_exists($e, 'heroImageUrl') ? $e->heroImageUrl() : null,
            'url' => EventResource::getUrl('edit', ['record' => $e->id]),
            'checkInUrl' => TicketCheckIn::canAccess() ? TicketCheckIn::getUrl() : null,
        ];
    }

    /**
     * The single most urgent thing that needs the operator right now, or null when
     * all's calm — a slim actionable ribbon at the very top. Sellout risk leads
     * (time-sensitive), then money awaiting settlement. Reuses the same signals as
     * the "Needs you" row, distilled to the one that matters most.
     *
     * @return array{icon:string, tone:string, text:string, cta:string, url:string}|null
     */
    public function getAlert(): ?array
    {
        // 1) Sellout risk — a soon, nearly-full event is the most time-sensitive nudge.
        if ($this->isEventLane()) {
            $risk = $this->scopedEventQuery()
                ->whereRaw('lower(status) = ?', ['published'])
                ->where('date', '>=', now()->startOfDay())
                ->where('total_slots', '>', 0)
                ->get(['id', 'title', 'date', 'total_slots', 'available_slots'])
                ->filter(function (Event $e): bool {
                    $sold = $e->total_slots - max(0, (int) $e->available_slots);
                    return $e->available_slots > 0 && ($sold / $e->total_slots) >= 0.85;
                })
                ->sortBy('date')
                ->first();

            if ($risk) {
                $left = max(0, (int) $risk->available_slots);
                $pct = (int) round(($risk->total_slots - $left) / $risk->total_slots * 100);

                return [
                    'icon' => 'heroicon-o-fire', 'tone' => 'hot',
                    'text' => "“{$risk->title}” is {$pct}% sold — only {$left} left",
                    'cta' => 'Review', 'url' => EventResource::getUrl(),
                ];
            }
        }

        // 2) Money collected but not yet paid out.
        $pending = (float) (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDoesntHave('payout', fn (Builder $q) => $q->whereIn(DB::raw('lower(status)'), ['paid', 'processed', 'completed']))
            ->sum('total_amount');

        if ($pending > 0) {
            return [
                'icon' => 'heroicon-o-banknotes', 'tone' => 'info',
                'text' => $this->inr($pending) . ' collected is awaiting settlement',
                'cta' => 'Earnings', 'url' => PartnerEarnings::getUrl(),
            ];
        }

        return null;
    }

    /**
     * A slim "today at a glance" strip below the hero: money in, volume, check-ins,
     * and (event lane) views today — the live snapshot an operator scans first.
     * Lane-aware + partner-scoped.
     *
     * @return array<int, array{icon:string, value:string, label:string, sub:string}>
     */
    public function getTodayStrip(): array
    {
        $start = now()->startOfDay();

        $today = (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $start)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as rev, COUNT(*) as cnt')
            ->first();

        $checkins = (int) (clone $this->laneBookings())
            ->where('checked_in_at', '>=', $start)
            ->sum(DB::raw('COALESCE(checked_in_count, 1)'));

        $tiles = [
            ['icon' => 'heroicon-o-banknotes', 'accent' => 'green', 'value' => $this->inr((float) ($today->rev ?? 0)), 'label' => 'Earned', 'sub' => 'today'],
            ['icon' => 'heroicon-o-ticket', 'accent' => 'blue', 'value' => number_format((int) ($today->cnt ?? 0)), 'label' => $this->isEventLane() ? 'Tickets' : 'Bookings', 'sub' => 'today'],
            ['icon' => 'heroicon-o-check-badge', 'accent' => 'indigo', 'value' => number_format($checkins), 'label' => 'Check-ins', 'sub' => 'today'],
        ];

        if ($this->isEventLane()) {
            $views = (int) (clone $this->scopedEventViewQuery())
                ->where('created_at', '>=', $start)
                ->count();
            $tiles[] = ['icon' => 'heroicon-o-eye', 'accent' => 'violet', 'value' => number_format($views), 'label' => 'Views', 'sub' => 'today'];
        } else {
            $newWk = (int) (clone $this->laneBookings())
                ->where('created_at', '>=', now()->startOfDay()->subDays(6))
                ->count();
            $tiles[] = ['icon' => 'heroicon-o-calendar-days', 'accent' => 'violet', 'value' => number_format($newWk), 'label' => 'New', 'sub' => '7 days'];
        }

        return $tiles;
    }

    /** ₹18,42,900 — Indian grouping. */
    private function inr(float $n): string
    {
        $n = (int) round($n);
        $str = (string) abs($n);
        if (strlen($str) <= 3) {
            return '₹' . $str;
        }
        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return '₹' . $rest . ',' . $last3;
    }
}
