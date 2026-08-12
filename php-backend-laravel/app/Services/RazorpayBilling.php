<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\CreditPack;
use App\Models\PartnerCredit;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Money: recurring subscriptions and one-off credit packs.
 *
 * Two things this class is careful about.
 *
 * **Idempotency.** Razorpay retries webhooks, and a browser can replay a
 * callback. Every grant is keyed on the payment id with a unique index behind
 * it, so the second attempt is a no-op rather than free credits.
 *
 * **Never over-reacting to a failed payment.** A halted subscription suspends
 * automation features and nothing else — PlanEntitlements drops the partner to
 * the default plan, and ticket delivery doesn't consult plans at all.
 */
final class RazorpayBilling
{
    private const SUBSCRIPTIONS_ENDPOINT = 'https://api.razorpay.com/v1/subscriptions';

    public function __construct(
        private readonly RazorpayGateway $gateway,
        private readonly BookingService $bookings,
    ) {}

    // -------------------------------------------------------------------------
    // Subscriptions
    // -------------------------------------------------------------------------

    /**
     * Create a Razorpay subscription for a partner and record it locally as
     * pending-activation (status halted until Razorpay says otherwise — an
     * unpaid subscription must not grant features).
     *
     * @return array{subscription_id: string, short_url: string|null, key: string|null}
     */
    public function startSubscription(User $partner, PartnerPlan $plan, int $months = 12): array
    {
        if (! $this->gateway->isConfigured()) {
            throw new RuntimeException('Payments are not configured.', 500);
        }

        if (blank($plan->razorpay_plan_id)) {
            // Our tier exists but has no counterpart in Razorpay, so there's
            // nothing to charge against.
            throw new RuntimeException('This plan is not set up for online payment yet.', 422);
        }

        try {
            $response = Http::withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))
                ->acceptJson()
                ->timeout(20)
                ->post(self::SUBSCRIPTIONS_ENDPOINT, [
                    'plan_id' => $plan->razorpay_plan_id,
                    'total_count' => $months,
                    'customer_notify' => 1,
                    'notes' => [
                        'partner_id' => (string) $partner->id,
                        'plan_code' => $plan->code,
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach the payment provider.', 502);
        }

        if (! $response->successful()) {
            Log::warning('Razorpay subscription create failed: ' . $response->body());

            throw new RuntimeException('Could not start the subscription.', 500);
        }

        $subscription = $response->json();

        PartnerSubscription::create([
            'partner_id' => $partner->id,
            'plan_id' => $plan->id,
            // Not active until Razorpay confirms a payment — see applyWebhook().
            'status' => PartnerSubscription::STATUS_HALTED,
            'external_id' => $subscription['id'] ?? null,
            'source' => 'razorpay',
            'note' => 'Awaiting first payment',
        ]);

        return [
            'subscription_id' => (string) ($subscription['id'] ?? ''),
            'short_url' => $subscription['short_url'] ?? null,
            'key' => $this->gateway->publicKey(),
        ];
    }

    /**
     * Apply a Razorpay webhook event.
     *
     * @param  array<string, mixed>  $payload
     * @return string what was done, for the log
     */
    public function applyWebhook(string $event, array $payload): string
    {
        return match ($event) {
            'subscription.activated', 'subscription.charged' => $this->markPaid($payload),
            'subscription.halted', 'subscription.pending' => $this->markStatus($payload, PartnerSubscription::STATUS_HALTED),
            'subscription.cancelled', 'subscription.completed', 'subscription.expired' => $this->markStatus($payload, PartnerSubscription::STATUS_CANCELLED),
            'payment.captured' => $this->applyCapturedPayment($payload),
            default => 'ignored',
        };
    }

    /**
     * A captured one-off payment. Two different things buy through this same event, so the
     * handler asks what the payment was FOR before it acts: a ticket order (identified by the
     * Razorpay order id we stamped on the reservation) or a partner credit pack.
     *
     * Tickets come first because they are the case that can silently lose a customer's money.
     * Confirmation used to depend entirely on the client round-trip after checkout — a killed
     * app or a closed tab meant the payment landed and the booking never left PENDING, so the
     * buyer had no ticket and the host's analytics never saw the sale. This is the server-side
     * backstop: Razorpay tells us directly, and it retries until we answer 200.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyCapturedPayment(array $payload): string
    {
        $entity = $payload['payload']['payment']['entity'] ?? [];
        $orderId = trim((string) ($entity['order_id'] ?? ''));
        $paymentId = trim((string) ($entity['id'] ?? ''));

        if ($orderId === '' || $paymentId === '') {
            return $this->grantFromPayment($payload);
        }

        $bookings = Booking::query()->where('razorpay_order_id', $orderId)->get();

        if ($bookings->isEmpty()) {
            return $this->grantFromPayment($payload);
        }

        // Only an order still waiting on its payment may be confirmed from here. A capture
        // event delivered late against an order that has since been cancelled or refunded
        // must not resurrect it into a live ticket — that money's story already ended.
        $confirmable = $bookings->filter(
            fn (Booking $b) => in_array(strtoupper((string) $b->status), ['PENDING', 'EXPIRED'], true),
        );

        if ($confirmable->isEmpty()) {
            // Nothing to do, and nothing wrong: the buyer's own confirm call usually wins
            // this race. Razorpay redelivers until it gets a 200, and re-sending the ticket
            // on every retry would be its own bug.
            return 'booking_not_confirmable';
        }

        $this->bookings->confirmReservation($confirmable->pluck('id')->all(), $paymentId);

        BookingNotifier::dispatch($confirmable->first()->refresh());

        return 'booking_confirmed';
    }

    /** @param array<string, mixed> $payload */
    private function markPaid(array $payload): string
    {
        $subscription = $this->locate($payload);

        if ($subscription === null) {
            return 'unknown_subscription';
        }

        $entity = $payload['payload']['subscription']['entity'] ?? [];

        $subscription->update([
            'status' => PartnerSubscription::STATUS_ACTIVE,
            'current_period_start' => $this->timestamp($entity['current_start'] ?? null) ?? Carbon::now(),
            // Razorpay's current_end is the authority on when access lapses; a
            // month added locally would drift from what they actually charged.
            'current_period_end' => $this->timestamp($entity['current_end'] ?? null) ?? Carbon::now()->addMonth(),
            'note' => null,
        ]);

        return 'activated';
    }

    /** @param array<string, mixed> $payload */
    private function markStatus(array $payload, string $status): string
    {
        $subscription = $this->locate($payload);

        if ($subscription === null) {
            return 'unknown_subscription';
        }

        $subscription->update([
            'status' => $status,
            'note' => $status === PartnerSubscription::STATUS_HALTED
                ? 'Payment failed — automations paused, ticket delivery unaffected'
                : 'Subscription ended',
        ]);

        return $status;
    }

    /** @param array<string, mixed> $payload */
    private function locate(array $payload): ?PartnerSubscription
    {
        $id = $payload['payload']['subscription']['entity']['id'] ?? null;

        return $id === null
            ? null
            : PartnerSubscription::query()->where('external_id', $id)->latest('id')->first();
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_numeric($value) ? Carbon::createFromTimestamp((int) $value) : null;
    }

    // -------------------------------------------------------------------------
    // Credit packs
    // -------------------------------------------------------------------------

    /**
     * Create the one-time order a partner pays to top up conversations.
     *
     * @return array<string, mixed>
     */
    public function createCreditOrder(User $partner, CreditPack $pack): array
    {
        // The receipt is how a webhook that arrives with nothing but a payment
        // works out who bought what.
        $receipt = 'credits:' . $pack->code . ':' . $partner->id;

        $order = $this->gateway->createOrder($pack->amountPaise(), $receipt);

        return [
            'order_id' => $order['id'] ?? null,
            'amount' => $pack->amountPaise(),
            'currency' => 'INR',
            'key' => $this->gateway->publicKey(),
            'pack' => $pack->code,
        ];
    }

    /**
     * Grant a pack after a verified payment. Safe to call repeatedly — the unique
     * index on `reference` turns a replay into a no-op.
     */
    public function grantCredits(User $partner, CreditPack $pack, string $paymentId): bool
    {
        try {
            PartnerCredit::create([
                'partner_id' => $partner->id,
                'conversations' => $pack->conversations,
                'source' => 'purchase',
                'reference' => $paymentId,
                'note' => $pack->name,
            ]);

            return true;
        } catch (QueryException) {
            // Already granted for this payment. Expected on a webhook retry, so
            // it isn't an error worth shouting about.
            return false;
        }
    }

    /**
     * Grant credits straight from a payment.captured webhook, using the receipt
     * on the order to identify the buyer and the pack.
     *
     * @param  array<string, mixed>  $payload
     */
    private function grantFromPayment(array $payload): string
    {
        $entity = $payload['payload']['payment']['entity'] ?? [];
        $paymentId = $entity['id'] ?? null;
        $receipt = (string) ($entity['notes']['receipt'] ?? $entity['description'] ?? '');

        // Razorpay doesn't put the order receipt on the payment entity, so fall
        // back to notes we control.
        $partnerId = $entity['notes']['partner_id'] ?? null;
        $packCode = $entity['notes']['pack'] ?? null;

        if ($paymentId === null || $partnerId === null || $packCode === null) {
            if ($receipt !== '' && str_starts_with($receipt, 'credits:')) {
                [, $packCode, $partnerId] = array_pad(explode(':', $receipt), 3, null);
            } else {
                return 'not_a_credit_payment';
            }
        }

        $partner = User::find($partnerId);
        $pack = CreditPack::where('code', $packCode)->first();

        if ($partner === null || $pack === null) {
            return 'unknown_partner_or_pack';
        }

        return $this->grantCredits($partner, $pack, (string) $paymentId) ? 'credits_granted' : 'already_granted';
    }
}
