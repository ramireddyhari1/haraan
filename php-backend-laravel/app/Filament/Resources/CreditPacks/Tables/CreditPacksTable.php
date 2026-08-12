<?php

namespace App\Filament\Resources\CreditPacks\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreditPacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('conversations')->numeric()->alignRight(),
                TextColumn::make('price_inr')
                    ->label('Price')
                    ->formatStateUsing(fn ($state): string => '₹' . number_format((int) $state))
                    ->alignRight(),
                // The number that decides whether a pack is worth selling.
                TextColumn::make('per')
                    ->label('Per conversation')
                    ->state(fn ($record): string => $record->conversations > 0
                        ? '₹' . number_format($record->price_inr / $record->conversations, 2)
                        : '—')
                    ->alignRight()
                    ->color('gray'),
                IconColumn::make('is_active')->label('On sale')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('No packs on sale')
            ->emptyStateDescription('Without a pack, a partner who runs out of quota has no way to top up.');
    }
}
