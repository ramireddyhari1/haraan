<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartnerCredit;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;

/**
 * The single answer to "is this partner allowed to do that, and do they have any
 * quota left?".
 *
 * One rule outranks everything here: **transactional messages are not a plan
 * feature.** Ticket delivery and login OTPs never consult this class. A partner
 * whose card bounced must still be able to get a ticket to the customer who paid
 * for it — turning a billing problem into a refund problem is a worse business
 * than losing the subscription.
 *
 * Quota is counted in conversations, matching what WhatsApp bills, and is read
 * live from the messaging ledger rather than a counter maintained here.
 */
final class PlanEntitlements
{
    public function __construct(private readonly MessageMeter $meter) {}

    /** The live subscription for a partner, or null if they've never had one. */
    public function subscription(?int $partnerId): ?PartnerSubscription
    {
        if ($partnerId === null) {
            return null;
        }

        return PartnerSubscription::query()
            ->with('plan')
            ->where('partner_id', $partnerId)
            ->latest('id')
            ->first();
    }

    /**
     * The plan whose entitlements currently apply.
     *
     * A halted or cancelled subscription drops the partner to the default plan
     * rather than to nothing, so they keep whatever the free tier includes.
     */
    public function plan(?int $partnerId): PartnerPlan
    {
        $subscription = $this->subscription($partnerId);

        if ($subscription === null || ! $subscription->isLive() || $subscription->plan === null) {
            return PartnerPlan::default();
        }

        return $subscription->plan;
    }

    /** Whether the partner's current plan includes a capability. */
    public function allows(?int $partnerId, string $feature): bool
    {
        return $this->plan($partnerId)->includes($feature);
    }

    /**
     * Conversation quota for the current period.
     *
     * remaining = plan allowance + prepaid credits − conversations already used.
     * Credits are lifetime grants rather than per-period, so they carry over.
     *
     * @return array{included: int, credits: int, used: int, remaining: int, allowance: int}
     */
    public function quota(?int $partnerId, string $channel = 'whatsapp'): array
    {
        $included = (int) $this->plan($partnerId)->included_conversations;

        $credits = $partnerId === null
            ? 0
            : (int) PartnerCredit::query()->where('partner_id', $partnerId)->sum('conversations');

        $used = $this->meter->usageThisPeriod($partnerId, $channel)['conversations'];
        $allowance = $included + $credits;

        return [
            'included' => $included,
            'credits' => $credits,
            'used' => $used,
            'allowance' => $allowance,
            'remaining' => max(0, $allowance - $used),
        ];
    }

    public function hasQuota(?int $partnerId, string $channel = 'whatsapp'): bool
    {
        return $this->quota($partnerId, $channel)['remaining'] > 0;
    }

    /**
     * The one call the automation paths make: may this partner send a
     * non-transactional message right now?
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canAutomate(?int $partnerId, string $feature, string $channel = 'whatsapp'): array
    {
        // Platform traffic (partner_id null) is Haraan's own and isn't plan-gated.
        if ($partnerId === null) {
            return ['allowed' => true, 'reason' => null];
        }

        if (! $this->allows($partnerId, $feature)) {
            $subscription = $this->subscription($partnerId);

            return [
                'allowed' => false,
                // Distinguish "never had it" from "had it and the card failed" —
                // the second is recoverable by the partner in a way the first isn't.
                'reason' => $subscription?->status === PartnerSubscription::STATUS_HALTED
                    ? 'payment_failed'
                    : 'plan_excludes',
            ];
        }

        if (! $this->hasQuota($partnerId, $channel)) {
            return ['allowed' => false, 'reason' => 'quota_exceeded'];
        }

        return ['allowed' => true, 'reason' => null];
    }
}
