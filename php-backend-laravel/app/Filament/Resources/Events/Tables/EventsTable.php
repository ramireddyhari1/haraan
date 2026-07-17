<?php

namespace App\Filament\Resources\Events\Tables;

use App\Filament\Resources\Events\Pages\EventAnalytics;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('booking_format')
                    ->searchable(),
                TextColumn::make('visibility')
                    ->searchable(),
                TextColumn::make('access_code')
                    ->searchable(),
                TextColumn::make('location')
                    ->searchable(),
                TextColumn::make('venue')
                    ->searchable(),
                TextColumn::make('date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('time')
                    ->searchable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                TextColumn::make('total_slots')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('available_slots')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('partner.name')
                    ->searchable(),
                TextColumn::make('seat_rows')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('seats_per_row')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('seat_selection')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            // Clicking anywhere on the row (i.e. the event name) opens the read-only analytics
            // dashboard, not the edit form. Editing stays a deliberate, explicit action.
            ->recordUrl(fn ($record): string => EventAnalytics::getUrl(['record' => $record]))
            ->recordActions([
                Action::make('analytics')
                    ->label('Analytics')
                    ->icon('heroicon-m-chart-bar')
                    ->color('gray')
                    ->url(fn ($record): string => EventAnalytics::getUrl(['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
