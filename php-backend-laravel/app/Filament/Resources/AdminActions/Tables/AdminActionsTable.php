<?php

namespace App\Filament\Resources\AdminActions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminActionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Admin')
                    ->weight('bold')
                    ->description(fn ($record) => $record->user?->email)
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'cancelled'), str_contains($state, 'failed') => 'danger',
                        str_contains($state, 'processed'), str_contains($state, 'confirmed') => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('meta')
                    ->label('Details')
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? collect($state)->map(fn ($v, $k) => "$k: $v")->implode(', ')
                        : (string) $state)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('action')
                    ->options(fn () => \App\Models\AdminAction::query()
                        ->distinct()->pluck('action', 'action')->toArray()),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
