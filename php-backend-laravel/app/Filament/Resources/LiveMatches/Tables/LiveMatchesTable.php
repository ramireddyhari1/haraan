<?php

namespace App\Filament\Resources\LiveMatches\Tables;

use App\Models\LiveMatch;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LiveMatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Match')
                    ->weight('bold')
                    ->description(fn (LiveMatch $r): string => trim(($r->home ?? '') . ' vs ' . ($r->away ?? '')))
                    ->searchable(),
                TextColumn::make('district')
                    ->icon('heroicon-m-map-pin')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'live' => 'danger',
                        'completed' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (string $state): string => $state === LiveMatch::VIS_FEATURED ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === LiveMatch::VIS_FEATURED ? 'Featured' : 'Local'),
                TextColumn::make('competition')
                    ->label('Format')
                    ->toggleable(),
                TextColumn::make('featured_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('visibility')
                    ->options([
                        LiveMatch::VIS_LOCAL => 'Local',
                        LiveMatch::VIS_FEATURED => 'Featured',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'Live' => 'Live',
                        'Scheduled' => 'Scheduled',
                        'Completed' => 'Completed',
                    ]),
                SelectFilter::make('district')
                    ->options(fn (): array => LiveMatch::query()
                        ->whereNotNull('district')
                        ->distinct()
                        ->orderBy('district')
                        ->pluck('district', 'district')
                        ->all()),
            ])
            ->recordActions([
                // Admin-only reach control. Promote a district match to FEATURED
                // (visible everywhere) or pull it back to LOCAL. Records who/when.
                Action::make('toggleVisibility')
                    ->label(fn (LiveMatch $r): string => $r->visibility === LiveMatch::VIS_FEATURED ? 'Unfeature' : 'Feature')
                    ->icon(fn (LiveMatch $r): string => $r->visibility === LiveMatch::VIS_FEATURED ? 'heroicon-m-arrow-uturn-left' : 'heroicon-m-megaphone')
                    ->color(fn (LiveMatch $r): string => $r->visibility === LiveMatch::VIS_FEATURED ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (LiveMatch $r): string => $r->visibility === LiveMatch::VIS_FEATURED
                        ? 'Make this match local again?'
                        : 'Feature this match platform-wide?')
                    ->modalDescription(fn (LiveMatch $r): string => $r->visibility === LiveMatch::VIS_FEATURED
                        ? 'It will go back to being visible only inside its district.'
                        : 'It will appear in every user\'s Featured feed, regardless of district.')
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                    ->action(function (LiveMatch $r): void {
                        if ($r->visibility === LiveMatch::VIS_FEATURED) {
                            $r->update([
                                'visibility' => LiveMatch::VIS_LOCAL,
                                'featured_at' => null,
                                'featured_by' => null,
                            ]);
                        } else {
                            $r->update([
                                'visibility' => LiveMatch::VIS_FEATURED,
                                'featured_at' => now(),
                                'featured_by' => auth()->id(),
                            ]);
                        }
                    }),
            ]);
    }
}
