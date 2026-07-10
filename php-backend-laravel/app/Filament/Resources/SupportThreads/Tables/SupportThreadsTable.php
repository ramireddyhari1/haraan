<?php

namespace App\Filament\Resources\SupportThreads\Tables;

use App\Models\SupportThread;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                TextColumn::make('category.label')
                    ->label('Topic')
                    ->badge()
                    ->color('info')
                    ->placeholder('Unsorted'),
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
                SelectFilter::make('category_id')
                    ->label('Topic')
                    ->relationship('category', 'label')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()->label('Open chat'),

                // Take ownership without opening the thread. Hidden once it's yours.
                Action::make('assignToMe')
                    ->label('Assign to me')
                    ->icon('heroicon-m-user-plus')
                    ->color('info')
                    ->visible(fn (SupportThread $record): bool => $record->assigned_to !== auth()->id())
                    ->action(function (SupportThread $record): void {
                        $record->forceFill(['assigned_to' => auth()->id()])->save();
                        Notification::make()->title('Assigned to you')->success()->send();
                    }),

                // Mark resolved. A new user message reopens it automatically.
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-m-check-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (SupportThread $record): bool => $record->status !== 'closed')
                    ->action(function (SupportThread $record): void {
                        $record->forceFill(['status' => 'closed'])->save();
                        Notification::make()->title('Conversation closed')->success()->send();
                    }),

                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->visible(fn (SupportThread $record): bool => $record->status === 'closed')
                    ->action(function (SupportThread $record): void {
                        $record->forceFill(['status' => 'open'])->save();
                        Notification::make()->title('Conversation reopened')->success()->send();
                    }),
            ]);
    }
}
