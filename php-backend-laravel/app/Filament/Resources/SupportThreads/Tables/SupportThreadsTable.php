<?php

namespace App\Filament\Resources\SupportThreads\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->weight('bold')
                    ->description(fn ($record) => $record->subject ?: 'General support')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open'    => 'danger',
                        'pending' => 'warning',
                        'closed'  => 'gray',
                        default   => 'gray',
                    }),
                TextColumn::make('admin_unread_count')
                    ->label('New')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '—'),
                TextColumn::make('assignee.name')
                    ->label('Assigned to')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('last_message_at')
                    ->label('Last activity')
                    ->since()
                    ->sortable(),
            ])
            // Waiting-on-us first, then most recent activity.
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open'    => 'Open',
                        'pending' => 'Pending',
                        'closed'  => 'Closed',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Open chat'),
            ]);
    }
}
