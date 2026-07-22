<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Filament\Concerns\ScopesToOrganization;
use App\Filament\Resources\Events\EventResource;
use App\Models\Booking;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    // Alias the org-scoping query so /control keeps its organization scoping; the
    // partner console overrides it below (bookings have no partner_id column).
    use ScopesToOrganization {
        getEloquentQuery as protected scopedByOrgQuery;
    }

    /**
     * Bookings carry no partner_id, so the generic partner_id scoping would hide
     * every row from a partner. Own them through the event instead: EventResource's
     * query is already scoped to the partner (and to any per-staff event
     * assignment), so bookings inherit both. /control still scopes by organization.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && $user !== null && ! $user->isSuperAdmin()) {
            return Booking::query()
                ->whereIn('event_id', EventResource::getEloquentQuery()->select('events.id'));
        }

        return static::scopedByOrgQuery();
    }

    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $cluster = \App\Filament\Clusters\Events\EventsCluster::class;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->canManage('events') ?? false) && $user->hasPartnerPermission('bookings');
    }

    // Bookings have no name, so search by id / customer / coupon and render a useful title.
    protected static ?string $recordTitleAttribute = 'id';

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'user.name', 'coupon_code'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return 'Booking #' . $record->id . ($record->user?->name ? ' · ' . $record->user->name : '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'For' => $record->event?->title ?? $record->venue?->name,
            'Status' => $record->status ? ucfirst(strtolower((string) $record->status)) : null,
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['user', 'event', 'venue']);
    }

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }
}
