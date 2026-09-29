<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Concerns;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\Booking;
use App\Support\FormatsSummaries;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * For the GameHub pages that render summary panels (x-summary-panel).
 *
 * The same pages serve both consoles. In /control they read the whole
 * platform; in /partner every query is narrowed to the partner's own venues
 * through VenueResource's scoped query (which also honours the branch switcher),
 * so a venue owner never sees another business's bookings or rules.
 *
 * The page's view is filament.clusters.game-hub.summary-page, which draws
 * whatever getPanels() returns.
 */
trait SummarisesVenues
{
    use FormatsSummaries;

    /** Paid-for booking statuses, compared lower-cased (status casing is mixed). */
    protected const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    protected const CANCELLED = ['cancelled', 'canceled', 'refunded'];

    /**
     * @return list<array<string, mixed>> summary panels, drawn top to bottom
     */
    abstract public function getPanels(): array;

    protected static function inPartnerConsole(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'partner';
    }

    /** The partner (owner) whose data this is, or null in /control. */
    protected static function partnerId(): ?int
    {
        return static::inPartnerConsole() ? auth()->user()?->effectivePartnerId() : null;
    }

    /** Venue ids in view: a subquery in /partner, null (= every venue) in /control. */
    protected static function venueIds(): ?Builder
    {
        return static::inPartnerConsole() ? VenueResource::getEloquentQuery()->select('venues.id') : null;
    }

    /** Apply the venue scope to any query with a venue_id column. */
    protected static function ownVenues(Builder $query, string $column = 'venue_id'): Builder
    {
        $ids = static::venueIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /** Venue (court/slot) bookings in view. */
    protected static function venueBookings(): Builder
    {
        return static::ownVenues(Booking::query()->where('booking_type', 'venue'));
    }
}
