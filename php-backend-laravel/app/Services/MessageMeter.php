<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageConversation;
use App\Models\MessageLog;
use App\Models\MessagingUsage;
use App\Support\MessageContext;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records every send and meters it against the 24-hour conversation window.
 *
 * Two rules shape this class:
 *
 *  1. **Only a send that actually left the building is metered.** A message the
 *     provider rejected costs nothing, so it must not open a conversation or eat
 *     a partner's quota — it's still logged, as a failure.
 *  2. **Metering must never break a send.** Every entry point is wrapped: if the
 *     ledger itself throws, the message has already gone out and the caller must
 *     not hear about it. A missing log row is a reporting bug; a booking that
 *     fails because a counter update deadlocked is an outage.
 *
 * Conversations only apply where the provider bills that way (WhatsApp and, later,
 * Instagram). SMS bills per message, so it gets a log row and counters but no
 * conversation.
 */
final class MessageMeter
{
    /** Channels billed per 24h conversation rather than per message. */
    private const CONVERSATION_CHANNELS = ['whatsapp', 'instagram'];

    private const WINDOW_HOURS = 24;

    /**
     * Record one send attempt.
     *
     * @param  string  $status  One of the MessageLog::STATUS_* constants.
     * @param  string|null  $provider  Who carried it. Defaults to Meta, which owns
     *                                 both WhatsApp and Instagram; SMS passes its
     *                                 aggregator so the ledger can tell a MSG91
     *                                 outage apart from a Meta one.
     */
    public function record(
        string $channel,
        string $recipient,
        string $status,
        ?MessageContext $context = null,
        ?string $providerMessageId = null,
        ?string $error = null,
        ?string $provider = null,
    ): ?MessageLog {
        $context ??= MessageContext::platform();
        $recipient = $this->address($channel, $recipient);

        try {
            $sent = $status === MessageLog::STATUS_SENT;

            $conversation = $sent ? $this->touchConversation($channel, $recipient, $context) : null;

            $log = MessageLog::create([
                'partner_id' => $context->partnerId,
                'conversation_id' => $conversation?->id,
                'channel' => $channel,
                'direction' => 'out',
                'recipient' => $recipient,
                'category' => $context->category,
                'template_key' => $context->templateKey,
                'context_type' => $context->contextType,
                'context_id' => $context->contextId,
                'provider' => $provider ?? 'meta',
                'provider_message_id' => $providerMessageId,
                'status' => $status,
                // Truncated: provider errors can be long, and the useful part is the head.
                'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
            ]);

            $this->bumpUsage($context->partnerId, $channel, $sent);

            return $log;
        } catch (Throwable $e) {
            Log::warning('MessageMeter could not record a send: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Record a message FROM a customer.
     *
     * Inbound costs nothing to receive, but it opens (or extends) the 24-hour
     * service window — the period in which we're allowed to reply freely, without
     * a pre-approved template. So it's metered as a conversation, not as a
     * message: it's a billable window, just not a billable send.
     *
     * @return MessageConversation|null the live service window, if one could be opened
     */
    public function recordInbound(
        string $channel,
        string $from,
        ?int $partnerId = null,
        ?string $providerMessageId = null,
        ?string $body = null,
    ): ?MessageConversation {
        $context = new MessageContext($partnerId, MessageContext::SERVICE);
        // The webhook hands back the number in the provider's own format
        // ("919876543210"), which is not the format an outbound send is recorded
        // under. Normalising here is what makes an inbound reply EXTEND the
        // conversation we opened rather than open a second, separately-billed one.
        $from = $this->address($channel, $from);

        try {
            $conversation = $this->touchConversation($channel, $from, $context);

            MessageLog::create([
                'partner_id' => $partnerId,
                'conversation_id' => $conversation?->id,
                'channel' => $channel,
                'direction' => 'in',
                'recipient' => $from,
                'category' => MessageContext::SERVICE,
                'provider' => 'meta',
                'provider_message_id' => $providerMessageId,
                'status' => MessageLog::STATUS_RECEIVED,
                // The customer's own words, trimmed — enough to see what they asked
                // without turning the ledger into a message archive.
                'error' => null,
                'template_key' => null,
                'context_type' => $body !== null ? 'inbound_text' : null,
            ]);

            return $conversation;
        } catch (Throwable $e) {
            Log::warning('MessageMeter could not record an inbound message: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The single spelling of a recipient, so one person is one row.
     *
     * Every write and every lookup in this class goes through it. Phone channels
     * normalise to E.164; Instagram's scoped ids pass through untouched.
     */
    private function address(string $channel, string $address): string
    {
        return PhoneNumber::forChannel(
            $channel,
            $address,
            (string) config('services.whatsapp.default_country', '91'),
        );
    }

    /**
     * Find the live window for this recipient/category, or open a new one. A new
     * window is what a "conversation" costs, so it's also what the quota counts.
     */
    private function touchConversation(string $channel, string $recipient, MessageContext $context): ?MessageConversation
    {
        if (! in_array($channel, self::CONVERSATION_CHANNELS, true)) {
            return null;
        }

        $now = Carbon::now();

        $conversation = MessageConversation::query()
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->where('category', $context->category)
            ->when(
                $context->partnerId === null,
                fn ($q) => $q->whereNull('partner_id'),
                fn ($q) => $q->where('partner_id', $context->partnerId),
            )
            ->where('expires_at', '>', $now)
            ->latest('expires_at')
            ->first();

        if ($conversation !== null) {
            $conversation->increment('message_count');

            return $conversation;
        }

        $conversation = MessageConversation::create([
            'partner_id' => $context->partnerId,
            'channel' => $channel,
            'recipient' => $recipient,
            'category' => $context->category,
            'opened_at' => $now,
            'expires_at' => $now->copy()->addHours(self::WINDOW_HOURS),
            'message_count' => 1,
        ]);

        $this->bumpUsage($context->partnerId, $channel, sent: false, conversations: 1);

        return $conversation;
    }

    /**
     * Increment the period rollup, creating it on first use.
     *
     * Calendar month for now; Phase 2 re-keys the period to the partner's
     * subscription cycle so quota resets line up with what they're billed for.
     */
    private function bumpUsage(?int $partnerId, string $channel, bool $sent, int $conversations = 0): void
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        $usage = MessagingUsage::query()
            ->where('channel', $channel)
            ->whereDate('period_start', $start)
            ->when(
                $partnerId === null,
                fn ($q) => $q->whereNull('partner_id'),
                fn ($q) => $q->where('partner_id', $partnerId),
            )
            ->first();

        if ($usage === null) {
            $usage = MessagingUsage::create([
                'partner_id' => $partnerId,
                'channel' => $channel,
                'period_start' => $start,
                'period_end' => $end,
            ]);
        }

        $increments = [];

        if ($conversations > 0) {
            $increments['conversations_opened'] = $conversations;
        }

        // A record() call is either metering a delivery or a failure, never both;
        // conversation opens come through with sent:false and are counted above.
        if ($conversations === 0) {
            $increments[$sent ? 'messages_sent' : 'messages_failed'] = 1;
        }

        foreach ($increments as $column => $by) {
            $usage->increment($column, $by);
        }
    }

    /**
     * What a partner has used this period — the read the plan quota will gate on.
     *
     * @return array{conversations: int, messages: int, failed: int}
     */
    public function usageThisPeriod(?int $partnerId, string $channel = 'whatsapp'): array
    {
        $usage = MessagingUsage::query()
            ->where('channel', $channel)
            ->whereDate('period_start', Carbon::now()->startOfMonth()->toDateString())
            ->when(
                $partnerId === null,
                fn ($q) => $q->whereNull('partner_id'),
                fn ($q) => $q->where('partner_id', $partnerId),
            )
            ->first();

        return [
            'conversations' => (int) ($usage->conversations_opened ?? 0),
            'messages' => (int) ($usage->messages_sent ?? 0),
            'failed' => (int) ($usage->messages_failed ?? 0),
        ];
    }
}
