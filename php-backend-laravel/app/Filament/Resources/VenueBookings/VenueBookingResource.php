<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBookings;

use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Resources\VenueBookings\Pages\ListVenueBookings;
use App\Filament\Resources\VenueBookings\Tables\VenueBookingsTable;
use App\Models\Booking;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Court bookings, and — the reason this exists separately from BookingResource —
 * who still owes money on them.
 *
 * BookingResource lives in the Events cluster and owns rows through `event_id`,
 * so a venue partner sees nothing in it. This is its venue-lane twin: same model,
 * owned through `venue_id`, and built around the balance-due question that the
 * payment ledger made answerable for the first time.
 *
 * Read-and-act, not create: new bookings go through the Day bookings grid, which
 * runs the court-hour conflict engine. Creating a row here would bypass it.
 *
 * @see \App\Services\BookingLedger
 */
class VenueBookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    // Two resources share the Booking model, so this one needs its own slug.
    protected static ?string $slug = 'venue-bookings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Bookings & payments';

    protected static ?string $title = 'Bookings & payments';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'ticket_code';

    /**
     * Bookings carry no partner_id, so own them through the venue — VenueResource's
     * query is already partner-scoped (and per-staff venue-assignment scoped), so
     * these inherit both. /control sees every venue booking.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = Booking::query()->where('booking_type', 'venue');

        $user = auth()->user();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && $user !== null && ! $user->isSuperAdmin()) {
            $query->whereIn('venue_id', VenueResource::getEloquentQuery()->select('venues.id'));
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->canManage('gamehub') ?? false)
            && $user->hasPartnerPermission('bookings');
    }

    /** Rows are created by the booking flow, never typed in here. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    /** How much money is outstanding — the number worth a badge in the nav. */
    public static function getNavigationBadge(): ?string
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return null;
        }

        $count = static::outstanding()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Bookings with a balance still due';
    }

    /**
     * Live bookings that took some money (or none) and still owe the rest.
     * Refunded rows are excluded — they are not a chase target.
     */
    public static function outstanding(): Builder
    {
        return static::getEloquentQuery()
            ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
            ->whereNotIn('payment_status', ['refunded', 'part_refunded'])
            ->whereRaw('coalesce(amount_paid, 0) < total_amount');
    }

    public static function table(Table $table): Table
    {
        return VenueBookingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenueBookings::route('/'),
        ];
    }
}
