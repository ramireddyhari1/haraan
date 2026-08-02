<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBlocks;

use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Resources\VenueBlocks\Pages\CreateVenueBlock;
use App\Filament\Resources\VenueBlocks\Pages\EditVenueBlock;
use App\Filament\Resources\VenueBlocks\Pages\ListVenueBlocks;
use App\Filament\Resources\VenueBlocks\Schemas\VenueBlockForm;
use App\Filament\Resources\VenueBlocks\Tables\VenueBlocksTable;
use App\Models\VenueBlock;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Time taken off the market: maintenance, holidays, academy batches, tournament
 * holds, private hires.
 *
 * Rows written here are read by
 * {@see \App\Services\BookingService::assertCourtHourFree()}, so a block created
 * on this page immediately stops the app, the desk and the API from selling that
 * court-hour. Until this existed the engine was live but nothing fed it.
 *
 * Blocking needs the same 'pricing' capability as changing a rate — both take
 * inventory off sale, so a front-desk person should not be able to do either.
 */
class VenueBlockResource extends Resource
{
    protected static ?string $model = VenueBlock::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Blocked time';

    protected static ?string $modelLabel = 'block';

    protected static ?string $pluralModelLabel = 'blocked time';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    /** Blocks own through their venue — VenueResource's query is already scoped. */
    public static function getEloquentQuery(): Builder
    {
        $query = VenueBlock::query();

        $user = auth()->user();

        if (Filament::getCurrentPanel()?->getId() === 'partner' && $user !== null && ! $user->isSuperAdmin()) {
            $query->whereIn('venue_id', VenueResource::getEloquentQuery()->select('venues.id'));
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canAccess() && (auth()->user()?->hasPartnerPermission('pricing') ?? false);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canCreate();
    }

    /** Blocks in force right now — the thing worth a nav badge. */
    public static function getNavigationBadge(): ?string
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return null;
        }

        $count = static::getEloquentQuery()
            ->whereDate('ends_on', '>=', today())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return VenueBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VenueBlocksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenueBlocks::route('/'),
            'create' => CreateVenueBlock::route('/create'),
            'edit' => EditVenueBlock::route('/{record}/edit'),
        ];
    }
}
