<?php

namespace App\Filament\Resources\Ads\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->defaultImageUrl('https://placehold.co/80x48/0f172a/ffffff?text=Ad')
                    ->size(48),
                TextColumn::make('sponsor')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn ($record) => $record->subtitle)
                    ->searchable(),
                TextColumn::make('cta_text')
                    ->badge()
                    ->color('success'),
                TextColumn::make('placement')
                    ->badge()
                    ->formatStateUsing(fn ($state) => \App\Models\Ad::PLACEMENTS[$state] ?? "{$state} (unused)")
                    ->color(fn ($state) => array_key_exists((string) $state, \App\Models\Ad::PLACEMENTS) ? 'info' : 'danger'),
                // De-duplicated per viewer — see AdTracker. These are the numbers to quote.
                TextColumn::make('impressions_count')
                    ->label('Impressions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ctr')
                    ->label('CTR')
                    ->state(fn ($record) => $record->ctr())
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 2) . '%')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('starts_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('placement')
                    ->options(\App\Models\Ad::PLACEMENTS),
                \Filament\Tables\Filters\TernaryFilter::make('is_active')->label('Live'),
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
