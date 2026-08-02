<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenueBlocks\Tables;

use App\Models\VenueBlock;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VenueBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Block')
                    ->state(fn (VenueBlock $record): string => $record->label())
                    ->description(fn (VenueBlock $record): string => $record->venue?->name ?? '—')
                    ->searchable(['title'])
                    ->weight('medium'),

                TextColumn::make('kind')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => VenueBlock::KINDS[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'maintenance' => 'warning',
                        'holiday' => 'gray',
                        'academy' => 'success',
                        'tournament' => 'info',
                        default => 'primary',
                    }),

                TextColumn::make('venue_court_id')
                    ->label('Court')
                    ->state(fn (VenueBlock $record): string => $record->court?->name ?? 'Whole venue')
                    ->color(fn (VenueBlock $record): string => $record->venue_court_id === null ? 'danger' : 'gray'),

                TextColumn::make('starts_on')
                    ->label('Dates')
                    ->state(fn (VenueBlock $record): string => $record->starts_on?->isSameDay($record->ends_on)
                        ? $record->starts_on->format('d M Y')
                        : sprintf(
                            '%s → %s',
                            $record->starts_on?->format('d M'),
                            $record->ends_on?->format('d M Y'),
                        ))
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Time')
                    ->state(fn (VenueBlock $record): string => $record->isAllDay()
                        ? 'All day'
                        : sprintf('%s – %s', $record->start_time, $record->end_time)),

                TextColumn::make('weekday')
                    ->label('Repeats')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? 'Every day'
                        : ['Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays'][$state])
                    ->color(fn (?int $state): string => $state === null ? 'gray' : 'info')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (VenueBlock $record): string => match (true) {
                        $record->ends_on?->isBefore(today()) => 'Ended',
                        $record->starts_on?->isAfter(today()) => 'Scheduled',
                        default => 'In force',
                    })
                    ->color(fn (VenueBlock $record): string => match (true) {
                        $record->ends_on?->isBefore(today()) => 'gray',
                        $record->starts_on?->isAfter(today()) => 'info',
                        default => 'danger',
                    }),
            ])
            ->filters([
                Filter::make('current')
                    ->label('In force or upcoming')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereDate('ends_on', '>=', today())),

                SelectFilter::make('kind')
                    ->label('Reason')
                    ->options(VenueBlock::KINDS),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Removing this block puts the court-hour back on sale immediately.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nothing blocked')
            ->emptyStateDescription('Block time for maintenance, a holiday, an academy batch or a tournament — the app, the desk and the API all stop selling it straight away.')
            ->emptyStateIcon('heroicon-o-no-symbol');
    }
}
