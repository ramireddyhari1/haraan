<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\MemberPayment;
use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Models\MemberSubscriptionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The member-facing API end to end against a fake Razorpay: catalogue, checkout, signature
 * verification, abandonment, cancellation, plan changes and billing history.
 */
class MembershipApiTest extends TestCase
{
    use MembershipFixtures;
    use RefreshDatabase;

    private FakeRazorpaySubscriptions $razorpay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureRazorpay();
        $this->razorpay = FakeRazorpaySubscriptions::install();
    }

    private function signature(string $paymentId, string $subscriptionId): string
    {
        return hash_hmac('sha256', $paymentId . '|' . $subscriptionId, self::KEY_SECRET);
    }

    // ── Catalogue ───────────────────────────────────────────────────────────

    public function test_catalogue_is_public_and_only_lists_sellable_prices(): void
    {
        $this->sellable('pro');

        $response = $this->getJson('/api/membership/plans')->assertOk();

        $response->assertJsonPath('data.current_plan', 'free');
        $this->assertSame(['free', 'pro', 'hero'], array_column($response->json('data.plans'), 'code'));

        $pro = collect($response->json('data.plans'))->firstWhere('code', 'pro');
        $this->assertCount(1, $pro['prices'], 'only the linked, active monthly price is on sale');
        $this->assertSame('₹99/month', $pro['prices'][0]['label']);

        $hero = collect($response->json('data.plans'))->firstWhere('code', 'hero');
        $this->assertSame([], $hero['prices'], 'unlinked prices are never offered');

        $reviews = collect($pro['features'])->firstWhere('key', 'ai.delivery_review');
        $this->assertSame(20, $reviews['limit']);
        $this->assertStringNotContainsString(self::KEY_SECRET, $response->getContent());
    }

    public function test_catalogue_marks_the_signed_in_members_plan(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'hero');

        $this->asMember($member)->getJson('/api/membership/plans')
            ->assertOk()
            ->assertJsonPath('data.current_plan', 'hero');
    }

    // ── Membership state ────────────────────────────────────────────────────

    public function test_membership_requires_auth(): void
    {
        $this->getJson('/api/membership')->assertUnauthorized();
        $this->postJson('/api/membership/subscribe', ['price_id' => 1])->assertUnauthorized();
        $this->postJson('/api/membership/cancel')->assertUnauthorized();
    }

    public function test_membership_describes_a_free_member_with_quota_usage(): void
    {
        $member = $this->member();

        $this->asMember($member)->getJson('/api/membership')
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.source', 'default')
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.badge', null)
            ->assertJsonFragment(['key' => 'ai.delivery_review', 'limit' => 2, 'used' => 0]);
    }

    public function test_membership_flags_a_failed_payment_for_attention(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'pro', ['status' => MemberSubscription::STATUS_HALTED]);

        $this->asMember($member)->getJson('/api/membership')
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.attention', 'payment_failed');
    }

    // ── Checkout ────────────────────────────────────────────────────────────

    public function test_subscribe_creates_a_razorpay_subscription_and_grants_nothing_yet(): void
    {
        $member = $this->member();
        $price = $this->sellable('pro');

        $response = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $price->id])
            ->assertCreated()
            ->assertJsonPath('data.key', 'rzp_test_key')
            ->assertJsonPath('data.amount_paise', 9900)
            ->assertJsonPath('data.change_type', 'new')
            ->assertJsonPath('data.plan.code', 'pro');

        $subId = $response->json('data.subscription_id');
        $this->assertStringStartsWith('sub_', $subId);

        $local = MemberSubscription::query()->where('provider_subscription_id', $subId)->firstOrFail();
        $this->assertSame(MemberSubscription::STATUS_CREATED, $local->status);
        $this->assertFalse($local->grantsAccess());

        $create = $this->razorpay->callsTo('/subscriptions')[0]['body'];
        $this->assertSame('plan_pro_month', $create['plan_id']);
        $this->assertSame('member', $create['notes']['kind']);
        $this->assertSame((string) $member->id, $create['notes']['user_id']);
        $this->assertArrayNotHasKey('start_at', $create);
        $this->assertStringNotContainsString(self::KEY_SECRET, $response->getContent());

        $this->asMember($member)->getJson('/api/membership')->assertJsonPath('data.plan.code', 'free');
    }

    public function test_subscribe_refuses_prices_that_are_not_on_sale(): void
    {
        $member = $this->member();
        $unlinked = MemberPlanPrice::query()->where('plan_id', $this->plan('hero')->id)->firstOrFail();

        $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => $unlinked->id])
            ->assertStatus(422)->assertJsonPath('code', 'price_unavailable');
        $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => 999999])
            ->assertStatus(422)->assertJsonPath('code', 'price_unavailable');

        $this->assertSame([], $this->razorpay->callsTo('/subscriptions'));
    }

    public function test_subscribe_refuses_the_plan_the_member_already_has(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'pro');

        $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->assertStatus(409)->assertJsonPath('code', 'already_subscribed');
    }

    public function test_a_new_checkout_abandons_the_previous_unpaid_one(): void
    {
        $member = $this->member();
        $price = $this->sellable('pro');

        $first = $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => $price->id])->json('data.subscription_id');
        $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => $price->id])->assertCreated();

        $this->assertSame(
            MemberSubscription::STATUS_ABANDONED,
            MemberSubscription::query()->where('provider_subscription_id', $first)->value('status'),
        );
        $this->assertNotEmpty($this->razorpay->callsTo($first . '/cancel'), 'the stale mandate is cancelled at Razorpay');
    }

    public function test_a_razorpay_failure_leaves_no_open_subscription(): void
    {
        $member = $this->member();
        $this->razorpay->failCreates = true;

        $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->assertStatus(502);

        $this->assertSame(
            [MemberSubscription::STATUS_ABANDONED],
            MemberSubscription::query()->where('user_id', $member->id)->pluck('status')->all(),
        );
    }

    public function test_subscribe_is_unavailable_without_keys(): void
    {
        config(['services.razorpay.secret' => null]);
        $member = $this->member();

        $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->assertStatus(503)->assertJsonPath('code', 'payments_unavailable');
    }

    // ── Verification ────────────────────────────────────────────────────────

    public function test_verify_checks_the_signature_then_trusts_razorpay_for_state(): void
    {
        $member = $this->member();
        $subId = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('hero')->id])
            ->json('data.subscription_id');

        // Razorpay has charged the first cycle.
        $this->razorpay->setStatus($subId, 'active', [
            'current_start' => Carbon::now()->getTimestamp(),
            'current_end' => Carbon::now()->addMonth()->getTimestamp(),
            'paid_count' => 1,
        ]);

        $this->asMember($member)->postJson('/api/membership/verify', [
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_subscription_id' => $subId,
            'razorpay_signature' => $this->signature('pay_ABC', $subId),
        ])
            ->assertOk()
            ->assertJsonPath('data.confirmed', true)
            ->assertJsonPath('data.membership.plan.code', 'hero')
            ->assertJsonPath('data.membership.badge', 'hero');

        $this->assertNotEmpty($this->razorpay->callsTo('/subscriptions/' . $subId, 'GET'));
    }

    public function test_verify_with_a_bad_signature_changes_nothing(): void
    {
        $member = $this->member();
        $subId = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->json('data.subscription_id');
        $this->razorpay->setStatus($subId, 'active');

        $this->asMember($member)->postJson('/api/membership/verify', [
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_subscription_id' => $subId,
            'razorpay_signature' => str_repeat('0', 64),
        ])->assertStatus(400)->assertJsonPath('code', 'invalid_signature');

        $this->assertSame(MemberSubscription::STATUS_CREATED, MemberSubscription::query()->where('provider_subscription_id', $subId)->value('status'));
    }

    public function test_verify_a_client_claim_of_success_is_not_enough(): void
    {
        // Valid signature, but Razorpay says the subscription hasn't been charged: no plan.
        $member = $this->member();
        $subId = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->json('data.subscription_id');
        $this->razorpay->setStatus($subId, 'authenticated');

        $this->asMember($member)->postJson('/api/membership/verify', [
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_subscription_id' => $subId,
            'razorpay_signature' => $this->signature('pay_ABC', $subId),
        ])->assertOk()->assertJsonPath('data.confirmed', false)->assertJsonPath('data.membership.plan.code', 'free');
    }

    public function test_verify_cannot_claim_another_members_subscription(): void
    {
        $owner = $this->member();
        $attacker = $this->member();
        $subId = $this->asMember($owner)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->json('data.subscription_id');
        $this->razorpay->setStatus($subId, 'active');

        $this->asMember($attacker)->postJson('/api/membership/verify', [
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_subscription_id' => $subId,
            'razorpay_signature' => $this->signature('pay_ABC', $subId),
        ])->assertStatus(404);

        $this->asMember($attacker)->getJson('/api/membership')->assertJsonPath('data.plan.code', 'free');
    }

    public function test_verify_when_razorpay_is_unreachable_records_the_mandate_without_granting(): void
    {
        $member = $this->member();
        $subId = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->json('data.subscription_id');
        $this->razorpay->failFetches = true;

        $this->asMember($member)->postJson('/api/membership/verify', [
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_subscription_id' => $subId,
            'razorpay_signature' => $this->signature('pay_ABC', $subId),
        ])->assertOk()->assertJsonPath('data.confirmed', false);

        $this->assertSame(MemberSubscription::STATUS_AUTHENTICATED, MemberSubscription::query()->where('provider_subscription_id', $subId)->value('status'));
    }

    // ── Abandon ─────────────────────────────────────────────────────────────

    public function test_dismissing_checkout_abandons_only_the_members_unpaid_subscription(): void
    {
        $member = $this->member();
        $other = $this->member();
        $subId = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->json('data.subscription_id');

        $this->asMember($other)->postJson('/api/membership/abandon', ['subscription_id' => $subId])->assertOk();
        $this->assertSame(MemberSubscription::STATUS_CREATED, MemberSubscription::query()->where('provider_subscription_id', $subId)->value('status'));

        $this->asMember($member)->postJson('/api/membership/abandon', ['subscription_id' => $subId])->assertOk();
        $this->assertSame(MemberSubscription::STATUS_ABANDONED, MemberSubscription::query()->where('provider_subscription_id', $subId)->value('status'));
    }

    // ── Cancel ──────────────────────────────────────────────────────────────

    public function test_cancel_defaults_to_the_end_of_the_paid_period(): void
    {
        $member = $this->member();
        $subscription = $this->paidSubscription($member, 'pro');
        $this->razorpay->subscriptions[$subscription->provider_subscription_id] = $this->entity($subscription->provider_subscription_id, 'active');

        $this->asMember($member)->postJson('/api/membership/cancel')
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.subscription.cancel_at_period_end', true)
            ->assertJsonPath('data.subscription.renews_at', null)
            ->assertJsonPath('data.subscription.can_cancel', false);

        $call = $this->razorpay->callsTo($subscription->provider_subscription_id . '/cancel')[0];
        $this->assertSame(1, $call['body']['cancel_at_cycle_end']);
        $this->assertTrue(MemberSubscriptionEvent::query()->where('subscription_id', $subscription->id)->where('type', 'cancel_scheduled')->where('actor_id', $member->id)->exists());
    }

    public function test_cancel_immediately_ends_access(): void
    {
        $member = $this->member();
        $subscription = $this->paidSubscription($member, 'pro');
        $this->razorpay->subscriptions[$subscription->provider_subscription_id] = $this->entity($subscription->provider_subscription_id, 'active');

        $this->asMember($member)->postJson('/api/membership/cancel', ['at_period_end' => false])
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'free');

        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $subscription->refresh()->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_cancel_without_a_paid_plan(): void
    {
        $member = $this->member();
        $this->asMember($member)->postJson('/api/membership/cancel')->assertStatus(404)->assertJsonPath('code', 'no_subscription');

        MemberSubscription::create([
            'user_id' => $member->id, 'plan_id' => $this->plan('hero')->id,
            'provider' => MemberSubscription::PROVIDER_ADMIN, 'status' => MemberSubscription::STATUS_ACTIVE,
        ]);
        $this->asMember($member)->postJson('/api/membership/cancel')->assertStatus(422)->assertJsonPath('code', 'not_cancellable');
    }

    // ── Plan changes ────────────────────────────────────────────────────────

    public function test_upgrade_starts_now_and_cancels_the_old_plan_once_paid(): void
    {
        $member = $this->member();
        $pro = $this->paidSubscription($member, 'pro');
        $this->razorpay->subscriptions[$pro->provider_subscription_id] = $this->entity($pro->provider_subscription_id, 'active');

        $response = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('hero')->id])
            ->assertCreated()
            ->assertJsonPath('data.change_type', 'upgrade')
            ->assertJsonPath('data.starts_at', null);
        $heroId = $response->json('data.subscription_id');

        // Until the upgrade is paid, Pro is untouched.
        $this->assertSame([], $this->razorpay->callsTo($pro->provider_subscription_id . '/cancel'));

        $this->razorpay->setStatus($heroId, 'active', ['current_start' => time(), 'current_end' => Carbon::now()->addMonth()->getTimestamp()]);
        $this->asMember($member)->postJson('/api/membership/verify', [
            'razorpay_payment_id' => 'pay_UP',
            'razorpay_subscription_id' => $heroId,
            'razorpay_signature' => $this->signature('pay_UP', $heroId),
        ])->assertOk()->assertJsonPath('data.membership.plan.code', 'hero');

        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $pro->refresh()->status);
        $this->assertSame(0, $this->razorpay->callsTo($pro->provider_subscription_id . '/cancel')[0]['body']['cancel_at_cycle_end']);
    }

    public function test_downgrade_is_scheduled_for_the_end_of_the_paid_period(): void
    {
        $member = $this->member();
        $hero = $this->paidSubscription($member, 'hero');
        $this->razorpay->subscriptions[$hero->provider_subscription_id] = $this->entity($hero->provider_subscription_id, 'active');

        $response = $this->asMember($member)
            ->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('pro')->id])
            ->assertCreated()
            ->assertJsonPath('data.change_type', 'downgrade');
        $proId = $response->json('data.subscription_id');

        $this->assertSame($hero->current_period_end->getTimestamp(), $this->razorpay->callsTo('/subscriptions')[0]['body']['start_at']);

        // The member authorises the mandate; nothing is charged until the start date.
        $this->razorpay->setStatus($proId, 'authenticated');
        $this->asMember($member)->postJson('/api/membership/verify', [
            'razorpay_payment_id' => 'pay_DOWN',
            'razorpay_subscription_id' => $proId,
            'razorpay_signature' => $this->signature('pay_DOWN', $proId),
        ])
            ->assertOk()
            ->assertJsonPath('data.confirmed', true)
            ->assertJsonPath('data.membership.plan.code', 'hero')
            ->assertJsonPath('data.membership.scheduled_change.plan.code', 'pro');

        $this->assertTrue($hero->refresh()->cancel_at_period_end, 'Hero stops renewing but keeps its paid period');
        $this->assertSame(1, $this->razorpay->callsTo($hero->provider_subscription_id . '/cancel')[0]['body']['cancel_at_cycle_end']);

        $this->asMember($member)->postJson('/api/membership/subscribe', ['price_id' => $this->sellable('hero', 'year')->id])
            ->assertStatus(409)->assertJsonPath('code', 'change_already_scheduled');
    }

    // ── History ─────────────────────────────────────────────────────────────

    public function test_payments_lists_only_the_members_own_charges(): void
    {
        $member = $this->member();
        $other = $this->member();
        $mine = $this->paidSubscription($member, 'pro');
        $theirs = $this->paidSubscription($other, 'hero');

        MemberPayment::create(['subscription_id' => $mine->id, 'user_id' => $member->id, 'provider_payment_id' => 'pay_1', 'amount_paise' => 9900, 'status' => 'captured', 'paid_at' => now()]);
        MemberPayment::create(['subscription_id' => $theirs->id, 'user_id' => $other->id, 'provider_payment_id' => 'pay_2', 'amount_paise' => 24900, 'status' => 'captured', 'paid_at' => now()]);

        $this->asMember($member)->getJson('/api/membership/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'pay_1')
            ->assertJsonPath('data.0.plan', 'Pro');
    }
}
