<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\MemberPayment;
use App\Models\MemberSubscription;
use App\Models\MemberSubscriptionEvent;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The member webhook is where renewals, failures and endings actually arrive. It's a security
 * boundary (it grants paid plans) and a correctness one (Razorpay redelivers and reorders).
 * It must also never touch — or be touched by — partner billing.
 */
class MemberWebhookTest extends TestCase
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

    private function planOf(User $user): string
    {
        MemberEntitlements::flush();

        return app(MemberEntitlements::class)->for($user)->plan->code;
    }

    private function checkout(User $member, string $plan = 'pro'): MemberSubscription
    {
        return $this->paidSubscription($member, $plan, [
            'status' => MemberSubscription::STATUS_CREATED,
            'current_period_start' => null,
            'current_period_end' => null,
            'paid_count' => 0,
        ]);
    }

    // ── Authentication ──────────────────────────────────────────────────────

    public function test_rejects_bad_or_missing_signatures(): void
    {
        $member = $this->member();
        $sub = $this->checkout($member);
        $payload = $this->subscriptionEvent('subscription.activated', $this->entity($sub->provider_subscription_id, 'active'));

        $this->memberWebhook($payload, 'evt_1', 'not-a-signature')->assertForbidden();
        $this->memberWebhook($payload, 'evt_1', '')->assertForbidden();

        // Signed with the PARTNER webhook secret — must not be accepted here.
        $this->memberWebhook($payload, 'evt_1', hash_hmac('sha256', json_encode($payload), 'partner-whsec-test'))->assertForbidden();

        $this->assertSame('free', $this->planOf($member));
    }

    public function test_fails_closed_without_a_configured_secret(): void
    {
        config(['services.razorpay.member_webhook_secret' => null]);
        $member = $this->member();
        $sub = $this->checkout($member);
        $payload = $this->subscriptionEvent('subscription.activated', $this->entity($sub->provider_subscription_id, 'active'));

        $this->memberWebhook($payload, 'evt_1', hash_hmac('sha256', json_encode($payload), ''))->assertForbidden();
        $this->assertSame('free', $this->planOf($member));
    }

    // ── Lifecycle ───────────────────────────────────────────────────────────

    public function test_full_lifecycle_activation_renewal_failure_recovery_and_end(): void
    {
        $member = $this->member();
        $sub = $this->checkout($member);
        $id = $sub->provider_subscription_id;
        $t = Carbon::now()->getTimestamp();

        // Mandate authorised — still nothing granted.
        $this->memberWebhook($this->subscriptionEvent('subscription.authenticated', $this->entity($id, 'authenticated'), [], $t), 'evt_a')->assertOk();
        $this->assertSame('free', $this->planOf($member));

        // First charge.
        $end1 = Carbon::now()->addMonth()->getTimestamp();
        $this->memberWebhook($this->subscriptionEvent('subscription.activated', $this->entity($id, 'active', ['current_end' => $end1]), [], $t + 1), 'evt_b')->assertOk();
        $this->memberWebhook($this->subscriptionEvent(
            'subscription.charged',
            $this->entity($id, 'active', ['current_end' => $end1, 'paid_count' => 1]),
            ['id' => 'pay_1', 'amount' => 9900, 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi', 'invoice_id' => 'inv_1', 'created_at' => $t + 2],
            $t + 2,
        ), 'evt_c')->assertOk();

        $this->assertSame('pro', $this->planOf($member));
        $this->assertSame($end1, $sub->refresh()->current_period_end->getTimestamp());
        $this->assertDatabaseHas('member_payments', ['provider_payment_id' => 'pay_1', 'amount_paise' => 9900, 'method' => 'upi', 'user_id' => $member->id]);

        // Renewal charge fails; Razorpay retries (pending) — member keeps Pro through grace.
        $this->memberWebhook($this->subscriptionEvent('subscription.pending', $this->entity($id, 'pending', ['current_end' => $end1]), [], $t + 10), 'evt_d')->assertOk();
        $this->assertSame(MemberSubscription::STATUS_PENDING, $sub->refresh()->status);
        $this->assertSame('pro', $this->planOf($member));

        // Retries exhausted.
        $this->memberWebhook($this->subscriptionEvent('subscription.halted', $this->entity($id, 'halted', ['current_end' => $end1]), [], $t + 20), 'evt_e')->assertOk();
        $this->assertSame('free', $this->planOf($member));

        // Member fixes their card; Razorpay charges and reactivates.
        $end2 = Carbon::now()->addMonths(2)->getTimestamp();
        $this->memberWebhook($this->subscriptionEvent(
            'subscription.charged',
            $this->entity($id, 'active', ['current_end' => $end2, 'paid_count' => 2]),
            ['id' => 'pay_2', 'amount' => 9900, 'currency' => 'INR', 'status' => 'captured'],
            $t + 30,
        ), 'evt_f')->assertOk();
        $this->assertSame('pro', $this->planOf($member));
        $this->assertSame(2, $sub->refresh()->paid_count);

        // Cancelled.
        $this->memberWebhook($this->subscriptionEvent('subscription.cancelled', $this->entity($id, 'cancelled'), [], $t + 40), 'evt_g')->assertOk();
        $this->assertSame('free', $this->planOf($member));
        $this->assertNotNull($sub->refresh()->ended_at);

        $this->assertSame(2, MemberPayment::query()->where('subscription_id', $sub->id)->count());
        $this->assertSame(7, MemberSubscriptionEvent::query()->where('subscription_id', $sub->id)->whereNotNull('provider_event_id')->count());
    }

    public function test_completed_and_expired_end_access(): void
    {
        foreach (['completed', 'expired'] as $i => $status) {
            $member = $this->member();
            $sub = $this->paidSubscription($member, 'hero');

            $this->memberWebhook(
                $this->subscriptionEvent('subscription.' . $status, $this->entity($sub->provider_subscription_id, $status)),
                'evt_end_' . $i,
            )->assertOk();

            $this->assertSame('free', $this->planOf($member), $status);
        }
    }

    // ── Idempotency & ordering ──────────────────────────────────────────────

    public function test_a_redelivered_event_is_applied_once(): void
    {
        $member = $this->member();
        $sub = $this->paidSubscription($member, 'pro');
        $payload = $this->subscriptionEvent(
            'subscription.charged',
            $this->entity($sub->provider_subscription_id, 'active', ['paid_count' => 2]),
            ['id' => 'pay_dup', 'amount' => 9900, 'currency' => 'INR', 'status' => 'captured'],
        );

        $this->memberWebhook($payload, 'evt_dup')->assertOk();
        $this->memberWebhook($payload, 'evt_dup')->assertOk();
        // Same payment redelivered under a different event id still records one payment.
        $this->memberWebhook($payload, 'evt_dup_2')->assertOk();

        $this->assertSame(1, MemberPayment::query()->where('provider_payment_id', 'pay_dup')->count());
        $this->assertSame(1, MemberSubscriptionEvent::query()->where('provider_event_id', 'evt_dup')->count());
    }

    public function test_an_out_of_order_event_cannot_move_status_backwards(): void
    {
        $member = $this->member();
        $sub = $this->checkout($member);
        $id = $sub->provider_subscription_id;
        $t = Carbon::now()->getTimestamp();

        $this->memberWebhook($this->subscriptionEvent('subscription.cancelled', $this->entity($id, 'cancelled'), [], $t + 100), 'evt_late_cancel')->assertOk();
        // An older activation delivered afterwards.
        $this->memberWebhook($this->subscriptionEvent('subscription.activated', $this->entity($id, 'active'), [], $t), 'evt_old_activate')->assertOk();

        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $sub->refresh()->status);
        $this->assertSame('free', $this->planOf($member));
    }

    public function test_a_payment_after_our_abandonment_is_honoured(): void
    {
        // We guessed the checkout was dead; Razorpay says the member paid. Money wins.
        $member = $this->member();
        $sub = $this->checkout($member);
        $sub->forceFill(['status' => MemberSubscription::STATUS_ABANDONED, 'ended_at' => now()])->save();

        $this->memberWebhook($this->subscriptionEvent('subscription.activated', $this->entity($sub->provider_subscription_id, 'active')), 'evt_revive')->assertOk();

        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $sub->refresh()->status);
        $this->assertNull($sub->ended_at);
        $this->assertSame('pro', $this->planOf($member));
    }

    public function test_activation_retires_other_open_subscriptions_that_could_still_charge(): void
    {
        $member = $this->member();
        $halted = $this->paidSubscription($member, 'pro', ['status' => MemberSubscription::STATUS_HALTED]);
        $this->razorpay->subscriptions[$halted->provider_subscription_id] = $this->entity($halted->provider_subscription_id, 'halted');
        $fresh = $this->checkout($member, 'pro');

        $this->memberWebhook($this->subscriptionEvent('subscription.activated', $this->entity($fresh->provider_subscription_id, 'active')), 'evt_new')->assertOk();

        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $halted->refresh()->status);
        $this->assertNotEmpty($this->razorpay->callsTo($halted->provider_subscription_id . '/cancel'));
    }

    public function test_a_failed_cancel_of_a_replaced_subscription_is_retried_by_reconcile(): void
    {
        $member = $this->member();
        $pro = $this->paidSubscription($member, 'pro');
        $hero = $this->checkout($member, 'hero');
        $hero->forceFill(['replaces_subscription_id' => $pro->id, 'change_type' => MemberSubscription::CHANGE_UPGRADE])->save();
        // Razorpay doesn't know the old one (cancel → 404) the first time.

        $this->memberWebhook($this->subscriptionEvent('subscription.activated', $this->entity($hero->provider_subscription_id, 'active')), 'evt_up')->assertOk();
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $pro->refresh()->status, 'left open so it is retried, never falsely marked cancelled');
        $this->assertTrue(MemberSubscriptionEvent::query()->where('subscription_id', $pro->id)->where('type', 'cancel_failed')->exists());

        $this->razorpay->subscriptions[$pro->provider_subscription_id] = $this->entity($pro->provider_subscription_id, 'active');
        $this->artisan('membership:reconcile')->assertSuccessful();

        $this->assertSame(MemberSubscription::STATUS_CANCELLED, $pro->refresh()->status);
    }

    // ── Separation from partner billing ─────────────────────────────────────

    public function test_partner_subscription_events_are_ignored_by_the_member_webhook(): void
    {
        $partner = User::create([
            'name' => 'Partner', 'email' => 'p@haraan.test', 'password' => bcrypt('x'),
            'role' => 'PARTNER', 'status' => 'active', 'partner_type' => 'event',
        ]);
        $plan = PartnerPlan::create(['code' => 'growth', 'name' => 'Growth', 'price_inr' => 999, 'included_conversations' => 100, 'features' => []]);
        $partnerSub = PartnerSubscription::create([
            'partner_id' => $partner->id, 'plan_id' => $plan->id, 'status' => PartnerSubscription::STATUS_HALTED,
            'external_id' => 'sub_PARTNER1', 'source' => 'razorpay',
        ]);

        $this->memberWebhook($this->subscriptionEvent('subscription.activated', $this->entity('sub_PARTNER1', 'active')), 'evt_partner')->assertOk();

        $this->assertSame(PartnerSubscription::STATUS_HALTED, $partnerSub->refresh()->status);
        $this->assertSame(0, MemberSubscription::query()->count());
        $this->assertSame('free', $this->planOf($partner));
    }

    public function test_member_subscription_events_on_the_partner_webhook_grant_nothing(): void
    {
        $member = $this->member();
        $sub = $this->checkout($member);
        $payload = $this->subscriptionEvent('subscription.activated', $this->entity($sub->provider_subscription_id, 'active'));
        $raw = json_encode($payload);

        $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, 'partner-whsec-test'),
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $this->assertSame(MemberSubscription::STATUS_CREATED, $sub->refresh()->status);
        $this->assertSame('free', $this->planOf($member));
    }

    // ── Reconcile ───────────────────────────────────────────────────────────

    public function test_reconcile_abandons_expired_checkouts_syncs_overdue_renewals_and_expires_grants(): void
    {
        config(['membership.grace_hours' => 48]);

        $a = $this->member();
        $stale = $this->checkout($a);
        $stale->forceFill(['checkout_expires_at' => Carbon::now()->subHour()])->save();
        $this->razorpay->subscriptions[$stale->provider_subscription_id] = $this->entity($stale->provider_subscription_id, 'created');

        $b = $this->member();
        $overdue = $this->paidSubscription($b, 'pro', ['current_period_end' => Carbon::now()->subDays(3)]);
        // Razorpay renewed it; the webhook never arrived.
        $this->razorpay->subscriptions[$overdue->provider_subscription_id] = $this->entity($overdue->provider_subscription_id, 'active', [
            'current_end' => Carbon::now()->addDays(27)->getTimestamp(),
        ]);

        $c = $this->member();
        $grant = MemberSubscription::create([
            'user_id' => $c->id, 'plan_id' => $this->plan('hero')->id,
            'provider' => MemberSubscription::PROVIDER_ADMIN, 'status' => MemberSubscription::STATUS_ACTIVE,
            'current_period_end' => Carbon::now()->subMinute(),
        ]);

        $this->artisan('membership:reconcile --dry-run')->assertSuccessful();
        $this->assertSame(MemberSubscription::STATUS_CREATED, $stale->refresh()->status, 'dry run writes nothing');

        $this->artisan('membership:reconcile')->assertSuccessful();

        $this->assertSame(MemberSubscription::STATUS_ABANDONED, $stale->refresh()->status);
        $this->assertTrue($overdue->refresh()->current_period_end->isFuture());
        $this->assertSame('pro', $this->planOf($b));
        $this->assertSame(MemberSubscription::STATUS_EXPIRED, $grant->refresh()->status);
    }

    public function test_reconcile_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'membership:reconcile'));

        $this->assertCount(1, $events);
    }
}
