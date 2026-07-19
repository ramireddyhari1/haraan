<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Partner;

use App\Filament\Clusters\Events\Pages\TicketCheckIn;
use App\Filament\Clusters\GameHub\Pages\VenueBookings;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Venues\VenueResource;
use Filament\Widgets\Widget;

/**
 * The partner home's action bar — the two or three things an operator opens most,
 * one tap away, so the dashboard is a launchpad and not just a report. Lane-aware:
 * event organisers get create-event / bookings / check-in, venue owners get
 * add-venue / day-bookings / manage-venues.
 */
class PartnerQuickActionsWidget extends Widget
{
    protected string $view = 'filament.widgets.partner.quick-actions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 0;

    // No skeleton on this strip, so render eagerly (see the isLazy gotcha).
    protected static bool $isLazy = false;

    private function isEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
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
