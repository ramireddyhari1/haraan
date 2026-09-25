<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\LiveMatch;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Game Hub side of the venue: ActionBoard matches played on a booking taken here.
 *
 * The join is `live_matches.venue_booking_id → bookings.id → bookings.venue_id`.
 * `live_matches.venue` is free text a player typed and is deliberately ignored:
 * matching on it would fold every "Turf Park" in the country into one venue, and
 * the resulting counts would be quietly wrong rather than loudly missing.
 *
 * That booking link is also what makes a match eligible to be ranked, which is
 * why the ranked flag is worth showing next to the venue that earned it.
 */
class VenueMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'matches';

    protected static ?string $title = 'Game Hub matches';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-trophy';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->matches()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('live_matches.created_at', 'desc')
            ->emptyStateHeading('No matches played here')
            ->emptyStateDescription('A match links to a venue through the booking it was played on. Matches created without booking a court will not appear.')
            ->emptyStateIcon('heroicon-o-trophy')
            ->columns([
                TextColumn::make('title')
                    ->label('Match')
                    ->state(fn (LiveMatch $record): string => $record->title
                        ?: implode(' vs ', array_filter([$record->home, $record->away])))
                    ->description(fn (LiveMatch $record): ?string => $record->competition)
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('sport')
                    ->label('Sport')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'live', 'in_progress' => 'danger',
                        'completed', 'finished' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('score_text')
                    ->label('Result')
                    ->state(fn (LiveMatch $record): string => $record->result
                        ?: ($record->score_text ?: '—'))
                    ->wrap(),

                IconColumn::make('is_ranked')
                    ->label('Ranked')
                    ->boolean()
                    ->tooltip('A booking at this venue is what makes a match eligible for ranked XP.'),

                TextColumn::make('venue_booking_id')
                    ->label('Booking')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => '#' . $state),

                TextColumn::make('created_at')
                    ->label('Played')
                    ->dateTime('d M Y, g:i A')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('ranked')
                    ->label('Ranked only')
                    ->query(fn (Builder $query): Builder => $query->where('live_matches.is_ranked', true)),

                Filter::make('live')
                    ->label('Live now')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('live_matches.status', ['live', 'LIVE', 'in_progress', 'IN_PROGRESS'])),
            ]);
    }
}
