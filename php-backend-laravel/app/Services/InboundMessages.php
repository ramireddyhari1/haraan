<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\MessageConversation;
use App\Models\MessagingOptOut;
use App\Support\MessageContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * What happens when a customer messages Haraan's WhatsApp number.
 *
 * Order matters, and it isn't negotiable:
 *
 *   1. Record it. Even a message we do nothing with opens the 24h service window
 *      and belongs in the ledger.
 *   2. Compliance keywords (STOP / START / HELP) — handled before anything else
 *      and never routed through the rules table, so no partner can reword or
 *      disable their way out of an opt-out.
 *   3. Auto-reply rules, if the sender hasn't opted out.
 *
 * Partner attribution on a SHARED sender: an inbound message carries no hint of
 * which organiser it's about, so it's attributed to whoever last had a
 * conversation with that number. That's a heuristic, and it's the price of the
 * shared-sender decision — with a per-partner number it would be exact.
 */
final class InboundMessages
{
    /** Exact-match compliance words, checked before any rule. */
    private const STOP_WORDS = ['stop', 'unsubscribe', 'cancel', 'quit', 'end'];

    private const START_WORDS = ['start', 'unstop', 'subscribe', 'resume'];

    private const HELP_WORDS = ['help', 'info'];

    public function __construct(
        private readonly MessageMeter $meter,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * Handle one inbound message.
     *
     * @return array{action: string, partner_id: int|null, reply: string|null}
     */
    public function handle(string $channel, string $from, string $body, ?string $providerMessageId = null): array
    {
        $partnerId = $this->attribute($channel, $from);

        $this->meter->recordInbound($channel, $from, $partnerId, $providerMessageId, $body);

        $text = mb_strtolower(trim($body));

        // --- 1. Compliance, before anything else -----------------------------
        if ($this->isOneOf($text, self::STOP_WORDS)) {
            // Global, not per-partner: the customer messaged one number and has no
            // idea several organisers share it. Silencing only the last one would
            // mean they keep hearing from Haraan after asking us to stop.
            MessagingOptOut::record($channel, $from, null, 'stop_keyword');

            return $this->finish($channel, $from, $partnerId, 'opt_out',
                "You're unsubscribed and won't get further updates from Haraan.\n"
                . 'Reply START to turn them back on. Your tickets are unaffected.',
                force: true);
        }

        if ($this->isOneOf($text, self::START_WORDS)) {
            $removed = MessagingOptOut::query()
                ->where('channel', $channel)
                ->where('recipient', $from)
                ->delete();

            return $this->finish($channel, $from, $partnerId, 'opt_in',
                $removed > 0
                    ? "You're subscribed again — we'll send booking updates and reminders."
                    : "You're already subscribed to Haraan updates.",
                force: true);
        }

        if ($this->isOneOf($text, self::HELP_WORDS)) {
            return $this->finish($channel, $from, $partnerId, 'help',
                "Haraan support\n\n"
                . "· Your tickets: haraan.app/bookings\n"
                . "· Talk to a human: haraan.app/support\n\n"
                . 'Reply STOP to unsubscribe.',
                force: true);
        }

        // --- 2. Someone who opted out gets silence, not a rule ---------------
        if (MessagingOptOut::blocks($channel, $from, $partnerId)) {
            return ['action' => 'ignored_opted_out', 'partner_id' => $partnerId, 'reply' => null];
        }

        // --- 3. Auto-reply rules ---------------------------------------------
        foreach (AutomationRule::forPartner($partnerId, $channel) as $rule) {
            if ($rule->matches($text)) {
                return $this->finish($channel, $from, $partnerId, 'rule:' . $rule->id, $rule->reply_body);
            }
        }

        return ['action' => 'no_match', 'partner_id' => $partnerId, 'reply' => null];
    }

    /**
     * Send the reply and report what happened.
     *
     * `force` marks compliance replies (STOP/START/HELP): those must go out even
     * to someone who has opted out, because confirming an opt-out is the one
     * message a person who said stop still needs.
     */
    private function finish(string $channel, string $to, ?int $partnerId, string $action, string $reply, bool $force = false): array
    {
        $context = new MessageContext(
            $partnerId,
            MessageContext::SERVICE,
            'inbound.' . explode(':', $action)[0],
        );

        // Replies ride inside the service window the customer just opened, so no
        // approved template is needed — this is the one place free text is allowed.
        if ($force || ! MessagingOptOut::blocks($channel, $to, $partnerId)) {
            if ($channel === 'whatsapp') {
                $this->whatsapp->sendMessage($to, $reply, $context);
            } else {
                Log::info("Inbound reply skipped: unsupported channel {$channel}");
            }
        }

        return ['action' => $action, 'partner_id' => $partnerId, 'reply' => $reply];
    }

    /**
     * Whose customer is this? The partner of the most recent conversation with
     * this number — preferring one that's still inside its 24h window.
     */
    public function attribute(string $channel, string $from): ?int
    {
        $conversation = MessageConversation::query()
            ->where('channel', $channel)
            ->where('recipient', $from)
            ->whereNotNull('partner_id')
            ->orderByRaw('case when expires_at > ? then 0 else 1 end', [Carbon::now()])
            ->latest('opened_at')
            ->first();

        return $conversation?->partner_id !== null ? (int) $conversation->partner_id : null;
    }

    /** @param array<int, string> $words */
    private function isOneOf(string $text, array $words): bool
    {
        // Exact match only: "stop" opts out, "please stop by the venue" does not.
        return in_array(trim($text, " \t\n\r\0\x0B.!"), $words, true);
    }
}
