<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\MessageConversation;
use App\Models\MessageLog;
use App\Models\MessagingOptOut;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Inbound WhatsApp through Meta's Cloud API. The endpoint is public and can create
 * opt-outs and send replies, so the signature is the security boundary. The rest
 * covers what protects a customer: STOP always wins, an opted-out person gets
 * silence, and the reply is billed to the right partner.
 *
 * Note the shape difference from Instagram on the same URL: WhatsApp arrives as
 * entry[].changes[] with field=messages, Instagram as entry[].messaging[].
 */
class InboundMessageTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'meta-app-secret';

    private const FROM = '919876543210';

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Inbound Partner',
            'email' => 'inbound-partner@example.test',
            'password' => bcrypt('secret'),
            'role' => 'PARTNER',
            'status' => 'active',
            'partner_type' => 'event',
        ]);

        // Auto-replies are a paid feature since phase 2a. Compliance replies
        // (STOP/START/HELP) are not, which several tests below rely on.
        $plan = PartnerPlan::create([
            'code' => 'growth', 'name' => 'Growth', 'price_inr' => 499,
            'included_conversations' => 500,
            'features' => [PartnerPlan::FEATURE_INBOUND],
        ]);

        PartnerSubscription::create([
            'partner_id' => $this->partner->id,
            'plan_id' => $plan->id,
            'status' => PartnerSubscription::STATUS_ACTIVE,
            'current_period_end' => Carbon::now()->addMonth(),
        ]);

        config([
            'services.instagram.app_secret' => self::SECRET,
            'services.instagram.validate_signature' => true,
            'services.whatsapp.enabled' => true,
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'meta-token',
        ]);

        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]], 200));
    }

    /** @param array<string, mixed> $payload */
    private function hook(array $payload, ?string $signature = null)
    {
        $raw = json_encode($payload);
        $signature ??= 'sha256=' . hash_hmac('sha256', $raw, self::SECRET);

        return $this->call(
            'POST', '/api/webhooks/meta', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $raw,
        );
    }

    /** A WhatsApp Cloud inbound-message payload. */
    private function inbound(string $body, string $from = self::FROM): array
    {
        return ['entry' => [['changes' => [[
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'messages' => [[
                    'from' => $from,
                    'id' => 'wamid.' . uniqid(),
                    'type' => 'text',
                    'text' => ['body' => $body],
                ]],
            ],
        ]]]]];
    }

    // --- security -----------------------------------------------------------

    public function test_a_forged_signature_is_rejected(): void
    {
        $this->hook($this->inbound('stop'), 'sha256=deadbeef')->assertStatus(403);

        $this->assertSame(0, MessagingOptOut::count());
        $this->assertSame(0, MessageLog::count());
        Http::assertNothingSent();
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $this->hook($this->inbound('hello'), '')->assertStatus(403);
        $this->assertSame(0, MessageLog::count());
    }

    public function test_it_fails_closed_when_no_app_secret_is_configured(): void
    {
        config(['services.instagram.app_secret' => '']);

        $this->hook($this->inbound('hello'))->assertStatus(403);
        $this->assertSame(0, MessageLog::count());
    }

    public function test_a_tampered_body_invalidates_the_signature(): void
    {
        $payload = $this->inbound('hello');
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), self::SECRET);

        $this->hook($this->inbound('stop'), $signature)->assertStatus(403);
        $this->assertSame(0, MessagingOptOut::count());
    }

    public function test_a_valid_signature_is_accepted(): void
    {
        $this->hook($this->inbound('hello there'))->assertStatus(200);

        $this->assertSame(1, MessageLog::where('direction', 'in')->count());
    }

    // --- compliance ---------------------------------------------------------

    public function test_stop_opts_the_sender_out_globally_and_confirms(): void
    {
        $this->hook($this->inbound('STOP'))->assertStatus(200);

        $optOut = MessagingOptOut::first();
        $this->assertNotNull($optOut);
        // Global, not per-partner: they messaged one number and can't tell that
        // several organisers share it.
        $this->assertNull($optOut->partner_id);
        $this->assertSame('stop_keyword', $optOut->reason);

        // The confirmation still goes out — it's the one message someone who
        // said stop still needs.
        Http::assertSentCount(1);
    }

    public function test_stop_is_matched_exactly_not_loosely(): void
    {
        $this->hook($this->inbound('please stop by the venue at 6'))->assertStatus(200);

        $this->assertSame(0, MessagingOptOut::count(), 'a sentence containing "stop" is not an opt-out');
    }

    public function test_start_resubscribes(): void
    {
        MessagingOptOut::record('whatsapp', '+' . self::FROM);

        $this->hook($this->inbound('START'))->assertStatus(200);

        $this->assertSame(0, MessagingOptOut::count());
    }

    public function test_an_opted_out_sender_gets_no_rule_reply(): void
    {
        MessagingOptOut::record('whatsapp', '+' . self::FROM);
        AutomationRule::create([
            'name' => 'Tickets', 'trigger_type' => 'keyword', 'keywords' => ['ticket'],
            'reply_body' => 'Here are your tickets.',
        ]);

        $this->hook($this->inbound('where is my ticket'))->assertStatus(200);

        Http::assertNothingSent();
        // Still recorded, though — we heard them, we just didn't reply.
        $this->assertSame(1, MessageLog::where('direction', 'in')->count());
    }

    // --- auto-reply ---------------------------------------------------------

    public function test_a_keyword_rule_replies(): void
    {
        AutomationRule::create([
            'name' => 'Parking', 'trigger_type' => 'keyword', 'keywords' => ['parking', 'park'],
            'reply_body' => 'Parking is free at the north gate.',
        ]);

        $this->hook($this->inbound('is there parking?'))->assertStatus(200);

        Http::assertSent(fn ($request): bool
            => str_contains((string) ($request->data()['text']['body'] ?? ''), 'north gate'));
    }

    public function test_a_partner_rule_beats_the_platform_default(): void
    {
        AutomationRule::create([
            'name' => 'Platform', 'trigger_type' => 'fallback', 'partner_id' => null,
            'reply_body' => 'Generic Haraan reply.', 'priority' => 1,
        ]);
        AutomationRule::create([
            'name' => 'Partner', 'trigger_type' => 'fallback', 'partner_id' => $this->partner->id,
            'reply_body' => 'The venue will call you back.', 'priority' => 99,
        ]);

        // Attribution on the shared number comes from the last conversation with it.
        MessageConversation::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp',
            'recipient' => '+' . self::FROM, 'category' => 'utility',
            'opened_at' => Carbon::now()->subHour(), 'expires_at' => Carbon::now()->addHours(23),
        ]);

        $this->hook($this->inbound('anything at all'))->assertStatus(200);

        Http::assertSent(fn ($request): bool
            => str_contains((string) ($request->data()['text']['body'] ?? ''), 'call you back'));
    }

    public function test_an_unmatched_message_gets_no_reply(): void
    {
        $this->hook($this->inbound('mumble mumble'))->assertStatus(200);

        Http::assertNothingSent();
        $this->assertSame(1, MessageLog::where('direction', 'in')->count());
    }

    // --- ledger -------------------------------------------------------------

    public function test_inbound_opens_a_service_window_and_is_attributed(): void
    {
        MessageConversation::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp',
            'recipient' => '+' . self::FROM, 'category' => 'utility',
            'opened_at' => Carbon::now()->subHour(), 'expires_at' => Carbon::now()->addHours(23),
        ]);

        $this->hook($this->inbound('hello'))->assertStatus(200);

        $log = MessageLog::where('direction', 'in')->first();
        $this->assertSame($this->partner->id, $log->partner_id);
        $this->assertSame('service', $log->category);
        $this->assertSame(MessageLog::STATUS_RECEIVED, $log->status);

        // A service window is opened for the reply to ride inside.
        $this->assertTrue(
            MessageConversation::where('category', 'service')->where('recipient', '+' . self::FROM)->exists()
        );
    }

    public function test_the_recipient_is_normalised_to_e164(): void
    {
        // Meta reports `from` without a plus; the ledger and WhatsAppService both
        // key on E.164, so they have to agree or every reply opens a new window.
        $this->hook($this->inbound('hello'))->assertStatus(200);

        $this->assertSame('+' . self::FROM, MessageLog::where('direction', 'in')->first()->recipient);
    }

    public function test_status_callbacks_are_ignored(): void
    {
        // Delivery/read receipts arrive on the same field with no `messages` key.
        $payload = ['entry' => [['changes' => [[
            'field' => 'messages',
            'value' => ['statuses' => [['id' => 'wamid.x', 'status' => 'delivered']]],
        ]]]]];

        $this->hook($payload)->assertStatus(200);

        $this->assertSame(0, MessageLog::count());
    }
}
