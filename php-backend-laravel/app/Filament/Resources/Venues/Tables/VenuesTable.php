<?php

namespace App\Filament\Resources\Venues\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->getStateUsing(fn ($record) => $record->images[0] ?? null)
                    ->defaultImageUrl('https://placehold.co/80x80/0f172a/ffffff?text=Venue')
                    ->square()
                    ->size(48),
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn ($record) => $record->tagline)
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cricket' => 'success',
                        'Football' => 'warning',
                        'Badminton' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('location')
                    ->icon('heroicon-m-map-pin')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('rating')
                    ->icon('heroicon-m-star')
                    ->iconColor('warning')
                    ->sortable(),
                TextColumn::make('is_bookable')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Bookable' : 'Info only')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('ratings_count')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviews_count')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('category')
                    ->options(['Cricket' => 'Cricket', 'Football' => 'Football', 'Badminton' => 'Badminton', 'Basketball' => 'Basketball']),
                TernaryFilter::make('is_bookable')->label('Bookable'),
                TernaryFilter::make('is_active')->label('Live'),
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
