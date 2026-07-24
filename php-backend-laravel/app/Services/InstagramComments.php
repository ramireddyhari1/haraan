<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\ChannelConnection;
use App\Models\InstagramCommentReply;
use App\Models\MessagingOptOut;
use App\Models\PartnerPlan;
use App\Support\MessageContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Comment-to-DM: someone comments "price?" under an event reel and gets a DM
 * with the booking link.
 *
 * The highest-intent automation in this market, and the reason Meta allows it at
 * all is narrow — a PRIVATE REPLY is addressed to the comment, not the person, so
 * it's permitted even though they never messaged you. The limits that come with
 * that are absolute:
 *
 *   • exactly ONE private reply per comment, ever;
 *   • within 7 days of the comment;
 *   • never to your own comments.
 *
 * The unique index on instagram_comment_replies.comment_id is what enforces the
 * first one. Meta redelivers webhooks, so without it a retry would be both a
 * duplicate DM and a rule violation.
 */
final class InstagramComments
{
    public function __construct(
        private readonly InstagramMessenger $instagram,
        private readonly PlanEntitlements $entitlements,
    ) {}

    /**
     * Handle one comment event.
     *
     * @param  array<string, mixed>  $value  the webhook's change value
     * @return string what happened, for the log
     */
    public function handle(ChannelConnection $connection, array $value): string
    {
        $commentId = (string) ($value['id'] ?? '');
        $text = trim((string) ($value['text'] ?? ''));
        $commenterId = (string) ($value['from']['id'] ?? '');
        $username = $value['from']['username'] ?? null;

        if ($commentId === '' || $text === '') {
            return 'ignored_empty';
        }

        // Our own comment — including the public "sent you a DM!" this very flow
        // posts. Answering it would start a conversation with ourselves.
        if ($commenterId !== '' && $commenterId === $connection->external_id) {
            return 'ignored_own_comment';
        }

        // Claim the comment BEFORE doing any work. If this insert loses the race
        // (or the webhook is a redelivery) there is nothing more to do — and it
        // matters that the claim is the same row we later mark up, so a crash
        // mid-send can never turn into a second DM.
        try {
            $record = InstagramCommentReply::create([
                'comment_id' => $commentId,
                'partner_id' => $connection->partner_id,
                'connection_id' => $connection->id,
                'media_id' => $value['media']['id'] ?? null,
                'commenter_id' => $commenterId ?: null,
                'commenter_username' => $username,
                'status' => 'sent',
            ]);
        } catch (QueryException) {
            return 'already_handled';
        }

        $outcome = $this->reply($connection, $record, $text, $commentId, $commenterId);

        if ($outcome !== 'replied') {
            $record->update(['status' => 'skipped', 'skip_reason' => $outcome]);
        }

        return $outcome;
    }

    private function reply(
        ChannelConnection $connection,
        InstagramCommentReply $record,
        string $text,
        string $commentId,
        string $commenterId,
    ): string {
        $partnerId = $connection->partner_id;

        // Comment-to-DM is Instagram automation, gated like the rest of it.
        $entitlement = $this->entitlements->canAutomate($partnerId, PartnerPlan::FEATURE_INSTAGRAM, 'instagram');

        if (! $entitlement['allowed']) {
            return (string) $entitlement['reason'];
        }

        // Someone who told us to stop doesn't get DMed because they commented.
        if ($commenterId !== '' && MessagingOptOut::blocks('instagram', $commenterId, $partnerId)) {
            return 'opted_out';
        }

        $rule = AutomationRule::commentRulesFor($partnerId)
            ->first(fn (AutomationRule $r): bool => $r->matches(mb_strtolower($text)));

        if ($rule === null) {
            return 'no_matching_rule';
        }

        $context = new MessageContext($partnerId, MessageContext::SERVICE, 'comment.private_reply');

        if (! $this->instagram->privateReply($connection, $commentId, $rule->reply_body, $context)) {
            $record->update(['rule_id' => $rule->id]);

            return 'send_failed';
        }

        $record->update(['rule_id' => $rule->id]);

        // The visible half, if configured. Best-effort: the DM is the thing that
        // matters, and a failed public reply must not look like a failed automation.
        if (filled($rule->public_reply_body)) {
            try {
                $this->instagram->publicReply($connection, $commentId, (string) $rule->public_reply_body);
            } catch (\Throwable $e) {
                Log::warning('Instagram public reply failed: ' . $e->getMessage());
            }
        }

        return 'replied';
    }
}
