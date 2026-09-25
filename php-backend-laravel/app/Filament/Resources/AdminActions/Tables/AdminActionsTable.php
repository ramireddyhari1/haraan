<?php

namespace App\Filament\Resources\AdminActions\Tables;

use App\Models\AdminAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn ($state, $record): string => $state ? "{$state} #{$record->subject_id}" : '—')
                    ->placeholder('—')
                    ->toggleable(),
                // Before → after for each changed field. Nested values are flattened (the old
                // "$k: $v" join threw on any array, taking the whole log page down).
                TextColumn::make('meta')
                    ->label('Details')
                    ->state(fn (AdminAction $record): string => $record->summary())
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AdminAction::query()
                        ->distinct()->orderBy('action')->pluck('action', 'action')->toArray())
                    ->searchable(),
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn () => AdminAction::query()->whereNotNull('subject_type')
                        ->distinct()->orderBy('subject_type')->pluck('subject_type', 'subject_type')->toArray()),
                SelectFilter::make('user_id')
                    ->label('Admin')
                    ->relationship('user', 'name')
                    ->searchable(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
