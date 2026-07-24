<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageConversation;
use App\Models\MessageTemplate;
use Illuminate\Support\Carbon;

/**
 * Decides HOW a business-initiated message may be sent — and whether it may be
 * sent at all.
 *
 * WhatsApp's rule, which this encodes: free text is only allowed inside the
 * 24-hour customer service window, and that window is opened by the CUSTOMER
 * messaging us. Our own outbound messages don't open it. Outside it, the only
 * legal send is a pre-approved template.
 *
 * The important consequence is the third outcome. A journey with no approved
 * template would otherwise be rejected by Meta (error 131047 / 132001) and show
 * up as a delivery failure, which reads like an outage. Naming it "blocked — no
 * approved template" turns a mystery into a to-do item.
 */
final class TemplateResolver
{
    /** Send using an approved template (Meta template name + language) + variables. */
    public const MODE_TEMPLATE = 'template';

    /** A live customer-service window is open; free text is allowed. */
    public const MODE_FREE_TEXT = 'free_text';

    /** Neither — sending would be rejected, so don't try. */
    public const MODE_BLOCKED = 'blocked';

    /**
     * @return array{mode: string, name: string|null, language: string, reason: string|null}
     */
    public function resolve(string $key, string $channel, string $recipient): array
    {
        $template = MessageTemplate::query()
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();

        // An approved template is always the safest route, in or out of a window:
        // it can't be rejected for being business-initiated.
        if ($template !== null && $template->isApproved() && filled($template->provider_template_id)) {
            // Meta identifies a template by NAME + language, not by an opaque id —
            // provider_template_id holds the registered name.
            return [
                'mode' => self::MODE_TEMPLATE,
                'name' => (string) $template->provider_template_id,
                'language' => (string) ($template->locale ?: 'en'),
                'reason' => null,
            ];
        }

        if ($this->hasOpenServiceWindow($channel, $recipient)) {
            return ['mode' => self::MODE_FREE_TEXT, 'name' => null, 'language' => 'en', 'reason' => null];
        }

        return [
            'mode' => self::MODE_BLOCKED,
            'name' => null,
            'language' => 'en',
            // Distinguish "nobody has registered this template" from "it's
            // registered but Meta hasn't approved it yet" — different next steps.
            'reason' => $template === null ? 'no_template_registered' : 'template_not_approved',
        ];
    }

    /**
     * Is there a live window opened by an inbound message?
     *
     * Only 'service' conversations count: {@see MessageMeter::recordInbound}
     * creates those, and only a customer message opens the free-text window.
     */
    public function hasOpenServiceWindow(string $channel, string $recipient): bool
    {
        return MessageConversation::query()
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->where('category', 'service')
            ->where('expires_at', '>', Carbon::now())
            ->exists();
    }
}
