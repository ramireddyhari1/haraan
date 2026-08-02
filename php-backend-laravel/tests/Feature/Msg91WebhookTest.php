<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MessageConversation;
use App\Models\MessageLog;
use App\Models\MessagingOptOut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Inbound WhatsApp through MSG91 as the BSP.
 *
 * MSG91 signs nothing, so a shared header secret is the entire security boundary —
 * and this endpoint can create opt-outs and open billable conversations, which is
 * exactly what someone would forge. That's most of what's covered here.
 *
 * The other half is the trap: delivery reports for OUR OWN outgoing messages
 * arrive on this same URL. Handling one as an inbound message would open a
 * 24-hour conversation nobody opened, bill it, and make free text legal to a
 * customer who never wrote to us.
 */
class Msg91WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a-long-random-shared-secret';

    private const URL = '/api/webhooks/msg91/whatsapp';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.msg91.webhook_token' => self::TOKEN,
            'services.whatsapp.msg91.webhook_header' => 'X-Haraan-Token',
            'services.whatsapp.enabled' => false,
        ]);

        Http::fake();
    }

    /** @param array<string, mixed> $payload */
    private function deliver(array $payload, ?string $token = self::TOKEN)
    {
        return $this->withHeaders($token === null ? [] : ['X-Haraan-Token' => $token])
            ->postJson(self::URL, $payload);
    }

    /** @return array<string, mixed> */
    private function inboundEvent(string $text = 'hello', array $overrides = []): array
    {
        return array_merge([
            'eventName' => 'inbound',
            'customerNumber' => '919876543210',
            'integratedNumber' => '15554750727',
            'contentType' => 'text',
            'text' => $text,
            'uuid' => 'evt-' . uniqid(),
        ], $overrides);
    }

    public function test_a_get_answers_a_reachability_check_without_leaking_state(): void
    {
        $response = $this->getJson(self::URL)->assertOk();

        $response->assertJson(['ok' => true, 'provider' => 'msg91']);
        // Whether a token is configured is a hint for an attacker and useless to
        // the operator, who has the logs.
        $response->assertJsonMissingPath('webhook_token');
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_the_reachability_check_cannot_be_used_to_deliver_anything(): void
    {
        // A GET must never be a side door around the token check.
        $this->getJson(self::URL . '?customerNumber=919876543210&text=STOP')->assertOk();

        $this->assertFalse(MessagingOptOut::blocks('whatsapp', '+919876543210', null));
        $this->assertSame(0, MessageConversation::query()->count());
    }

    // --- the security boundary ----------------------------------------------

    public function test_a_delivery_with_no_token_is_refused(): void
    {
        $this->deliver($this->inboundEvent(), token: null)->assertForbidden();

        $this->assertSame(0, MessageConversation::query()->count());
    }

    public function test_a_delivery_with_the_wrong_token_is_refused(): void
    {
        $this->deliver($this->inboundEvent(), token: 'guessed')->assertForbidden();

        $this->assertSame(0, MessageConversation::query()->count());
    }

    public function test_the_endpoint_fails_closed_when_no_token_is_configured(): void
    {
        // The dangerous state: credentials half-set on a fresh box. An endpoint
        // that accepts everything until someone remembers to configure it is worse
        // than one that accepts nothing, because nobody notices the first.
        config(['services.whatsapp.msg91.webhook_token' => '']);

        $this->deliver($this->inboundEvent(), token: '')->assertForbidden();
        $this->deliver($this->inboundEvent())->assertForbidden();
    }

    // --- inbound messages ----------------------------------------------------

    public function test_an_inbound_message_opens_a_service_window_keyed_on_e164(): void
    {
        $this->deliver($this->inboundEvent('is parking free?'))->assertOk();

        $conversation = MessageConversation::query()->first();

        $this->assertNotNull($conversation);
        // Normalised, so this is the same recipient an outbound send is metered
        // against — otherwise one customer is two conversations and two bills.
        $this->assertSame('+919876543210', $conversation->recipient);
        $this->assertSame('service', $conversation->category);
    }

    public function test_a_stop_keyword_records_an_opt_out(): void
    {
        $this->deliver($this->inboundEvent('STOP'))->assertOk();

        $this->assertTrue(MessagingOptOut::blocks('whatsapp', '+919876543210', null));
    }

    public function test_the_body_is_read_from_stringified_content_json(): void
    {
        // MSG91's other spelling: the text arrives as escaped JSON in `content`.
        $this->deliver($this->inboundEvent('', [
            'text' => null,
            'content' => '{"text":"STOP"}',
        ]))->assertOk();

        $this->assertTrue(MessagingOptOut::blocks('whatsapp', '+919876543210', null));
    }

    public function test_a_batch_under_a_data_key_is_unwrapped(): void
    {
        $this->deliver([
            'data' => [
                $this->inboundEvent('hello there'),
                $this->inboundEvent('and again', ['customerNumber' => '919000000001']),
            ],
        ])->assertOk();

        $this->assertSame(
            ['+919876543210', '+919000000001'],
            MessageConversation::query()->orderBy('id')->pluck('recipient')->all(),
        );
    }

    // --- the delivery-report trap --------------------------------------------

    public function test_a_delivery_report_never_opens_a_conversation(): void
    {
        $this->deliver([
            'eventName' => 'DELIVERY_REPORT',
            'direction' => 'outbound',
            'customerNumber' => '919876543210',
            'integratedNumber' => '15554750727',
            'content' => '{"text":"You are confirmed for Sunburn Arena"}',
            'uuid' => 'dlr-1',
        ])->assertOk();

        $this->assertSame(0, MessageConversation::query()->count());
        $this->assertSame(0, MessageLog::query()->where('direction', 'in')->count());
    }

    public function test_an_event_named_as_a_report_is_not_treated_as_inbound(): void
    {
        // No `direction` at all — the event name is the only clue, and "when in
        // doubt, not inbound" is the safe way to be wrong.
        $this->deliver($this->inboundEvent('STOP', [
            'eventName' => 'On Inbound Report Received',
        ]))->assertOk();

        $this->assertFalse(MessagingOptOut::blocks('whatsapp', '+919876543210', null));
        $this->assertSame(0, MessageConversation::query()->count());
    }

    /**
     * MSG91's real default payload, captured from a live delivery on 2026-08-01.
     *
     * Kept verbatim (minus the noisier media fields) because two of its properties
     * are load-bearing and neither is documented: it carries `webhookType` rather
     * than `direction`, and `dryRun` marks a Test Run as not-a-real-message.
     *
     * @return array<string, mixed>
     */
    private function msg91DefaultPayload(array $overrides = []): array
    {
        return array_merge([
            'crqid' => 'testing_crqid',
            'companyId' => '444370',
            'customerNumber' => '919876543210',
            'requestId' => '71152f6355a449218e19cd8f0e3bca8d',
            'reason' => 'Sample reason for webhook test',
            'uuid' => 'wamid.sample-' . uniqid(),
            'integratedNumber' => '15554750727',
            'templateName' => 'sample_template',
            'contentType' => 'text',
            'text' => 'Hello, this is a sample message for webhook test',
            'webhookType' => 'inbound',
            'ts' => '2026-08-01T22:37:17+05:30',
        ], $overrides);
    }

    public function test_msg91s_own_default_payload_is_understood_without_customisation(): void
    {
        // The operator shouldn't have to hand-craft a payload in the panel for this
        // to work — getting that JSON subtly wrong is a silent no-op.
        $this->deliver($this->msg91DefaultPayload())->assertOk();

        $conversation = MessageConversation::query()->first();

        $this->assertNotNull($conversation);
        $this->assertSame('+919876543210', $conversation->recipient);
    }

    public function test_webhook_type_outbound_is_a_report_even_with_no_direction_field(): void
    {
        // MSG91's default payload has no `direction` at all. Reading only that field
        // would let every delivery report through as a customer message, opening —
        // and billing — a 24h conversation off the back of our own outgoing ticket.
        $this->deliver($this->msg91DefaultPayload([
            'webhookType' => 'outbound',
            'text' => 'STOP',
        ]))->assertOk();

        $this->assertSame(0, MessageConversation::query()->count());
        $this->assertFalse(MessagingOptOut::blocks('whatsapp', '+919876543210', null));
    }

    public function test_a_test_run_is_acknowledged_but_never_recorded(): void
    {
        // MSG91's "Test Run" posts a complete sample event that differs from a real
        // one only by this flag. Without the guard, testing your webhook config
        // creates a real conversation against a sample number.
        $this->deliver($this->msg91DefaultPayload(['dryRun' => true, 'text' => 'STOP']))->assertOk();

        $this->assertSame(0, MessageConversation::query()->count());
        $this->assertFalse(MessagingOptOut::blocks('whatsapp', '+919876543210', null));
    }

    public function test_a_masked_customer_number_is_refused_rather_than_half_stored(): void
    {
        // Their sample data masks the number ("9181588XXXXX"). Seven usable digits
        // is not a phone number, and storing it would put a junk recipient in the
        // ledger that no future message could ever match.
        $this->deliver($this->msg91DefaultPayload(['customerNumber' => '9181588XXXXX']))->assertOk();

        $this->assertSame(0, MessageConversation::query()->count());
    }

    // --- retries and noise ---------------------------------------------------

    public function test_a_redelivered_event_is_only_processed_once(): void
    {
        $event = $this->inboundEvent('hello', ['uuid' => 'evt-fixed']);

        $this->deliver($event)->assertOk();
        $this->deliver($event)->assertOk();

        $this->assertSame(1, MessageConversation::query()->count());
        $this->assertSame(1, MessageLog::query()->where('direction', 'in')->count());
    }

    public function test_a_sticker_with_no_text_is_acknowledged_and_ignored(): void
    {
        // 200, not 4xx: there is nothing wrong with the delivery, there is just
        // no keyword to act on — and a non-2xx would make MSG91 retry it forever.
        $this->deliver($this->inboundEvent('', ['text' => null, 'contentType' => 'sticker']))->assertOk();

        $this->assertSame(0, MessageConversation::query()->count());
    }

    public function test_a_body_that_is_not_json_is_rejected_without_a_retry_storm(): void
    {
        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HARAAN_TOKEN' => self::TOKEN,
        ], 'not json')->assertStatus(400);
    }
}
