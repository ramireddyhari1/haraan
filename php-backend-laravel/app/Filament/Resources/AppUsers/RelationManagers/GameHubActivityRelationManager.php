<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\PlayerMatchStat;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameHubActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'playerMatchStats';

    protected static ?string $title = 'GameHub Activity';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-trophy';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        /** @var \App\Models\User $ownerRecord */
        $count = (int) ($ownerRecord->matches_played_count ?? $ownerRecord->playerMatchStats()->where('played', true)->count());

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['match']))
            ->columns([
                TextColumn::make('match.title')
                    ->label('Match')
                    ->weight('bold')
                    ->description(fn (PlayerMatchStat $r): string => $r->match
                        ? trim(($r->match->home ?? '') . ' vs ' . ($r->match->away ?? ''))
                        : '—')
                    ->searchable(),

                TextColumn::make('sport')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),

                TextColumn::make('side')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),

                IconColumn::make('played')
                    ->label('Played')
                    ->boolean(),

                TextColumn::make('figures')
                    ->label('Performance figure')
                    ->state(function (PlayerMatchStat $r): string {
                        if (strtolower((string) $r->sport) === 'cricket') {
                            $batting = "{$r->runs} ({$r->balls}b)";
                            $bowling = "{$r->wickets}/{$r->runs_conceded} ({$r->overs_bowled} ov)";

                            return "Bat: {$batting} · Bowl: {$bowling}";
                        }

                        if (! empty($r->stats)) {
                            return json_encode($r->stats, JSON_UNESCAPED_SLASHES);
                        }

                        return 'Played squad member';
                    }),

                TextColumn::make('result')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'win', 'won' => 'success',
                        'loss', 'lost' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('openInMatches')
                    ->label('View in Live Matches')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => route('filament.control.game-hub.resources.live-matches.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }
}
