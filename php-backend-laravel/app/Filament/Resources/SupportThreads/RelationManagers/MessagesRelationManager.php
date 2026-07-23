<?php

namespace App\Filament\Resources\SupportThreads\RelationManagers;

use App\Events\ContentUpdated;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    /**
     * Saved responses agents can drop into a reply, then tweak before sending.
     * Keeps tone consistent and cuts typing for the common asks.
     */
    private const CANNED_REPLIES = [
        'Thanks for reaching out! Could you share your booking ID (starts with HRN) so we can look into this?',
        'We\'ve issued your refund — it should reflect in 5–7 business days depending on your bank.',
        'Your ticket QR is under Profile → Tickets. Show it at the venue entrance for check-in.',
        'Sorry for the trouble! Could you tell us which screen you saw this on and share a screenshot?',
        'Glad we could help. Is there anything else you need before we close this out?',
    ];

    public function form(Schema $schema): Schema
    {
        // Present the templates as a label => text map so picking one fills the box.
        $templates = collect(self::CANNED_REPLIES)
            ->mapWithKeys(fn (string $text): array => [$text => \Illuminate\Support\Str::limit($text, 60)])
            ->all();

        return $schema
            ->components([
                Select::make('template')
                    ->label('Quick reply')
                    ->placeholder('Insert a saved response…')
                    ->options($templates)
                    ->searchable()
                    ->live()
                    ->dehydrated(false) // not a real column — only used to fill the body
                    ->afterStateUpdated(function ($state, Set $set): void {
                        if (filled($state)) {
                            $set('body', $state);
                        }
                    })
                    ->columnSpanFull(),
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
                    ->color(fn (string $state): string => $state === 'admin' ? 'success' : 'info')
                    // Show which agent replied (only meaningful for team messages).
                    ->description(fn ($record) => $record->sender_type === 'admin' ? $record->sender?->name : null),
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
