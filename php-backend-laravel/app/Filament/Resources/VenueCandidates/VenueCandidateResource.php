<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueCandidates;

use App\Filament\Resources\VenueCandidates\Pages\ListVenueCandidates;
use App\Filament\Resources\VenueCandidates\Tables\VenueCandidatesTable;
use App\Models\VenueCandidate;
use App\Models\VenueInterest;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * /control → GameHub → Discovery. Sports venues found on Google that are not on Haraan
 * yet, each marked with whether Playo lists it too. Nothing here is public: "List it"
 * makes a venue in the Listed state (a website page with booking off, where players ask
 * to book and the owner can claim it); Reject keeps it out for good, so the next search
 * doesn't bring it back.
 *
 * What is searched, and where, is in Discovery settings.
 */
class VenueCandidateResource extends Resource
{
    protected static ?string $model = VenueCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Discovery';

    protected static ?string $modelLabel = 'found venue';

    protected static ?string $pluralModelLabel = 'Venue discovery';

    protected static ?string $slug = 'venue-discovery';

    protected static ?int $navigationSort = 9;

    public static function getEloquentQuery(): Builder
    {
        return VenueCandidate::query()
            ->with('venue:id,name,status')
            ->select('venue_candidates.*')
            ->selectSub(
                VenueInterest::query()->selectRaw('count(*)')
                    ->whereColumn('venue_interests.venue_id', 'venue_candidates.venue_id'),
                'requests_count'
            );
    }

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->canManage('gamehub') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = VenueCandidate::query()->where('status', VenueCandidate::STATUS_NEW)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Found venues waiting for review';
    }

    public static function table(Table $table): Table
    {
        return VenueCandidatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenueCandidates::route('/'),
        ];
    }
}
