<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\MemberPayment;
use App\Models\MemberPlan;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Models\MemberSubscriptionEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The member subscription lifecycle: checkout, verification, webhooks, plan changes,
 * cancellation, reconciliation and admin grants. Member-only — it reads and writes
 * member_* tables and never a partner table.
 *
 * Three rules hold everywhere in here:
 *
 *  1. **Razorpay is the authority on money.** Status and billing periods are copied from
 *     Razorpay's own subscription entity (webhook payload or a fresh fetch), never inferred
 *     from a client callback or computed locally.
 *  2. **Every applied change is idempotent.** Webhook event ids and payment ids are UNIQUE;
 *     a redelivery hits the constraint and becomes a no-op.
 *  3. **Order is respected.** An event older than the last one applied can't move status
 *     backwards (a late `charged` can't revive a `cancelled`).
 */
class MemberSubscriptions
{
    /** Razorpay statuses we mirror. Anything else from the API is ignored rather than stored. */
    private const PROVIDER_STATUSES = [
        MemberSubscription::STATUS_CREATED, MemberSubscription::STATUS_AUTHENTICATED,
        MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PENDING,
        MemberSubscription::STATUS_HALTED, MemberSubscription::STATUS_PAUSED,
        MemberSubscription::STATUS_CANCELLED, MemberSubscription::STATUS_COMPLETED,
        MemberSubscription::STATUS_EXPIRED,
    ];

    public function __construct(
        private readonly MemberRazorpayClient $razorpay,
        private readonly MemberEntitlements $entitlements,
    ) {}

    // -------------------------------------------------------------------------
    // Member actions
    // -------------------------------------------------------------------------

    /**
     * Start checkout for a price: a brand-new plan, an upgrade (takes effect as soon as it's
     * paid), or a downgrade / interval switch (starts when the current period ends, so the
     * member keeps what they already paid for).
     *
     * @return array<string, mixed>
     *
     * @throws MembershipException
     */
    public function subscribe(User $user, MemberPlanPrice $price): array
    {
        $price->loadMissing('plan');

        if (! $price->isPurchasable()) {
            throw new MembershipException("This plan isn't available to buy right now.", 422, 'price_unavailable');
        }

        if (! $this->razorpay->isConfigured()) {
            throw new MembershipException('Payments are not configured.', 503, 'payments_unavailable');
        }

        // One checkout at a time per member. Two taps racing each other would otherwise
        // create two Razorpay subscriptions — and two mandates that both charge.
        $lock = Cache::lock('member-subscribe:' . $user->id, 30);
        if (! $lock->get()) {
            throw new MembershipException('A checkout is already starting. Try again in a moment.', 409, 'checkout_in_progress');
        }

        try {
            MemberEntitlements::flush($user->id);
            $this->abandonStaleCheckouts($user);

            $scheduled = MemberSubscription::query()
                ->where('user_id', $user->id)
                ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
                ->where('status', MemberSubscription::STATUS_AUTHENTICATED)
                ->where('starts_at', '>', Carbon::now())
                ->exists();
            if ($scheduled) {
                throw new MembershipException('You already have a plan change scheduled.', 409, 'change_already_scheduled');
            }

            $current = $this->currentRazorpaySubscription($user);
            [$changeType, $startAt] = $this->classifyChange($current, $price);

            $local = MemberSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $price->plan_id,
                'price_id' => $price->id,
                'provider' => MemberSubscription::PROVIDER_RAZORPAY,
                'status' => MemberSubscription::STATUS_CREATED,
                'starts_at' => $startAt,
                'change_type' => $changeType,
                'replaces_subscription_id' => $current?->id,
                'checkout_expires_at' => Carbon::now()->addMinutes(max(5, (int) config('membership.checkout_ttl_minutes', 30))),
            ]);

            try {
                $remote = $this->razorpay->createSubscription(
                    planId: (string) $price->razorpay_plan_id,
                    totalCount: max(1, (int) config('membership.total_count.' . $price->interval, 12)),
                    startAt: $startAt?->getTimestamp(),
                    // Razorpay wants a little headroom past the checkout window.
                    expireBy: $local->checkout_expires_at->copy()->addMinutes(5)->getTimestamp(),
                    notes: [
                        'kind' => 'member',
                        'user_id' => (string) $user->id,
                        'member_subscription_id' => (string) $local->id,
                        'plan_code' => (string) $price->plan->code,
                    ],
                );
            } catch (MembershipException $e) {
                $local->forceFill(['status' => MemberSubscription::STATUS_ABANDONED, 'ended_at' => Carbon::now(), 'note' => 'Razorpay create failed'])->save();

                throw $e;
            }

            $local->forceFill(['provider_subscription_id' => (string) $remote['id']])->save();
            $this->record($local, 'checkout_created', null, MemberSubscription::STATUS_CREATED, null, [
                'price_id' => $price->id, 'change_type' => $changeType,
            ]);

            return [
                'subscription_id' => (string) $remote['id'],
                'key' => $this->razorpay->publicKey(),
                'amount_paise' => $price->amount_paise,
                'currency' => $price->currency,
                'interval' => $price->interval,
                'plan' => ['code' => $price->plan->code, 'name' => $price->plan->name],
                'change_type' => $changeType,
                'starts_at' => $startAt?->toIso8601String(),
                'short_url' => $remote['short_url'] ?? null,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * The app's post-checkout call. The signature proves the payment belongs to this
     * subscription; ownership proves the subscription belongs to this member; and the state
     * that's stored comes from Razorpay, not from the app.
     *
     * @throws MembershipException
     */
    public function verifyCheckout(User $user, string $paymentId, string $subscriptionId, string $signature): MemberSubscription
    {
        $subscription = MemberSubscription::query()
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->where('provider_subscription_id', $subscriptionId)
            ->where('user_id', $user->id)
            ->first();

        // Not "forbidden" — someone else's subscription id is indistinguishable from none.
        if ($subscription === null) {
            throw new MembershipException('Subscription not found.', 404, 'subscription_not_found');
        }

        if (! $this->razorpay->verifyCheckoutSignature($paymentId, $subscriptionId, $signature)) {
            Log::warning('Member checkout signature mismatch', ['user_id' => $user->id, 'subscription' => $subscription->id]);

            throw new MembershipException('Payment verification failed.', 400, 'invalid_signature');
        }

        $this->record($subscription, 'checkout_verified', $subscription->status, $subscription->status, null, ['payment_id' => $paymentId]);

        try {
            $entity = $this->razorpay->fetchSubscription($subscriptionId);
            $this->applyEntity($subscription, $entity, Carbon::now(), 'verify');
        } catch (MembershipException $e) {
            // The mandate is authorised (the signature says so) but we couldn't read the
            // outcome. Authenticated grants nothing; the webhook or reconcile finishes it.
            if ($subscription->status === MemberSubscription::STATUS_CREATED
                || $subscription->status === MemberSubscription::STATUS_ABANDONED) {
                $this->transition($subscription, MemberSubscription::STATUS_AUTHENTICATED, 'verify_unconfirmed', null, ['error' => $e->getMessage()]);
            }
        }

        return $subscription->refresh();
    }

    /** Checkout was dismissed: cancel the unpaid subscription so its mandate can never be completed later. */
    public function abandon(User $user, string $subscriptionId): ?MemberSubscription
    {
        $subscription = MemberSubscription::query()
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->where('provider_subscription_id', $subscriptionId)
            ->where('user_id', $user->id)
            ->first();

        if ($subscription === null) {
            return null;
        }

        if ($subscription->status === MemberSubscription::STATUS_CREATED) {
            $this->abandonOne($subscription, 'checkout_dismissed');
        }

        return $subscription->refresh();
    }

    /**
     * Cancel the member's paid plan, at the end of the period they've paid for (default) or
     * immediately. A plan change they'd scheduled is cancelled with it.
     *
     * @throws MembershipException
     */
    public function cancel(User $user, bool $atPeriodEnd = true, ?User $actor = null): MemberSubscription
    {
        MemberEntitlements::flush($user->id);
        $subscription = $this->currentRazorpaySubscription($user)
            ?? MemberSubscription::query()
                ->where('user_id', $user->id)
                ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
                ->whereIn('status', [MemberSubscription::STATUS_PENDING, MemberSubscription::STATUS_HALTED])
                ->latest('id')
                ->first();

        if ($subscription === null) {
            $granting = $this->entitlements->activeSubscription($user);
            if ($granting?->provider === MemberSubscription::PROVIDER_ADMIN) {
                throw new MembershipException('Your plan was granted by Haraan and has nothing to cancel.', 422, 'not_cancellable');
            }

            throw new MembershipException("You don't have a paid plan to cancel.", 404, 'no_subscription');
        }

        return $this->cancelSubscription($subscription, $atPeriodEnd, $actor);
    }

    /** @throws MembershipException */
    public function cancelSubscription(MemberSubscription $subscription, bool $atPeriodEnd, ?User $actor = null): MemberSubscription
    {
        if ($subscription->provider !== MemberSubscription::PROVIDER_RAZORPAY || $subscription->isTerminal()) {
            throw new MembershipException('This subscription is not cancellable.', 422, 'not_cancellable');
        }

        // A halted or pending subscription has no paid period left to run out.
        $atPeriodEnd = $atPeriodEnd
            && $subscription->status === MemberSubscription::STATUS_ACTIVE
            && $subscription->current_period_end?->isFuture();

        if ($atPeriodEnd && $subscription->cancel_at_period_end) {
            return $subscription;
        }

        $entity = $this->razorpay->cancelSubscription((string) $subscription->provider_subscription_id, $atPeriodEnd);

        if ($atPeriodEnd) {
            $subscription->forceFill(['cancel_at_period_end' => true])->save();
            $this->record($subscription, 'cancel_scheduled', $subscription->status, $subscription->status, $actor);
        } else {
            $this->applyEntity($subscription, $entity + ['status' => MemberSubscription::STATUS_CANCELLED], Carbon::now(), 'cancel', $actor);
        }

        // A scheduled downgrade waiting on this one would otherwise start charging later.
        MemberSubscription::query()
            ->where('replaces_subscription_id', $subscription->id)
            ->whereIn('status', [MemberSubscription::STATUS_CREATED, MemberSubscription::STATUS_AUTHENTICATED])
            ->get()
            ->each(function (MemberSubscription $next) use ($actor): void {
                try {
                    $this->razorpay->cancelSubscription((string) $next->provider_subscription_id, false);
                } catch (MembershipException $e) {
                    Log::warning('Could not cancel scheduled member subscription ' . $next->id . ': ' . $e->getMessage());
                }
                $this->transition($next, MemberSubscription::STATUS_CANCELLED, 'scheduled_change_cancelled', $actor);
            });

        return $subscription->refresh();
    }

    // -------------------------------------------------------------------------
    // Webhook
    // -------------------------------------------------------------------------

    /**
     * Apply one verified Razorpay webhook delivery.
     *
     * @param  array<string, mixed>  $payload
     * @return string what happened, for the log
     */
    public function applyWebhook(string $event, array $payload, ?string $eventId): string
    {
        if (! str_starts_with($event, 'subscription.')) {
            return 'ignored_event';
        }

        $entity = $payload['payload']['subscription']['entity'] ?? null;
        if (! is_array($entity) || blank($entity['id'] ?? null)) {
            return 'malformed';
        }

        // Only member subscriptions live in this table. A partner subscription's id is
        // simply not found here, and the partner webhook handles it on its own endpoint.
        $subscription = MemberSubscription::query()
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->where('provider_subscription_id', (string) $entity['id'])
            ->first();

        if ($subscription === null) {
            return 'not_a_member_subscription';
        }

        $eventAt = is_numeric($payload['created_at'] ?? null)
            ? Carbon::createFromTimestamp((int) $payload['created_at'])
            : Carbon::now();

        try {
            return DB::transaction(function () use ($subscription, $event, $payload, $entity, $eventId, $eventAt): string {
                // Claim the event id first. A concurrent redelivery hits the UNIQUE index and
                // rolls back with nothing applied.
                $this->record($subscription, 'webhook:' . $event, $subscription->status, $entity['status'] ?? null, null, [
                    'event' => $event,
                    'subscription' => array_intersect_key($entity, array_flip(['id', 'status', 'current_start', 'current_end', 'paid_count', 'charge_at', 'start_at'])),
                ], $eventId);

                $outcome = $this->applyEntity($subscription, $entity, $eventAt, 'webhook');

                if ($event === 'subscription.charged') {
                    $payment = $payload['payload']['payment']['entity'] ?? null;
                    if (is_array($payment)) {
                        $this->recordPayment($subscription, $payment);
                    }
                }

                return $outcome;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return 'duplicate';
            }

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    /** A complimentary plan from /control. No money involved; ends on [$until] or never. */
    public function grant(User $member, MemberPlan $plan, ?Carbon $until, User $actor, string $note): MemberSubscription
    {
        $subscription = MemberSubscription::create([
            'user_id' => $member->id,
            'plan_id' => $plan->id,
            'provider' => MemberSubscription::PROVIDER_ADMIN,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'current_period_start' => Carbon::now(),
            'current_period_end' => $until,
            'note' => mb_substr($note, 0, 255),
            'granted_by' => $actor->id,
        ]);

        $this->record($subscription, 'admin_granted', null, MemberSubscription::STATUS_ACTIVE, $actor, [
            'plan' => $plan->code, 'until' => $until?->toIso8601String(), 'note' => $note,
        ]);

        return $subscription;
    }

    public function extendGrant(MemberSubscription $subscription, ?Carbon $until, User $actor): MemberSubscription
    {
        if ($subscription->provider !== MemberSubscription::PROVIDER_ADMIN) {
            throw new MembershipException('Only complimentary plans can be extended here.', 422, 'not_extendable');
        }

        $from = $subscription->current_period_end?->toIso8601String();
        $subscription->forceFill([
            'current_period_end' => $until,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'ended_at' => null,
        ])->save();

        $this->record($subscription, 'admin_extended', $subscription->getOriginal('status'), MemberSubscription::STATUS_ACTIVE, $actor, [
            'from' => $from, 'until' => $until?->toIso8601String(),
        ]);

        return $subscription;
    }

    public function revokeGrant(MemberSubscription $subscription, User $actor, string $reason): MemberSubscription
    {
        if ($subscription->provider !== MemberSubscription::PROVIDER_ADMIN) {
            throw new MembershipException('Paid subscriptions are cancelled, not revoked.', 422, 'not_revocable');
        }

        $this->transition($subscription, MemberSubscription::STATUS_REVOKED, 'admin_revoked', $actor, ['reason' => $reason]);

        return $subscription->refresh();
    }

    /** Re-read one subscription from Razorpay and apply it. */
    public function sync(MemberSubscription $subscription, ?User $actor = null): string
    {
        if ($subscription->provider !== MemberSubscription::PROVIDER_RAZORPAY || blank($subscription->provider_subscription_id)) {
            return 'not_razorpay';
        }

        $entity = $this->razorpay->fetchSubscription((string) $subscription->provider_subscription_id);

        return $this->applyEntity($subscription, $entity, Carbon::now(), $actor === null ? 'reconcile' : 'admin_sync', $actor);
    }

    // -------------------------------------------------------------------------
    // Reconciliation
    // -------------------------------------------------------------------------

    /**
     * Catch up on anything a webhook didn't tell us.
     *
     * @return array<string, int>
     */
    public function reconcile(bool $dryRun = false): array
    {
        $now = Carbon::now();
        $grace = max(0, (int) config('membership.grace_hours', 48));
        $summary = ['abandoned' => 0, 'synced' => 0, 'sync_failed' => 0, 'grants_expired' => 0, 'replaced_cancelled' => 0];

        // 1. Checkouts nobody finished.
        $stale = MemberSubscription::query()
            ->where('status', MemberSubscription::STATUS_CREATED)
            ->where('checkout_expires_at', '<', $now)
            ->get();
        foreach ($stale as $subscription) {
            $summary['abandoned']++;
            if (! $dryRun) {
                $this->abandonOne($subscription, 'checkout_expired');
            }
        }

        // 2. Paid subscriptions whose renewal we never heard about, and scheduled ones that
        //    should have started.
        $overdue = MemberSubscription::query()
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->where(function ($q) use ($now, $grace): void {
                $q->where(function ($q) use ($now, $grace): void {
                    $q->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PENDING])
                        ->where('current_period_end', '<', $now->copy()->subHours($grace));
                })->orWhere(function ($q) use ($now): void {
                    // Authenticated but never activated: either a scheduled start that has
                    // passed, or an immediate one whose activation webhook never arrived.
                    $q->where('status', MemberSubscription::STATUS_AUTHENTICATED)
                        ->where(fn ($q) => $q
                            ->where(fn ($q) => $q->whereNull('starts_at')->where('updated_at', '<', $now->copy()->subMinutes(15)))
                            ->orWhere('starts_at', '<', $now->copy()->subHour()));
                });
            })
            ->get();
        foreach ($overdue as $subscription) {
            if ($dryRun) {
                $summary['synced']++;

                continue;
            }
            try {
                $this->sync($subscription);
                $summary['synced']++;
            } catch (MembershipException $e) {
                $summary['sync_failed']++;
                Log::warning('Member subscription reconcile failed for ' . $subscription->id . ': ' . $e->getMessage());
            }
        }

        // 3. Complimentary plans past their end date.
        $expiredGrants = MemberSubscription::query()
            ->where('provider', MemberSubscription::PROVIDER_ADMIN)
            ->where('status', MemberSubscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->get();
        foreach ($expiredGrants as $subscription) {
            $summary['grants_expired']++;
            if (! $dryRun) {
                $this->transition($subscription, MemberSubscription::STATUS_EXPIRED, 'grant_expired');
            }
        }

        // 4. Upgrades whose old subscription we failed to cancel — it would keep charging.
        $replaced = MemberSubscription::query()
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PENDING, MemberSubscription::STATUS_HALTED])
            ->whereExists(function ($q): void {
                $q->selectRaw('1')->from('member_subscriptions as newer')
                    ->whereColumn('newer.replaces_subscription_id', 'member_subscriptions.id')
                    ->where('newer.change_type', MemberSubscription::CHANGE_UPGRADE)
                    ->where('newer.status', MemberSubscription::STATUS_ACTIVE);
            })
            ->get();
        foreach ($replaced as $subscription) {
            $summary['replaced_cancelled']++;
            if (! $dryRun) {
                $this->cancelQuietly($subscription, false, 'upgrade_cleanup');
            }
        }

        return $summary;
    }

    // -------------------------------------------------------------------------
    // State application
    // -------------------------------------------------------------------------

    /**
     * Copy Razorpay's view of a subscription onto ours and run the side effects of any
     * status change.
     *
     * @param  array<string, mixed>  $entity
     */
    public function applyEntity(MemberSubscription $subscription, array $entity, Carbon $eventAt, string $source, ?User $actor = null): string
    {
        $status = (string) ($entity['status'] ?? '');
        if (! in_array($status, self::PROVIDER_STATUSES, true)) {
            return 'unknown_status';
        }

        // Out of order: something newer has already been applied.
        if ($subscription->last_event_at !== null && $eventAt->lessThan($subscription->last_event_at)) {
            return 'stale';
        }

        $from = $subscription->status;

        // Terminal is terminal — except `abandoned`, which is only OUR guess that checkout
        // died. If Razorpay says the member paid after all, the money is real.
        if ($subscription->isTerminal() && $from !== MemberSubscription::STATUS_ABANDONED && $status !== $from) {
            return 'terminal';
        }

        $attributes = [
            'status' => $status,
            'last_event_at' => $eventAt,
        ];

        if (is_numeric($entity['current_start'] ?? null)) {
            $attributes['current_period_start'] = Carbon::createFromTimestamp((int) $entity['current_start']);
        }
        if (is_numeric($entity['current_end'] ?? null)) {
            $attributes['current_period_end'] = Carbon::createFromTimestamp((int) $entity['current_end']);
        }
        if (is_numeric($entity['start_at'] ?? null)) {
            $attributes['starts_at'] = Carbon::createFromTimestamp((int) $entity['start_at']);
        }
        if (is_numeric($entity['paid_count'] ?? null)) {
            $attributes['paid_count'] = (int) $entity['paid_count'];
        }

        if (in_array($status, [MemberSubscription::STATUS_CANCELLED, MemberSubscription::STATUS_COMPLETED, MemberSubscription::STATUS_EXPIRED], true)) {
            $attributes['ended_at'] = $subscription->ended_at ?? Carbon::now();
            if ($status === MemberSubscription::STATUS_CANCELLED) {
                $attributes['cancelled_at'] = $subscription->cancelled_at ?? Carbon::now();
            }
        } elseif ($from === MemberSubscription::STATUS_ABANDONED) {
            $attributes['ended_at'] = null;
        }

        $subscription->forceFill($attributes)->save();

        if ($from === $status) {
            return 'refreshed';
        }

        if ($source !== 'webhook') {
            // Webhooks record their own event before applying.
            $this->record($subscription, $source . ':' . $status, $from, $status, $actor);
        }

        if ($status === MemberSubscription::STATUS_ACTIVE) {
            $this->onActivated($subscription);
        } elseif ($status === MemberSubscription::STATUS_AUTHENTICATED) {
            $this->onAuthenticated($subscription);
        }

        return $status;
    }

    /** Paid and live. Retire whatever this replaced, and any stray open subscription that could still charge. */
    private function onActivated(MemberSubscription $subscription): void
    {
        $others = MemberSubscription::query()
            ->where('user_id', $subscription->user_id)
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->whereKeyNot($subscription->id)
            ->whereIn('status', [
                MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PENDING,
                MemberSubscription::STATUS_HALTED, MemberSubscription::STATUS_PAUSED,
                MemberSubscription::STATUS_CREATED,
            ])
            ->get();

        foreach ($others as $other) {
            if ($other->status === MemberSubscription::STATUS_CREATED) {
                $this->abandonOne($other, 'superseded');

                continue;
            }

            $isReplaced = $subscription->replaces_subscription_id === $other->id;

            // A downgrade/interval switch starts at the old period's end, so the old one is
            // already ending on its own. Anything else overlapping would double-charge.
            if ($isReplaced && $subscription->change_type !== MemberSubscription::CHANGE_UPGRADE && $other->cancel_at_period_end) {
                continue;
            }

            $this->cancelQuietly($other, false, $isReplaced ? 'upgraded' : 'superseded');
        }
    }

    /** A scheduled change is confirmed: now (and only now) stop the current plan renewing. */
    private function onAuthenticated(MemberSubscription $subscription): void
    {
        if (! in_array($subscription->change_type, [MemberSubscription::CHANGE_DOWNGRADE, MemberSubscription::CHANGE_INTERVAL], true)
            || $subscription->replaces_subscription_id === null) {
            return;
        }

        $old = MemberSubscription::query()->find($subscription->replaces_subscription_id);
        if ($old === null || $old->isTerminal() || $old->cancel_at_period_end) {
            return;
        }

        $this->cancelQuietly($old, true, 'scheduled_change');
    }

    private function cancelQuietly(MemberSubscription $subscription, bool $atCycleEnd, string $reason): void
    {
        try {
            $entity = $this->razorpay->cancelSubscription((string) $subscription->provider_subscription_id, $atCycleEnd);
        } catch (MembershipException $e) {
            // Left open on purpose: reconcile() retries, and a false "cancelled" here would
            // hide a subscription that is still charging.
            Log::error('Could not cancel member subscription ' . $subscription->id . " ({$reason}): " . $e->getMessage());
            $this->record($subscription, 'cancel_failed', $subscription->status, $subscription->status, null, ['reason' => $reason, 'error' => $e->getMessage()]);

            return;
        }

        if ($atCycleEnd) {
            $subscription->forceFill(['cancel_at_period_end' => true])->save();
            $this->record($subscription, 'cancel_scheduled', $subscription->status, $subscription->status, null, ['reason' => $reason]);

            return;
        }

        $this->applyEntity($subscription, ['status' => MemberSubscription::STATUS_CANCELLED] + $entity, Carbon::now(), 'system');
        $subscription->forceFill(['note' => ucfirst(str_replace('_', ' ', $reason))])->save();
    }

    private function abandonStaleCheckouts(User $user): void
    {
        MemberSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', MemberSubscription::STATUS_CREATED)
            ->get()
            ->each(fn (MemberSubscription $s) => $this->abandonOne($s, 'new_checkout'));
    }

    private function abandonOne(MemberSubscription $subscription, string $reason): void
    {
        if (filled($subscription->provider_subscription_id)) {
            try {
                $this->razorpay->cancelSubscription((string) $subscription->provider_subscription_id, false);
            } catch (MembershipException) {
                // An unpaid subscription expires on its own at expire_by; this is tidiness.
            }
        }

        $this->transition($subscription, MemberSubscription::STATUS_ABANDONED, $reason);
    }

    /** @param array<string, mixed> $payload */
    private function transition(MemberSubscription $subscription, string $to, string $type, ?User $actor = null, array $payload = []): void
    {
        $from = $subscription->status;
        $attributes = ['status' => $to];
        if (in_array($to, MemberSubscription::TERMINAL_STATUSES, true)) {
            $attributes['ended_at'] = $subscription->ended_at ?? Carbon::now();
        }
        if ($to === MemberSubscription::STATUS_CANCELLED) {
            $attributes['cancelled_at'] = $subscription->cancelled_at ?? Carbon::now();
        }

        $subscription->forceFill($attributes)->save();
        $this->record($subscription, $type, $from, $to, $actor, $payload);
    }

    /** @param array<string, mixed> $payment */
    private function recordPayment(MemberSubscription $subscription, array $payment): void
    {
        $paymentId = trim((string) ($payment['id'] ?? ''));
        if ($paymentId === '') {
            return;
        }

        $now = Carbon::now();
        DB::table('member_payments')->insertOrIgnore([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'provider_payment_id' => $paymentId,
            'provider_invoice_id' => $payment['invoice_id'] ?? null,
            'amount_paise' => (int) ($payment['amount'] ?? 0),
            'currency' => strtoupper((string) ($payment['currency'] ?? 'INR')),
            'status' => (string) ($payment['status'] ?? MemberPayment::STATUS_CAPTURED),
            'method' => isset($payment['method']) ? mb_substr((string) $payment['method'], 0, 30) : null,
            'paid_at' => is_numeric($payment['created_at'] ?? null) ? Carbon::createFromTimestamp((int) $payment['created_at']) : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function record(
        MemberSubscription $subscription,
        string $type,
        ?string $from,
        ?string $to,
        ?User $actor = null,
        array $payload = [],
        ?string $providerEventId = null,
    ): void {
        MemberSubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'type' => mb_substr($type, 0, 40),
            'provider_event_id' => $providerEventId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'payload' => $payload === [] ? null : $payload,
        ]);
    }

    /** The member's paid Razorpay subscription that is granting access right now, if any. */
    private function currentRazorpaySubscription(User $user): ?MemberSubscription
    {
        $now = Carbon::now();

        return MemberSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->where('provider', MemberSubscription::PROVIDER_RAZORPAY)
            ->candidates()
            ->get()
            ->filter(fn (MemberSubscription $s) => $s->plan !== null && $s->grantsAccess($now))
            ->sort(fn (MemberSubscription $a, MemberSubscription $b) => [$b->plan->rank, $b->id] <=> [$a->plan->rank, $a->id])
            ->first();
    }

    /**
     * @return array{0: string, 1: Carbon|null} change type and scheduled start
     *
     * @throws MembershipException
     */
    private function classifyChange(?MemberSubscription $current, MemberPlanPrice $price): array
    {
        if ($current === null) {
            return [MemberSubscription::CHANGE_NEW, null];
        }

        if ($current->price_id === $price->id && ! $current->cancel_at_period_end) {
            throw new MembershipException("You're already on this plan.", 409, 'already_subscribed');
        }

        $currentRank = (int) $current->plan?->rank;
        $newRank = (int) $price->plan->rank;

        if ($newRank > $currentRank) {
            return [MemberSubscription::CHANGE_UPGRADE, null];
        }

        $endsAt = $current->current_period_end;
        $startAt = $endsAt !== null && $endsAt->isFuture() ? $endsAt->copy() : null;

        return [$newRank < $currentRank ? MemberSubscription::CHANGE_DOWNGRADE : MemberSubscription::CHANGE_INTERVAL, $startAt];
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[0] ?? $e->getCode());
        $message = strtolower($e->getMessage());

        return $code === '23000' || $code === '23505' || str_contains($message, 'unique');
    }
}
