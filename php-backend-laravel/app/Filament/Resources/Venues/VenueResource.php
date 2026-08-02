<?php

namespace App\Filament\Resources\Venues;

use App\Filament\Resources\Venues\Pages\CreateVenue;
use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Filament\Resources\Venues\Pages\ListVenues;
use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Filament\Resources\Venues\Tables\VenuesTable;
use App\Filament\Concerns\ScopesToOrganization;
use App\Models\Venue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VenueResource extends Resource
{
    use ScopesToOrganization;

    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        // Any operational desk staff can see the venues list (they need it to work
        // day bookings); mutations are gated separately below.
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    /** True while the request is being served by the partner console. */
    protected static function isPartnerPanel(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'partner';
    }

    /**
     * Venues come into existence on the admin side only.
     *
     * A listing carries decisions that are not the tenant's to make — which partner
     * owns it, which organisation unit it rolls up to, whether it is featured, where
     * it sorts. Haraan creates the venue in /control and assigns the owner there
     * (Venue → Visibility & ownership → Owner / partner); the partner then manages
     * everything about the venue they were given. Deleting is admin-only for the
     * same reason, and because a venue with bookings against it must never vanish
     * from under them.
     *
     * Elsewhere (the /control panel) this still needs the 'pricing' capability, so
     * a limited desk person cannot create or remove listings either.
     */
    public static function canCreate(): bool
    {
        if (static::isPartnerPanel()) {
            return false;
        }

        return static::canAccess() && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess() && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (static::isPartnerPanel()) {
            return false;
        }

        return static::canAccess() && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
    }

    public static function canDeleteAny(): bool
    {
        return ! static::isPartnerPanel()
            && static::canAccess()
            && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'city', 'address'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter(['City' => $record->city]);
    }

    public static function form(Schema $schema): Schema
    {
        return VenueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VenuesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Venues\RelationManagers\CourtsRelationManager::class,
            \App\Filament\Resources\Venues\RelationManagers\SlotsRelationManager::class,
            \App\Filament\Resources\Venues\RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenues::route('/'),
            'create' => CreateVenue::route('/create'),
            'edit' => EditVenue::route('/{record}/edit'),
        ];
    }
}
