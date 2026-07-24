<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\MessageConversation;
use App\Models\MessageLog;
use App\Models\MessagingOptOut;
use App\Models\User;
use App\Support\TwilioSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The inbound webhook is public and can create opt-outs and send replies, so the
 * signature check is the security boundary and gets tested as one. The rest
 * covers the rules that protect a customer: STOP always wins, an opted-out
 * person gets silence, and the reply goes to the right partner's account.
 */
class InboundMessageTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-auth-token';

    private const URL = 'https://haraan.test/api/webhooks/twilio/whatsapp';

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

        config([
            'app.url' => 'https://haraan.test',
            'messaging.webhook.validate_signature' => true,
            'messaging.webhook.url' => '',
            'services.whatsapp.enabled' => true,
            'services.whatsapp.account_sid' => 'ACtest',
            'services.whatsapp.auth_token' => self::TOKEN,
            'services.whatsapp.from' => '+14155238886',
        ]);

        Http::fake(fn () => Http::response(['sid' => 'SM' . uniqid()], 201));
    }

    /** @param array<string, string> $params */
    private function hook(array $params, ?string $signature = null)
    {
        $signature ??= base64_encode(hash_hmac('sha1', $this->payload($params), self::TOKEN, true));

        return $this->withHeaders(['X-Twilio-Signature' => $signature])
            ->post('/api/webhooks/twilio/whatsapp', $params);
    }

    /** @param array<string, string> $params */
    private function payload(array $params): string
    {
        ksort($params);
        $payload = self::URL;

        foreach ($params as $k => $v) {
            $payload .= $k . $v;
        }

        return $payload;
    }

    private function inbound(string $body, string $from = '+919876543210'): array
    {
        return ['From' => 'whatsapp:' . $from, 'Body' => $body, 'MessageSid' => 'SM' . uniqid()];
    }

    // --- security -----------------------------------------------------------

    public function test_a_forged_signature_is_rejected(): void
    {
        $this->hook($this->inbound('stop'), signature: 'obviously-wrong')->assertStatus(403);

        // Nothing happened: no opt-out, nothing recorded, no reply.
        $this->assertSame(0, MessagingOptOut::count());
        $this->assertSame(0, MessageLog::count());
        Http::assertNothingSent();
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $this->hook($this->inbound('hello'), signature: '')->assertStatus(403);
        $this->assertSame(0, MessageLog::count());
    }

    public function test_it_fails_closed_when_no_auth_token_is_configured(): void
    {
        // Better to reject real traffic than to accept forged traffic.
        config(['services.whatsapp.auth_token' => '']);

        $this->hook($this->inbound('hello'))->assertStatus(403);
        $this->assertSame(0, MessageLog::count());
    }

    public function test_a_tampered_body_invalidates_the_signature(): void
    {
        $params = $this->inbound('hello');
        $signature = base64_encode(hash_hmac('sha1', $this->payload($params), self::TOKEN, true));

        $params['Body'] = 'stop';   // swapped after signing

        $this->hook($params, signature: $signature)->assertStatus(403);
        $this->assertSame(0, MessagingOptOut::count());
    }

    public function test_a_valid_signature_is_accepted(): void
    {
        $this->hook($this->inbound('hello there'))->assertStatus(204);

        $this->assertSame(1, MessageLog::where('direction', 'in')->count());
    }

    public function test_the_validator_matches_twilios_documented_algorithm(): void
    {
        // Params are sorted by name and concatenated key+value onto the URL.
        $params = ['B' => 'two', 'A' => 'one'];
        $expected = base64_encode(hash_hmac('sha1', 'https://x.test/hookAoneBtwo', 'tok', true));

        $this->assertTrue(TwilioSignature::isValid($expected, 'https://x.test/hook', $params, 'tok'));
        $this->assertFalse(TwilioSignature::isValid($expected, 'https://x.test/other', $params, 'tok'));
    }

    // --- compliance ---------------------------------------------------------

    public function test_stop_opts_the_sender_out_globally_and_confirms(): void
    {
        $this->hook($this->inbound('STOP'))->assertStatus(204);

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
        $this->hook($this->inbound('please stop by the venue at 6'))->assertStatus(204);

        $this->assertSame(0, MessagingOptOut::count(), 'a sentence containing "stop" is not an opt-out');
    }

    public function test_start_resubscribes(): void
    {
        MessagingOptOut::record('whatsapp', '+919876543210');

        $this->hook($this->inbound('START'))->assertStatus(204);

        $this->assertSame(0, MessagingOptOut::count());
    }

    public function test_an_opted_out_sender_gets_no_rule_reply(): void
    {
        MessagingOptOut::record('whatsapp', '+919876543210');
        AutomationRule::create([
            'name' => 'Tickets', 'trigger_type' => 'keyword', 'keywords' => ['ticket'],
            'reply_body' => 'Here are your tickets.',
        ]);

        $this->hook($this->inbound('where is my ticket'))->assertStatus(204);

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

        $this->hook($this->inbound('is there parking?'))->assertStatus(204);

        Http::assertSent(fn ($request) => str_contains((string) ($request['Body'] ?? ''), 'north gate'));
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

        // Attribution comes from the last conversation with this number.
        MessageConversation::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp',
            'recipient' => '+919876543210', 'category' => 'utility',
            'opened_at' => Carbon::now()->subHour(), 'expires_at' => Carbon::now()->addHours(23),
        ]);

        $this->hook($this->inbound('anything at all'))->assertStatus(204);

        Http::assertSent(fn ($request) => str_contains((string) ($request['Body'] ?? ''), 'call you back'));
    }

    public function test_an_unmatched_message_gets_no_reply(): void
    {
        $this->hook($this->inbound('mumble mumble'))->assertStatus(204);

        Http::assertNothingSent();
        $this->assertSame(1, MessageLog::where('direction', 'in')->count());
    }

    // --- ledger -------------------------------------------------------------

    public function test_inbound_opens_a_service_window_and_is_attributed(): void
    {
        MessageConversation::create([
            'partner_id' => $this->partner->id, 'channel' => 'whatsapp',
            'recipient' => '+919876543210', 'category' => 'utility',
            'opened_at' => Carbon::now()->subHour(), 'expires_at' => Carbon::now()->addHours(23),
        ]);

        $this->hook($this->inbound('hello'))->assertStatus(204);

        $log = MessageLog::where('direction', 'in')->first();
        $this->assertSame($this->partner->id, $log->partner_id);
        $this->assertSame('service', $log->category);
        $this->assertSame(MessageLog::STATUS_RECEIVED, $log->status);

        // A service window is opened for the reply to ride inside.
        $this->assertTrue(
            MessageConversation::where('category', 'service')->where('recipient', '+919876543210')->exists()
        );
    }
}
