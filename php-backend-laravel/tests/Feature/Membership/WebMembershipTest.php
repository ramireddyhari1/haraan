<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\MemberPlanPrice;
use App\Models\MemberSubscription;
use App\Services\Membership\MemberCatalog;
use App\Services\Membership\MemberSubscriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * haraan.app/membership: where Pro and Hero are bought now that the app only shows them.
 * Same catalogue, same MemberSubscriptions lifecycle as the API — over the web session.
 */
class WebMembershipTest extends TestCase
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

    public function test_page_is_public_and_shows_the_catalogue_with_real_prices(): void
    {
        $this->sellable('pro', MemberPlanPrice::INTERVAL_YEAR);
        $this->sellable('hero', MemberPlanPrice::INTERVAL_QUARTER);

        $this->get('/membership')
            ->assertOk()
            ->assertSee('Membership')
            ->assertSee('Early ticket access')
            ->assertSee('Priority venue booking')
            ->assertSee('Sign in')
            // Prices reach the page as data for the switcher, from the catalogue.
            ->assertSee('₹799', false)
            ->assertSee('₹499', false)
            ->assertSee('/3 months', false);
    }

    public function test_guest_signing_in_comes_back_to_the_page(): void
    {
        $this->get('/membership?plan=hero')->assertOk();

        $this->assertStringContainsString('/membership?plan=hero', (string) session('url.intended'));
    }

    public function test_member_page_shows_their_plan_and_what_other_plans_change_for_them(): void
    {
        $member = $this->member();
        $this->paidSubscription($member, 'pro');

        $this->actingAs($member)->get('/membership')
            ->assertOk()
            ->assertSee('Renews on')
            ->assertSee('Your plan')
            ->assertSee('New for you')      // Hero adds priority support Pro doesn't have
            ->assertSee('You have 12 hours early')
            ->assertSee('Cancel membership');
    }

    public function test_checkout_needs_a_session(): void
    {
        $this->postJson('/membership/checkout', ['price_id' => $this->sellable('pro')->id])->assertUnauthorized();
    }

    public function test_web_checkout_creates_the_subscription_and_verify_turns_the_plan_on(): void
    {
        $member = $this->member();
        $price = $this->sellable('hero', MemberPlanPrice::INTERVAL_HALF_YEAR);

        $subId = $this->actingAs($member)
            ->postJson('/membership/checkout', ['price_id' => $price->id])
            ->assertCreated()
            ->assertJsonPath('data.interval', 'half_year')
            ->assertJsonPath('data.amount_paise', 89900)
            ->json('data.subscription_id');

        $this->assertSame(20, $this->razorpay->callsTo('/subscriptions')[0]['body']['total_count']);

        $this->razorpay->setStatus($subId, 'active', [
            'current_start' => Carbon::now()->getTimestamp(),
            'current_end' => Carbon::now()->addMonths(6)->getTimestamp(),
            'paid_count' => 1,
        ]);

        $this->actingAs($member)->postJson('/membership/verify', [
            'razorpay_payment_id' => 'pay_WEB',
            'razorpay_subscription_id' => $subId,
            'razorpay_signature' => hash_hmac('sha256', 'pay_WEB|'.$subId, self::KEY_SECRET),
        ])->assertOk()->assertJsonPath('data.confirmed', true);

        $this->assertSame(MemberSubscription::STATUS_ACTIVE, MemberSubscription::query()->where('provider_subscription_id', $subId)->value('status'));
    }

    public function test_closing_the_sheet_abandons_the_unpaid_subscription(): void
    {
        $member = $this->member();
        $subId = $this->actingAs($member)
            ->postJson('/membership/checkout', ['price_id' => $this->sellable('pro')->id])
            ->json('data.subscription_id');

        $this->actingAs($member)->postJson('/membership/abandon', ['subscription_id' => $subId])->assertOk();

        $this->assertSame(MemberSubscription::STATUS_ABANDONED, MemberSubscription::query()->where('provider_subscription_id', $subId)->value('status'));
    }

    public function test_cancel_stops_renewal_at_period_end(): void
    {
        $member = $this->member();
        $subscription = $this->paidSubscription($member, 'pro');
        $this->razorpay->subscriptions[$subscription->provider_subscription_id] = $this->entity($subscription->provider_subscription_id, 'active');

        $this->actingAs($member)->post('/membership/cancel')->assertRedirect('/membership');

        $this->assertTrue((bool) $subscription->refresh()->cancel_at_period_end);
    }

    public function test_linking_a_three_month_price_creates_a_monthly_plan_billed_every_three_periods(): void
    {
        $price = MemberPlanPrice::query()->where('plan_id', $this->plan('pro')->id)->where('interval', 'quarter')->firstOrFail();

        app(MemberCatalog::class)->linkRazorpayPlan($price);

        $body = $this->razorpay->callsTo('/plans')[0]['body'];
        $this->assertSame('monthly', $body['period']);
        $this->assertSame(3, $body['interval']);
        $this->assertSame(24900, $body['item']['amount']);
        $this->assertSame('Haraan Pro (3 months)', $body['item']['name']);
    }
}
