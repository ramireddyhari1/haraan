<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Resources\Events\EventResource;
use App\Models\Booking;
use App\Models\EventView;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps the Events-cluster overview widgets in a partner's lane.
 *
 * The KPI/list/chart widgets summarise events and their bookings with raw
 * Event:: / Booking:: queries, which would leak the whole platform's numbers
 * into the /partner console. These helpers route those reads through the same
 * scope the resource tables use:
 *
 *   - scopedEventQuery()   → EventResource::getEloquentQuery(), already
 *     partner-scoped (partner_id) in /partner and org-scoped in /control, so the
 *     numbers always match the table below them.
 *   - scopedBookingQuery() → bookings, constrained to the partner's own events
 *     in the /partner console (bookings have no partner_id — ownership runs
 *     through event.partner_id). Super-admins and /control are left unscoped.
 */
trait ScopesToPartnerEvents
{
    /** Events visible in the current panel (partner-scoped in /partner). */
    protected function scopedEventQuery(): Builder
    {
        return EventResource::getEloquentQuery();
    }

    /** Bookings visible in the current panel — the partner's own event bookings in /partner. */
    protected function scopedBookingQuery(): Builder
    {
        $query = Booking::query();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && ! auth()->user()?->isSuperAdmin()) {
            // Own the booking through its event: only rows whose event belongs to
            // this partner. A subquery (not a pluck) keeps it to one SQL round-trip.
            $query->whereIn('event_id', EventResource::getEloquentQuery()->select('events.id'));
        }

        return $query;
    }

    /**
     * Event-detail views (event_views) visible in the current panel. Ownership runs
     * through event.partner_id, exactly like bookings — a view has no partner_id of
     * its own. Powers the partner dashboard's page-views / conversion funnel.
     */
    protected function scopedEventViewQuery(): Builder
    {
        $query = EventView::query();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && ! auth()->user()?->isSuperAdmin()) {
            $query->whereIn('event_id', EventResource::getEloquentQuery()->select('events.id'));
        }

        return $query;
    }
}
