<?php

namespace App\Filament\Resources\SupportThreads\RelationManagers;

use App\Events\ContentUpdated;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Your reply')
                    ->required()
                    ->rows(3)
                    ->maxLength(4000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            // Near-live: pull new user messages while an agent has the chat open.
            ->poll('5s')
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('sender_type')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'admin' ? 'Team' : 'User')
                    ->color(fn (string $state): string => $state === 'admin' ? 'success' : 'info'),
                TextColumn::make('body')
                    ->label('Message')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->since()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Send reply')
                    ->icon('heroicon-m-paper-airplane')
                    ->modalHeading('Reply to user')
                    ->mutateDataUsing(function (array $data): array {
                        $data['sender_type'] = 'admin';
                        $data['sender_id'] = auth()->id();

                        return $data;
                    })
                    ->after(function ($record): void {
                        $thread = $record->thread;
                        if ($thread === null) {
                            return;
                        }
                        // Reply flags the thread for the user and clears our queue.
                        $thread->forceFill([
                            'status'            => 'pending',
                            'last_message_at'   => now(),
                            'user_unread_count' => $thread->user_unread_count + 1,
                            'admin_unread_count' => 0,
                        ])->save();

                        // Nudge the app to refetch the thread promptly.
                        ContentUpdated::dispatch('support');
                    }),
            ])
            ->recordActions([]);
    }
}
