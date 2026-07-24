<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CreditPack;
use App\Models\MessageLog;
use App\Models\PartnerCredit;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\User;
use App\Services\PlanEntitlements;
use App\Services\RazorpayBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Billing grants paid features and credits, so the webhook signature is a
 * security boundary and the grant path is a correctness one — a retried webhook
 * must never hand out a second pack.
 */
class RazorpayBillingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec-test';

    private User $partner;

    private PartnerPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Billing Partner', 'email' => 'billing-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);

        PartnerPlan::create([
            'code' => 'starter', 'name' => 'Starter', 'price_inr' => 0,
            'included_conversations' => 0, 'features' => [], 'is_default' => true,
        ]);

        $this->plan = PartnerPlan::create([
            'code' => 'pro', 'name' => 'Pro', 'price_inr' => 999,
            'included_conversations' => 500,
            'features' => [PartnerPlan::FEATURE_JOURNEYS],
            'razorpay_plan_id' => 'plan_RZP123',
        ]);

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => self::SECRET,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function webhook(array $payload, ?string $signature = null)
    {
        $raw = json_encode($payload);
        $signature ??= hash_hmac('sha256', $raw, self::SECRET);

        return $this->call(
            'POST', '/api/webhooks/razorpay', [], [], [],
            ['HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $raw,
        );
    }

    private function subscription(string $status = PartnerSubscription::STATUS_HALTED): PartnerSubscription
    {
        return PartnerSubscription::create([
            'partner_id' => $this->partner->id, 'plan_id' => $this->plan->id,
            'status' => $status, 'external_id' => 'sub_ABC123', 'source' => 'razorpay',
        ]);
    }

    /** @param array<string, mixed> $entity */
    private function subscriptionEvent(string $event, array $entity = []): array
    {
        return [
            'event' => $event,
            'payload' => ['subscription' => ['entity' => array_merge(['id' => 'sub_ABC123'], $entity)]],
        ];
    }

    // --- security -----------------------------------------------------------

    public function test_a_forged_signature_is_rejected(): void
    {
        $this->subscription();

        $this->webhook($this->subscriptionEvent('subscription.activated'), 'nonsense')->assertStatus(403);

        // Crucially: no features were granted.
        $this->assertSame(PartnerSubscription::STATUS_HALTED, PartnerSubscription::first()->status);
    }

    public function test_it_fails_closed_with_no_webhook_secret(): void
    {
        config(['services.razorpay.webhook_secret' => '']);

        $this->webhook($this->subscriptionEvent('subscription.activated'))->assertStatus(403);
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        $this->subscription();

        // Sign one payload, send another — the classic replay-with-edits.
        $signature = hash_hmac('sha256', json_encode($this->subscriptionEvent('subscription.halted')), self::SECRET);

        $this->webhook($this->subscriptionEvent('subscription.activated'), $signature)->assertStatus(403);
        $this->assertSame(PartnerSubscription::STATUS_HALTED, PartnerSubscription::first()->status);
    }

    // --- subscription lifecycle ---------------------------------------------

    public function test_activation_grants_features_and_records_the_period(): void
    {
        $this->subscription();
        $start = Carbon::now()->startOfDay();
        $end = $start->copy()->addMonth();

        $this->webhook($this->subscriptionEvent('subscription.activated', [
            'current_start' => $start->timestamp,
            'current_end' => $end->timestamp,
        ]))->assertStatus(200);

        $subscription = PartnerSubscription::first();
        $this->assertSame(PartnerSubscription::STATUS_ACTIVE, $subscription->status);
        // Razorpay's dates are the authority — a locally computed month would drift.
        $this->assertSame($end->timestamp, $subscription->current_period_end->timestamp);
        $this->assertTrue(app(PlanEntitlements::class)->allows($this->partner->id, PartnerPlan::FEATURE_JOURNEYS));
    }

    public function test_a_halted_payment_suspends_automations(): void
    {
        $this->subscription(PartnerSubscription::STATUS_ACTIVE);

        $this->webhook($this->subscriptionEvent('subscription.halted'))->assertStatus(200);

        $this->assertSame(PartnerSubscription::STATUS_HALTED, PartnerSubscription::first()->status);
        $this->assertFalse(app(PlanEntitlements::class)->allows($this->partner->id, PartnerPlan::FEATURE_JOURNEYS));
    }

    public function test_a_halted_payment_does_not_touch_ticket_delivery(): void
    {
        // The rule the whole billing design bends around.
        $this->subscription(PartnerSubscription::STATUS_ACTIVE);
        $this->webhook($this->subscriptionEvent('subscription.halted'))->assertStatus(200);

        config([
            'services.whatsapp.enabled' => true, 'services.whatsapp.account_sid' => 'ACtest',
            'services.whatsapp.auth_token' => 'token', 'services.whatsapp.from' => '+14155238886',
        ]);
        Http::fake(fn () => Http::response(['sid' => 'SM1'], 201));

        app(\App\Services\WhatsAppService::class)->sendMessage(
            '9876543210', 'your ticket',
            \App\Support\MessageContext::forBooking(new \App\Models\Booking(['id' => 1])),
        );

        $this->assertSame(1, MessageLog::where('status', MessageLog::STATUS_SENT)->count());
    }

    public function test_cancellation_ends_the_subscription(): void
    {
        $this->subscription(PartnerSubscription::STATUS_ACTIVE);

        $this->webhook($this->subscriptionEvent('subscription.cancelled'))->assertStatus(200);

        $this->assertSame(PartnerSubscription::STATUS_CANCELLED, PartnerSubscription::first()->status);
    }

    public function test_an_unknown_subscription_is_ignored_not_fatal(): void
    {
        $this->webhook($this->subscriptionEvent('subscription.charged'))->assertStatus(200);

        $this->assertSame(0, PartnerSubscription::count());
    }

    public function test_an_unrecognised_event_is_accepted_and_ignored(): void
    {
        $this->webhook(['event' => 'invoice.paid', 'payload' => []])->assertStatus(200);
    }

    // --- credit packs -------------------------------------------------------

    public function test_a_captured_payment_grants_the_pack(): void
    {
        $pack = CreditPack::create([
            'code' => 'pack500', 'name' => '500 conversations',
            'conversations' => 500, 'price_inr' => 499, 'is_active' => true,
        ]);

        $payload = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_XYZ', 'notes' => ['partner_id' => (string) $this->partner->id, 'pack' => $pack->code],
        ]]]];

        $this->webhook($payload)->assertStatus(200);

        $this->assertSame(500, (int) PartnerCredit::where('partner_id', $this->partner->id)->sum('conversations'));
    }

    public function test_a_redelivered_payment_does_not_grant_twice(): void
    {
        $pack = CreditPack::create([
            'code' => 'pack500', 'name' => '500 conversations',
            'conversations' => 500, 'price_inr' => 499, 'is_active' => true,
        ]);

        $payload = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_XYZ', 'notes' => ['partner_id' => (string) $this->partner->id, 'pack' => $pack->code],
        ]]]];

        // Razorpay retries. The unique index on `reference` is what makes the
        // second delivery a no-op instead of free credits.
        $this->webhook($payload)->assertStatus(200);
        $this->webhook($payload)->assertStatus(200);

        $this->assertSame(1, PartnerCredit::count());
        $this->assertSame(500, (int) PartnerCredit::sum('conversations'));
    }

    public function test_credits_lift_the_quota(): void
    {
        $this->subscription(PartnerSubscription::STATUS_ACTIVE);
        $this->plan->update(['included_conversations' => 10]);
        PartnerCredit::create([
            'partner_id' => $this->partner->id, 'conversations' => 500,
            'source' => 'purchase', 'reference' => 'pay_A',
        ]);

        $this->assertSame(510, app(PlanEntitlements::class)->quota($this->partner->id)['remaining']);
    }

    // --- subscription creation ----------------------------------------------

    public function test_starting_a_subscription_records_it_as_unpaid(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'sub_NEW', 'short_url' => 'https://rzp.io/x'], 200)]);

        $result = app(RazorpayBilling::class)->startSubscription($this->partner, $this->plan);

        $this->assertSame('sub_NEW', $result['subscription_id']);
        // Halted until Razorpay confirms payment: creating a subscription must not
        // hand over paid features for free.
        $this->assertSame(PartnerSubscription::STATUS_HALTED, PartnerSubscription::first()->status);
        $this->assertFalse(app(PlanEntitlements::class)->allows($this->partner->id, PartnerPlan::FEATURE_JOURNEYS));
    }

    public function test_a_plan_with_no_razorpay_counterpart_cannot_be_subscribed_to(): void
    {
        $this->plan->update(['razorpay_plan_id' => null]);

        $this->expectException(\RuntimeException::class);

        app(RazorpayBilling::class)->startSubscription($this->partner, $this->plan);
    }
}
