<?php

namespace App\Filament\Resources\PartnerPlans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartnerPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),

                TextColumn::make('price_inr')
                    ->label('Price')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? '₹' . number_format((int) $state) . '/mo' : 'Free')
                    ->alignRight(),

                TextColumn::make('included_conversations')
                    ->label('Conversations')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? number_format((int) $state) : '—')
                    ->alignRight(),

                TextColumn::make('features')
                    ->label('Unlocks')
                    ->formatStateUsing(function ($state): string {
                        $labels = [
                            'automations.inbound' => 'Auto-replies',
                            'automations.journeys' => 'Journeys',
                            'automations.instagram' => 'Instagram',
                        ];
                        $features = is_array($state) ? $state : [];

                        return $features === []
                            ? 'Transactional only'
                            : implode(', ', array_map(fn ($f): string => $labels[$f] ?? $f, $features));
                    })
                    ->color('gray')
                    ->wrap(),

                TextColumn::make('subscriptions_count')
                    ->label('Partners')
                    ->counts('subscriptions')
                    ->alignRight(),

                IconColumn::make('is_default')->label('Default')->boolean(),
                IconColumn::make('is_active')->label('Selectable')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('No plans yet')
            ->emptyStateDescription('Without a default plan, partners fall back to transactional-only.');
    }
}
