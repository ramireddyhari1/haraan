<?php

namespace App\Filament\Resources\FeedItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeedItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->defaultImageUrl('https://placehold.co/80x48/0f172a/ffffff?text=Card')
                    ->size(48),
                TextColumn::make('section')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'for_you' ? 'For You' : ucfirst($state))
                    ->color(fn (string $state): string => $state === 'trending' ? 'danger' : 'primary')
                    ->sortable(),
                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn ($record) => $record->subtitle)
                    ->searchable(),
                TextColumn::make('badge')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
                TextColumn::make('rating')
                    ->icon('heroicon-m-star')
                    ->iconColor('warning')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('section')
                    ->options(['for_you' => 'For You', 'trending' => 'Trending']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
