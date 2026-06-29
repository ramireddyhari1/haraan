<?php

namespace App\Filament\Resources\LiveMatches;

use App\Filament\Resources\LiveMatches\Pages\ListLiveMatches;
use App\Filament\Resources\LiveMatches\Tables\LiveMatchesTable;
use App\Filament\Concerns\ScopesToOrganization;
use App\Models\LiveMatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Read-only moderation view of player-created matches. Matches are created and
 * scored from the app; here, admins curate *reach* — promoting a high-quality
 * district match to FEATURED (visible platform-wide). Creators never choose
 * visibility, so promotion lives only here and in the admin app control.
 */
class LiveMatchResource extends Resource
{
    use ScopesToOrganization;

    protected static ?string $model = LiveMatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Match';

    protected static ?string $pluralModelLabel = 'Matches';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public static function table(Table $table): Table
    {
        return LiveMatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLiveMatches::route('/'),
        ];
    }
}
