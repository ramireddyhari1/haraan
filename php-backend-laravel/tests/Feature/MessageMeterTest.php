<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MessageConversation;
use App\Models\MessageLog;
use App\Models\MessagingUsage;
use App\Models\User;
use App\Services\MessageMeter;
use App\Services\WhatsAppService;
use App\Support\MessageContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The messaging ledger decides what a partner gets billed for, so these cover the
 * rules that must not drift: what counts as one conversation, what a failure does
 * (nothing), and who a send belongs to.
 */
class MessageMeterTest extends TestCase
{
    use RefreshDatabase;

    private int $partnerId;

    protected function setUp(): void
    {
        parent::setUp();

        // A real partner row: message_log.partner_id is a foreign key, and the meter
        // swallows its own failures (a ledger error must never break a send), so a
        // dangling id would silently record nothing at all.
        $this->partnerId = (int) User::create([
            'name' => 'Test Partner',
            'email' => 'meter-partner@example.test',
            'password' => bcrypt('secret'),
            'role' => 'PARTNER',
            'status' => 'active',
            'partner_type' => 'event',
        ])->id;

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'meta-token',
        ]);
    }

    private function fakeGraph(bool $fails = false): void
    {
        Http::fake(fn () => $fails
            ? Http::response(['error' => ['message' => 'template not approved', 'code' => 132001]], 400)
            : Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]], 200));
    }

    private function partnerContext(string $category = MessageContext::UTILITY): MessageContext
    {
        return new MessageContext($this->partnerId, $category, 'booking.ticket', 'booking', 999);
    }

    public function test_messages_to_one_recipient_inside_the_window_share_a_conversation(): void
    {
        $this->fakeGraph();
        $wa = app(WhatsAppService::class);

        $wa->sendMessage('9876543210', 'first', $this->partnerContext());
        // Same human, different formatting — must not open (or bill) a second window.
        $wa->sendMessage('+919876543210', 'second', $this->partnerContext());

        $this->assertSame(1, MessageConversation::count());
        $this->assertSame(2, (int) MessageConversation::first()->message_count);
        $this->assertSame('+919876543210', MessageConversation::first()->recipient);
    }

    public function test_a_different_category_opens_its_own_conversation(): void
    {
        $this->fakeGraph();
        $wa = app(WhatsAppService::class);

        $wa->sendMessage('9876543210', 'your ticket', $this->partnerContext());
        $wa->sendMessage('9876543210', 'a promo', $this->partnerContext(MessageContext::MARKETING));

        // WhatsApp prices per category, so utility and marketing are two conversations.
        $this->assertSame(2, MessageConversation::count());
    }

    public function test_an_expired_window_opens_a_new_conversation(): void
    {
        $this->fakeGraph();
        $wa = app(WhatsAppService::class);

        $wa->sendMessage('9876543210', 'day one', $this->partnerContext());

        Carbon::setTestNow(Carbon::now()->addHours(25));
        $wa->sendMessage('9876543210', 'day two', $this->partnerContext());
        Carbon::setTestNow();

        $this->assertSame(2, MessageConversation::count());
        $this->assertSame(2, (int) MessagingUsage::first()->conversations_opened);
    }

    public function test_a_failed_send_is_logged_but_never_billed(): void
    {
        $this->fakeGraph(fails: true);

        $this->assertFalse(app(WhatsAppService::class)->sendMessage('9876543210', 'nope', $this->partnerContext()));

        $this->assertSame(0, MessageConversation::count(), 'a rejected message costs nothing');
        $this->assertSame(MessageLog::STATUS_FAILED, MessageLog::first()->status);
        $this->assertStringContainsString('template not approved', (string) MessageLog::first()->error);

        $usage = MessagingUsage::first();
        $this->assertSame(0, (int) $usage->conversations_opened);
        $this->assertSame(0, (int) $usage->messages_sent);
        $this->assertSame(1, (int) $usage->messages_failed);
    }

    public function test_a_send_that_never_leaves_the_building_is_still_recorded(): void
    {
        // The silent-failure case: the channel is off, so nothing is attempted and
        // today nothing would be visible anywhere.
        config(['services.whatsapp.enabled' => false]);

        app(WhatsAppService::class)->sendMessage('9876543210', 'ticket', $this->partnerContext());

        $this->assertSame(MessageLog::STATUS_DISABLED, MessageLog::first()->status);
        $this->assertSame(0, MessageConversation::count());
    }

    public function test_a_send_without_context_belongs_to_no_partner(): void
    {
        $this->fakeGraph();

        // Login OTPs are Haraan's own traffic and must never land on a partner's bill.
        app(WhatsAppService::class)->sendMessage('9000000001', 'your OTP is 123456');

        $log = MessageLog::first();
        $this->assertNull($log->partner_id);
        $this->assertSame(MessageContext::AUTHENTICATION, $log->category);
    }

    public function test_usage_is_reported_per_partner(): void
    {
        $this->fakeGraph();
        $wa = app(WhatsAppService::class);

        $wa->sendMessage('9876543210', 'one', $this->partnerContext());
        $wa->sendMessage('9876543211', 'two', $this->partnerContext());
        $wa->sendMessage('9000000001', 'platform otp');

        $this->assertSame(
            ['conversations' => 2, 'messages' => 2, 'failed' => 0],
            app(MessageMeter::class)->usageThisPeriod($this->partnerId),
        );
    }

    public function test_an_inbound_reply_extends_the_conversation_our_send_opened(): void
    {
        $this->fakeGraph();
        $meter = app(MessageMeter::class);

        // Outbound holds bare digits off a booking; the webhook hands the same
        // person back in the provider's format. If the two are stored as written,
        // one customer becomes two conversations — and two conversations is two
        // charges for one exchange.
        app(WhatsAppService::class)->sendMessage('9876543210', 'ticket', $this->partnerContext());
        $meter->recordInbound('whatsapp', '919876543210', $this->partnerId, 'wamid.in', 'thanks');

        $this->assertSame(
            ['+919876543210'],
            MessageConversation::query()->pluck('recipient')->unique()->values()->all(),
        );
    }

    public function test_an_instagram_scoped_id_is_never_run_through_the_phone_rules(): void
    {
        // Not a number, and mangling it into one would address a stranger.
        app(MessageMeter::class)->recordInbound('instagram', '17841400000000000', $this->partnerId);

        $this->assertSame('17841400000000000', MessageConversation::first()->recipient);
    }

    public function test_the_provider_id_is_captured_for_cost_backfill(): void
    {
        $this->fakeGraph();

        app(WhatsAppService::class)->sendMessage('9876543210', 'ticket', $this->partnerContext());

        // Cost isn't known at send time; the wamid is what a later backfill joins on.
        $log = MessageLog::first();
        $this->assertStringStartsWith('wamid.', (string) $log->provider_message_id);
        $this->assertNull($log->cost_micros);
    }
}
