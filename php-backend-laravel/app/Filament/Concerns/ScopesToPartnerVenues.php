<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\Booking;
use App\Models\VenueSlot;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Venue-lane twin of [ScopesToPartnerEvents]. Keeps GameHub widgets in a venue
 * partner's lane: venues own by partner_id, while slots and bookings own through
 * their venue (venue_id → venue.partner_id). Super-admins and /control stay
 * unrestricted; scoping only bites inside the /partner console.
 */
trait ScopesToPartnerVenues
{
    /** Venues visible in the current panel (partner-scoped in /partner). */
    protected function scopedVenueQuery(): Builder
    {
        return VenueResource::getEloquentQuery();
    }

    /** Should venue reads be hard-scoped to this partner's own venues? */
    protected function shouldScopeToPartner(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'partner'
            && ! auth()->user()?->isSuperAdmin();
    }

    /** Slots, constrained to the partner's own venues in /partner. */
    protected function scopedSlotQuery(): Builder
    {
        $query = VenueSlot::query();

        if ($this->shouldScopeToPartner()) {
            $query->whereIn('venue_id', VenueResource::getEloquentQuery()->select('venues.id'));
        }

        return $query;
    }

    /** Venue bookings, constrained to the partner's own venues in /partner. */
    protected function scopedVenueBookingQuery(): Builder
    {
        $query = Booking::query()->whereNotNull('venue_id');

        if ($this->shouldScopeToPartner()) {
            $query->whereIn('venue_id', VenueResource::getEloquentQuery()->select('venues.id'));
        }

        return $query;
    }
}
