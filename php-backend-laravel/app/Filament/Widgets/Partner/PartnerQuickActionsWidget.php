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
use App\Support\BusinessClock;
use App\Support\PartnerLane;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
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
     * Live figures for the greeting hero: the money that landed today, and how
     * that compares — vs yesterday on a branch (a desk's rhythm is daily), vs
     * the previous seven days for an organiser (ticket sales run in weeks).
     *
     * Branch lanes read the payment ledger, exactly like VenueTodayWidget: a
     * ₹4,400 slot with a ₹500 advance has put ₹500 in the drawer, not ₹4,400.
     *
     * @return array{revenue:float, count:int, delta:int|null, deltaLabel:string, isEvent:bool}
     */
    public function getToday(): array
    {
        if (! $this->isEventLane()) {
            $today = Carbon::today();
            $collected = fn (Carbon $day): float => (float) DB::table('booking_payments')
                ->whereIn('booking_id', $this->scopedVenueBookingQuery()->select('bookings.id'))
                ->whereDate('collected_at', $day)
                ->sum('amount');

            $rev = $collected($today);
            $prev = $collected($today->copy()->subDay());

            return [
                'revenue' => $rev,
                'count' => (int) (clone $this->scopedVenueBookingQuery())
                    ->whereNotIn(DB::raw('lower(status)'), ['cancelled', 'canceled', 'expired', 'refunded'])
                    ->whereDate('slot_date', $today)
                    ->count(),
                'delta' => $prev > 0 ? (int) round((($rev - $prev) / $prev) * 100) : null,
                'deltaLabel' => 'vs yesterday',
                'isEvent' => false,
            ];
        }

        $startToday = now()->startOfDay();

        $today = (clone $this->laneBookings())
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->where('created_at', '>=', $startToday)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as rev, COUNT(*) as cnt')
            ->first();

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
            'delta' => $prev > 0 ? (int) round((($cur - $prev) / $prev) * 100) : null,
            'deltaLabel' => 'this week',
            'isEvent' => true,
        ];
    }

    /**
     * What the drawn scene shows: the partner's kind of place, lit by the real
     * local hour (the app clock is UTC — see BusinessClock).
     *
     * @return array{kind:string, phase:string}
     */
    public function getScene(): array
    {
        $h = (int) BusinessClock::now()->format('G');

        return [
            'kind' => match (auth()->user()?->partnerLane()) {
                PartnerLane::GAMEHUB => 'turf',
                PartnerLane::CAFE => 'cafe',
                default => 'stage',
            },
            'phase' => match (true) {
                $h >= 5 && $h < 10 => 'morning',
                $h >= 10 && $h < 17 => 'day',
                $h >= 17 && $h < 19 => 'evening',
                default => 'night',
            },
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
        $hour = (int) BusinessClock::now()->format('G');
        $part = $hour >= 4 && $hour < 12 ? 'Good morning' : ($hour >= 12 && $hour < 17 ? 'Good afternoon' : 'Good evening');

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
            // Open the per-event analytics dashboard (not the edit form).
            'url' => EventResource::getUrl('analytics', ['record' => $e->id]),
            // Open the scanner pre-locked to this event (?event=<id>).
            'checkInUrl' => TicketCheckIn::canAccess() ? TicketCheckIn::getUrl(['event' => $e->id]) : null,
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
     * The quieter facts under the hero figure, as one sentence of true numbers
     * rather than a grid of boxed counters. Only facts with something to say
     * are kept; a zero-views line on a fresh account is noise.
     *
     * @return array<int, string>
     */
    public function getFacts(): array
    {
        $start = now()->startOfDay();
        $facts = [];

        $checkins = (int) (clone $this->laneBookings())
            ->where('checked_in_at', '>=', $start)
            ->sum(DB::raw('COALESCE(checked_in_count, 1)'));

        if ($this->isEventLane()) {
            if ($checkins > 0) {
                $facts[] = number_format($checkins) . ' checked in';
            }
            $views = (int) (clone $this->scopedEventViewQuery())->where('created_at', '>=', $start)->count();
            if ($views > 0) {
                $facts[] = number_format($views) . ' page ' . str('view')->plural($views);
            }

            return $facts;
        }

        $newWk = (int) (clone $this->laneBookings())
            ->where('created_at', '>=', now()->startOfDay()->subDays(6))
            ->count();
        if ($newWk > 0) {
            $facts[] = number_format($newWk) . ' new this week';
        }

        return $facts;
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
