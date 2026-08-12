<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;

/**
 * Who a message belongs to and why it was sent — the attribution the transport
 * services (WhatsAppService, InstagramMessenger) have no way to work out for themselves.
 *
 * Passing one is optional so existing callers keep working untouched; a send with
 * no context is recorded as platform-owned, which is exactly right for login OTPs
 * and never bills a partner for something they didn't ask for.
 */
final class MessageContext
{
    public const UTILITY = 'utility';

    public const MARKETING = 'marketing';

    public const AUTHENTICATION = 'authentication';

    public const SERVICE = 'service';

    public function __construct(
        public readonly ?int $partnerId = null,
        public readonly string $category = self::UTILITY,
        public readonly ?string $templateKey = null,
        public readonly ?string $contextType = null,
        public readonly ?int $contextId = null,
    ) {}

    /** Haraan's own traffic — OTPs, account mail. Belongs to no partner. */
    public static function platform(string $category = self::AUTHENTICATION, ?string $templateKey = null): self
    {
        return new self(null, $category, $templateKey);
    }

    /**
     * Attribute to whoever owns the thing being booked: the event's partner for a
     * ticket, the venue's partner for a slot. Falls back to platform-owned rather
     * than guessing — an unattributed send is recoverable, a misattributed one
     * turns into someone else's bill.
     */
    public static function forBooking(Booking $booking, string $category = self::UTILITY, ?string $templateKey = null): self
    {
        $partnerId = $booking->event?->partner_id ?? $booking->venue?->partner_id;

        return new self(
            $partnerId !== null ? (int) $partnerId : null,
            $category,
            $templateKey,
            'booking',
            (int) $booking->id,
        );
    }
}
