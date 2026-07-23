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

    /** Creating/editing/deleting a venue needs the 'pricing' (listings & pricing) capability. */
    public static function canCreate(): bool
    {
        return static::canAccess() && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess() && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess() && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
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
