<?php

namespace App\Filament\Resources\LoginPosters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoginPostersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Poster')
                    ->disk('public')
                    ->defaultImageUrl('https://placehold.co/64x96/0f172a/ffffff?text=Poster')
                    ->height(72),
                TextColumn::make('title')
                    ->weight('bold')
                    ->description(fn ($record) => $record->subtitle)
                    ->placeholder('Untitled')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No login posters yet')
            ->emptyStateDescription('Add one and it appears behind the sign-in card in the app. With none set, the app shows its built-in default posters.');
    }
}
