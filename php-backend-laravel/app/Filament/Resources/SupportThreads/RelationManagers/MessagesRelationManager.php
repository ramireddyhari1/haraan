<?php

namespace App\Filament\Resources\SupportThreads\RelationManagers;

use App\Events\ContentUpdated;
use Filament\Resources\RelationManagers\RelationManager;
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

    /** The inline composer's draft. */
    public string $replyBody = '';

    /** Send the composer's draft as a team reply. */
    public function sendReply(): void
    {
        $body = trim($this->replyBody);
        if ($body === '') {
            return;
        }

        $message = $this->getOwnerRecord()->messages()->create([
            'sender_type' => 'admin',
            'sender_id'   => auth()->id(),
            'body'        => mb_substr($body, 0, 4000),
        ]);

        $this->afterReply($message);
        $this->replyBody = '';

        // The page's header and Status field still hold the old status; refresh them
        // so "Save details" can't write a stale "open" back over "pending".
        $this->dispatch('support-reply-sent');
    }

    /** Reply flags the thread for the user, clears our queue, and nudges the app. */
    private function afterReply($record): void
    {
        $thread = $record->thread;
        if ($thread === null) {
            return;
        }
        $thread->forceFill([
            'status'             => 'pending',
            'last_message_at'    => now(),
            'user_unread_count'  => $thread->user_unread_count + 1,
            'admin_unread_count' => 0,
        ])->save();

        // Nudge the app to refetch the thread promptly.
        ContentUpdated::dispatch('support');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            // Near-live: pull new user messages while an agent has the chat open.
            ->poll('5s')
            ->defaultSort('id', 'asc')
            ->modifyQueryUsing(fn ($query) => $query->with('sender'))
            ->paginated(false)
            // Drawn as a chat: bubbles, day separators, and an inline composer.
            ->content(fn () => view('filament.resources.support-threads.messages', [
                'thread' => $this->getOwnerRecord()->loadMissing('user'),
            ]))
            ->contentFooter(fn () => view('filament.resources.support-threads.composer', [
                'quickReplies' => self::CANNED_REPLIES,
            ]))
            ->columns([
                TextColumn::make('body')->label('Message'),
            ])
            ->recordActions([]);
    }
}
