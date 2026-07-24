<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\MessageLog;
use App\Models\PartnerCredit;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\MessageJourneys;
use App\Services\PlanEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Entitlements decide what a partner is allowed to send. The load-bearing test
 * here is the last one: a lapsed subscription must never stop a ticket.
 */
class PlanEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private PartnerPlan $free;

    private PartnerPlan $paid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Plan Partner',
            'email' => 'plan-partner@example.test',
            'password' => bcrypt('secret'),
            'role' => 'PARTNER',
            'status' => 'active',
            'partner_type' => 'event',
        ]);

        $this->free = PartnerPlan::create([
            'code' => 'starter', 'name' => 'Starter', 'price_inr' => 0,
            'included_conversations' => 0, 'features' => [], 'is_default' => true,
        ]);

        $this->paid = PartnerPlan::create([
            'code' => 'pro', 'name' => 'Pro', 'price_inr' => 999,
            'included_conversations' => 50,
            'features' => [PartnerPlan::FEATURE_JOURNEYS, PartnerPlan::FEATURE_INBOUND],
        ]);

        config([
            'messaging.journeys.enabled' => true,
            'messaging.journeys.quiet_hours.start' => 23,
            'messaging.journeys.quiet_hours.end' => 23,
            'services.whatsapp.enabled' => true,
            'services.whatsapp.account_sid' => 'ACtest',
            'services.whatsapp.auth_token' => 'token',
            'services.whatsapp.from' => '+14155238886',
        ]);

        // Business-initiated sends need an approved template unless the customer
        // has an open window (phase 2b routing).
        $this->approveJourneyTemplates();
    }

    private function subscribe(string $status = PartnerSubscription::STATUS_ACTIVE): PartnerSubscription
    {
        return PartnerSubscription::create([
            'partner_id' => $this->partner->id,
            'plan_id' => $this->paid->id,
            'status' => $status,
            'current_period_start' => Carbon::now()->subDays(3),
            'current_period_end' => Carbon::now()->addDays(27),
        ]);
    }

    /** Approve the journey templates so sends take the real production path. */
    private function approveJourneyTemplates(): void
    {
        foreach (['event.reminder_24h', 'event.reminder_2h', 'review.request'] as $key) {
            \App\Models\MessageTemplate::create([
                'key' => $key, 'name' => $key, 'channel' => 'whatsapp',
                'category' => 'utility', 'body' => '{{1}}', 'variables' => ['1' => 'title'],
                'provider_template_id' => 'HX' . md5($key), 'status' => 'approved', 'is_active' => true,
            ]);
        }
    }

    private function entitlements(): PlanEntitlements
    {
        return app(PlanEntitlements::class);
    }

    private function booking(): Booking
    {
        $event = Event::create([
            'partner_id' => $this->partner->id, 'title' => 'Gated Show', 'category' => 'Music',
            'location' => 'Arena', 'date' => Carbon::now()->addDays(3), 'time' => '19:00',
            'price' => 100, 'total_slots' => 50, 'available_slots' => 50, 'images' => [],
            'status' => 'published',
        ]);

        $buyer = User::firstOrCreate(
            ['email' => 'plan-buyer@example.test'],
            ['name' => 'Buyer', 'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active'],
        );

        return Booking::create([
            'user_id' => $buyer->id, 'event_id' => $event->id, 'quantity' => 1,
            'total_amount' => 100, 'status' => 'CONFIRMED', 'ticket_code' => 'GT' . uniqid(),
            'attendee_phone' => '9876543210',
        ]);
    }

    // --- plan resolution ----------------------------------------------------

    public function test_a_partner_with_no_subscription_gets_the_default_plan(): void
    {
        $this->assertSame('starter', $this->entitlements()->plan($this->partner->id)->code);
        $this->assertFalse($this->entitlements()->allows($this->partner->id, PartnerPlan::FEATURE_JOURNEYS));
    }

    public function test_a_live_subscription_grants_its_features(): void
    {
        $this->subscribe();

        $this->assertTrue($this->entitlements()->allows($this->partner->id, PartnerPlan::FEATURE_JOURNEYS));
    }

    public function test_a_halted_subscription_drops_back_to_the_default_plan(): void
    {
        $this->subscribe(PartnerSubscription::STATUS_HALTED);

        $this->assertSame('starter', $this->entitlements()->plan($this->partner->id)->code);

        $result = $this->entitlements()->canAutomate($this->partner->id, PartnerPlan::FEATURE_JOURNEYS);
        // The reason matters: a failed payment is something the partner can fix.
        $this->assertSame('payment_failed', $result['reason']);
    }

    public function test_an_expired_period_stops_granting_features(): void
    {
        $subscription = $this->subscribe();
        $subscription->update(['current_period_end' => Carbon::now()->subDay()]);

        $this->assertFalse($this->entitlements()->allows($this->partner->id, PartnerPlan::FEATURE_JOURNEYS));
    }

    // --- quota --------------------------------------------------------------

    public function test_quota_combines_plan_allowance_with_prepaid_credits(): void
    {
        $this->subscribe();
        PartnerCredit::create(['partner_id' => $this->partner->id, 'conversations' => 25, 'source' => 'purchase']);

        $quota = $this->entitlements()->quota($this->partner->id);

        $this->assertSame(50, $quota['included']);
        $this->assertSame(25, $quota['credits']);
        $this->assertSame(75, $quota['remaining']);
    }

    public function test_running_out_of_quota_blocks_automation(): void
    {
        $this->subscribe();
        $this->paid->update(['included_conversations' => 1]);

        // Burn the single included conversation.
        Http::fake(fn () => Http::response(['sid' => 'SM1'], 201));
        app(\App\Services\WhatsAppService::class)->sendMessage(
            '9000000001', 'hi',
            new \App\Support\MessageContext($this->partner->id, 'utility'),
        );

        $result = $this->entitlements()->canAutomate($this->partner->id, PartnerPlan::FEATURE_JOURNEYS);

        $this->assertFalse($result['allowed']);
        $this->assertSame('quota_exceeded', $result['reason']);
    }

    public function test_platform_traffic_is_never_plan_gated(): void
    {
        // Login OTPs belong to no partner and must not need a subscription.
        $this->assertTrue($this->entitlements()->canAutomate(null, PartnerPlan::FEATURE_JOURNEYS)['allowed']);
    }

    // --- enforcement --------------------------------------------------------

    public function test_journeys_are_skipped_when_the_plan_excludes_them(): void
    {
        Http::fake(fn () => Http::response(['sid' => 'SM1'], 201));
        $this->booking();
        app(MessageJourneys::class)->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $result = app(MessageJourneys::class)->dispatch();

        $this->assertSame(0, $result['sent']);
        $this->assertSame('plan_excludes', ScheduledMessage::first()->skip_reason);
        Http::assertNothingSent();
    }

    public function test_journeys_send_once_the_partner_is_on_a_plan_that_includes_them(): void
    {
        Http::fake(fn () => Http::response(['sid' => 'SM1'], 201));
        $this->subscribe();
        $this->booking();
        app(MessageJourneys::class)->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $result = app(MessageJourneys::class)->dispatch();

        $this->assertSame(3, $result['sent']);
    }

    public function test_ticket_delivery_is_never_blocked_by_billing(): void
    {
        // The rule this whole class exists to protect: the partner has no plan at
        // all, and the customer still gets the ticket they paid for.
        Http::fake(fn () => Http::response(['sid' => 'SM1'], 201));

        app(\App\Services\BookingNotifier::class)->notify($this->booking());

        $this->assertGreaterThan(
            0,
            MessageLog::where('partner_id', $this->partner->id)
                ->where('status', MessageLog::STATUS_SENT)
                ->count(),
            'a lapsed or absent subscription must never stop ticket delivery',
        );
    }
}
