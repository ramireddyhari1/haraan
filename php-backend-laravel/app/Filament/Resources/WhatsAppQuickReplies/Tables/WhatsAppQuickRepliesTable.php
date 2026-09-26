<?php

namespace App\Filament\Resources\WhatsAppQuickReplies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsAppQuickRepliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shortcut')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('title')
                    ->description(fn ($record) => mb_strimwidth((string) $record->body, 0, 90, '…'))
                    ->searchable(),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('venue.name')
                    ->label('Venue')
                    ->placeholder('Every venue'),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('venue_id')
                    ->label('Venue')
                    ->relationship('venue', 'name'),
            ])
            ->defaultSort('shortcut')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No quick replies yet')
            ->emptyStateDescription('Add one and venue staff can send it from the WhatsApp Desk in one tap.');
    }
}
