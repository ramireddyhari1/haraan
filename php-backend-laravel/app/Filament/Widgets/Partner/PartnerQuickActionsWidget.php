<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Clusters\Events\Pages\TicketCheckIn;
use App\Filament\Clusters\GameHub\Pages\VenueBookings;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
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
}
