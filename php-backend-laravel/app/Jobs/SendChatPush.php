<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\DirectMessage;
use App\Models\User;
use App\Services\Fcm\FcmClient;
use App\Support\PlatformRules;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * "You have a new message" on the phone of everyone else in the conversation.
 *
 * Without this a chat only exists while the app is open: Reverb and the inbox badge reach
 * a running app, and nothing reaches a phone in a pocket. Dispatched AFTER the response
 * by [DirectMessageService::sendAndBroadcast], so the sender's tick never waits on FCM.
 *
 * Who is skipped:
 *  - the sender (their other phone already has it over the socket),
 *  - anyone who muted this conversation and the mute has not run out,
 *  - everyone, when /control → ActionBoard chat → "Phone notifications" is off.
 *
 * The payload carries `type=dm` + `conversation_id` so the app can (a) stay quiet when the
 * reader is already looking at that very thread and (b) stack one notification per
 * conversation instead of one per message.
 */
final class SendChatPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public int $messageId)
    {
    }

    public function handle(FcmClient $fcm): void
    {
        if (! PlatformRules::bool('chat.push') || ! $fcm->isConfigured()) {
            return;
        }

        $message = DirectMessage::query()->find($this->messageId);
        if ($message === null || $message->deleted_at !== null || $message->kind !== null) {
            return;
        }
        $conversation = Conversation::query()->find($message->conversation_id);
        $sender = User::query()->find($message->sender_id);
        if ($conversation === null || $sender === null) {
            return;
        }

        $recipientIds = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $sender->id)
            ->where(fn ($q) => $q->whereNull('muted_until')->orWhere('muted_until', '<', now()))
            ->pluck('user_id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        $name = trim((string) $sender->name) !== '' ? (string) $sender->name : 'A player';
        $title = $conversation->is_group
            ? $name.' · '.(string) $conversation->title
            : $name;

        $body = self::preview($message);
        // Admin switch: some people don't want message text on a lock screen. Off, the
        // notification says only that there is something to read.
        if (! PlatformRules::bool('chat.push_preview')) {
            $body = $conversation->is_group ? 'New message in the group' : 'Sent you a message';
        }

        $data = [
            'type' => 'dm',
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $message->id,
            'deep_link' => 'haraan://chat/'.$conversation->id,
        ];

        DeviceToken::pushable()
            ->whereIn('user_id', $recipientIds)
            // A chat never goes to the partner console's browser tokens.
            ->where('platform', '!=', DeviceToken::PLATFORM_WEB)
            ->chunkById(200, function ($tokens) use ($fcm, $title, $body, $data): void {
                foreach ($tokens as $device) {
                    if ($fcm->send($device->token, $title, $body, $data) === FcmClient::INVALID) {
                        $device->delete();
                    }
                }
            });
    }

    /** One line for the lock screen, matching what the chat list shows. */
    public static function preview(DirectMessage $message): string
    {
        $body = trim((string) $message->body);
        if ($message->image_path !== null) {
            return $body !== '' ? '📷 '.mb_substr($body, 0, 140) : '📷 Photo';
        }
        if ($body === '' && $message->share_type !== null) {
            return match ($message->share_type) {
                'match' => 'Shared a match',
                'venue' => 'Shared a venue',
                'player' => 'Shared a player',
                default => 'Shared a card',
            };
        }

        return mb_substr($body, 0, 160);
    }
}
