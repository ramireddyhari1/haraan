<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\MessageConversation;
use App\Models\MessageTemplate;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\MessageJourneys;
use App\Services\TemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp only allows free text inside a window the CUSTOMER opened; outside it,
 * an approved template is the only legal send. Getting this wrong doesn't fail
 * loudly — it gets messages rejected and, repeated, damages the sender's quality
 * rating. So the routing rules are pinned here.
 */
class TemplateRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Template Partner', 'email' => 'tpl-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);

        $plan = PartnerPlan::create([
            'code' => 'pro', 'name' => 'Pro', 'price_inr' => 999,
            'included_conversations' => 500,
            'features' => [PartnerPlan::FEATURE_JOURNEYS],
        ]);

        PartnerSubscription::create([
            'partner_id' => $this->partner->id, 'plan_id' => $plan->id,
            'status' => PartnerSubscription::STATUS_ACTIVE,
            'current_period_end' => Carbon::now()->addMonth(),
        ]);

        config([
            'messaging.journeys.enabled' => true,
            'messaging.journeys.quiet_hours.start' => 23,
            'messaging.journeys.quiet_hours.end' => 23,
            'services.whatsapp.enabled' => true,
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'meta-token',
        ]);

        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]], 200));
    }

    private function resolver(): TemplateResolver
    {
        return app(TemplateResolver::class);
    }

    private function approvedTemplate(string $key = 'event.reminder_24h'): MessageTemplate
    {
        return MessageTemplate::create([
            'key' => $key, 'name' => 'Reminder', 'channel' => 'whatsapp',
            'category' => 'utility', 'body' => 'Tomorrow: {{1}}',
            'variables' => ['1' => 'title'], 'provider_template_id' => 'event_reminder_24h', 'locale' => 'en',
            'status' => 'approved', 'is_active' => true,
        ]);
    }

    private function openServiceWindow(string $recipient = '9876543210'): void
    {
        // Only an INBOUND message opens the free-text window.
        MessageConversation::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp',
            'recipient' => $recipient, 'category' => 'service',
            'opened_at' => Carbon::now()->subHour(),
            'expires_at' => Carbon::now()->addHours(23),
        ]);
    }

    private function booking(): Booking
    {
        $event = Event::create([
            'partner_id' => $this->partner->id, 'title' => 'Routed Show', 'category' => 'Music',
            'location' => 'Arena', 'date' => Carbon::now()->addDays(3), 'time' => '19:00',
            'price' => 100, 'total_slots' => 50, 'available_slots' => 50, 'images' => [],
            'status' => 'published',
        ]);

        $buyer = User::firstOrCreate(
            ['email' => 'tpl-buyer@example.test'],
            ['name' => 'Buyer', 'password' => bcrypt('secret'), 'role' => 'user', 'status' => 'active'],
        );

        return Booking::create([
            'user_id' => $buyer->id, 'event_id' => $event->id, 'quantity' => 1,
            'total_amount' => 100, 'status' => 'CONFIRMED', 'ticket_code' => 'RT' . uniqid(),
            'attendee_phone' => '9876543210',
        ]);
    }

    // --- resolution ---------------------------------------------------------

    public function test_an_approved_template_is_used(): void
    {
        $this->approvedTemplate();

        $route = $this->resolver()->resolve('event.reminder_24h', 'whatsapp', '9876543210');

        $this->assertSame(TemplateResolver::MODE_TEMPLATE, $route['mode']);
        // Meta identifies a template by name + language, not an opaque id.
        $this->assertSame('event_reminder_24h', $route['name']);
        $this->assertSame('en', $route['language']);
    }

    public function test_free_text_is_allowed_inside_a_customer_opened_window(): void
    {
        $this->openServiceWindow();

        $route = $this->resolver()->resolve('event.reminder_24h', 'whatsapp', '9876543210');

        $this->assertSame(TemplateResolver::MODE_FREE_TEXT, $route['mode']);
    }

    public function test_our_own_outbound_conversation_does_not_open_the_free_text_window(): void
    {
        // A utility conversation is one WE opened by sending. It grants nothing.
        MessageConversation::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp',
            'recipient' => '9876543210', 'category' => 'utility',
            'opened_at' => Carbon::now()->subHour(), 'expires_at' => Carbon::now()->addHours(23),
        ]);

        $route = $this->resolver()->resolve('event.reminder_24h', 'whatsapp', '9876543210');

        $this->assertSame(TemplateResolver::MODE_BLOCKED, $route['mode']);
    }

    public function test_an_expired_window_no_longer_allows_free_text(): void
    {
        MessageConversation::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp',
            'recipient' => '9876543210', 'category' => 'service',
            'opened_at' => Carbon::now()->subDays(2), 'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->assertSame(
            TemplateResolver::MODE_BLOCKED,
            $this->resolver()->resolve('event.reminder_24h', 'whatsapp', '9876543210')['mode'],
        );
    }

    public function test_an_unapproved_template_cannot_send(): void
    {
        MessageTemplate::create([
            'key' => 'event.reminder_24h', 'name' => 'Reminder', 'channel' => 'whatsapp',
            'category' => 'utility', 'body' => 'x', 'status' => 'draft', 'is_active' => true,
        ]);

        $route = $this->resolver()->resolve('event.reminder_24h', 'whatsapp', '9876543210');

        $this->assertSame(TemplateResolver::MODE_BLOCKED, $route['mode']);
        // The reason separates "not registered" from "waiting on Meta".
        $this->assertSame('template_not_approved', $route['reason']);
    }

    public function test_an_approved_template_without_a_sid_cannot_send(): void
    {
        MessageTemplate::create([
            'key' => 'event.reminder_24h', 'name' => 'Reminder', 'channel' => 'whatsapp',
            'category' => 'utility', 'body' => 'x', 'status' => 'approved',
            'provider_template_id' => null, 'is_active' => true,
        ]);

        $this->assertSame(
            TemplateResolver::MODE_BLOCKED,
            $this->resolver()->resolve('event.reminder_24h', 'whatsapp', '9876543210')['mode'],
        );
    }

    // --- sending ------------------------------------------------------------

    public function test_a_journey_with_no_approved_template_is_held_not_rejected(): void
    {
        $this->booking();
        app(MessageJourneys::class)->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $result = app(MessageJourneys::class)->dispatch();

        // Nothing is thrown at Meta to be rejected — the reason is recorded instead.
        Http::assertNothingSent();
        $this->assertSame(3, $result['skipped']);
        $this->assertSame('no_template_registered', ScheduledMessage::first()->skip_reason);
    }

    public function test_a_journey_sends_as_a_template_with_positional_variables(): void
    {
        $this->approvedTemplate();
        $this->booking();
        app(MessageJourneys::class)->enqueue();
        ScheduledMessage::where('template_key', '!=', 'event.reminder_24h')->delete();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $result = app(MessageJourneys::class)->dispatch();

        $this->assertSame(1, $result['sent']);
        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $parameters = $body['template']['components'][0]['parameters'] ?? [];

            return ($body['type'] ?? null) === 'template'
                && ($body['template']['name'] ?? null) === 'event_reminder_24h'
                && ($body['template']['language']['code'] ?? null) === 'en'
                // Positional {{1}}, in the order JourneyTemplates::variables() returns.
                && ($parameters[0]['text'] ?? null) === 'Routed Show';
        });
    }

    public function test_a_journey_falls_back_to_free_text_inside_an_open_window(): void
    {
        $this->openServiceWindow();
        $this->booking();
        app(MessageJourneys::class)->enqueue();
        ScheduledMessage::query()->update(['send_after' => Carbon::now()->subMinute()]);

        $result = app(MessageJourneys::class)->dispatch();

        $this->assertSame(3, $result['sent']);
        Http::assertSent(fn ($request): bool => ($request->data()['type'] ?? null) === 'text');
    }
}
