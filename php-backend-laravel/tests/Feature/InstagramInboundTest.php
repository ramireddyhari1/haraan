<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\ChannelConnection;
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
 * Instagram DMs. Two failure modes dominate: a forged webhook (it can make us
 * message people), and the echo loop — Meta delivers our own outgoing messages
 * back to us, so replying to one replies forever.
 */
class InstagramInboundTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'meta-app-secret';

    private const ACCOUNT = '17841400000000000';

    private User $partner;

    private ChannelConnection $connection;

    /** What the faked Graph API returns; tests mutate these. */
    private int $graphStatus = 200;

    /** @var array<string, mixed> */
    private array $graphBody = ['message_id' => 'mid.123'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'IG Partner', 'email' => 'ig-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);

        $plan = PartnerPlan::create([
            'code' => 'pro', 'name' => 'Pro', 'price_inr' => 999,
            'included_conversations' => 500,
            'features' => [PartnerPlan::FEATURE_INBOUND, PartnerPlan::FEATURE_INSTAGRAM],
        ]);

        PartnerSubscription::create([
            'partner_id' => $this->partner->id, 'plan_id' => $plan->id,
            'status' => PartnerSubscription::STATUS_ACTIVE,
            'current_period_end' => Carbon::now()->addMonth(),
        ]);

        $this->connection = ChannelConnection::create([
            'partner_id' => $this->partner->id, 'channel' => 'instagram',
            'external_id' => self::ACCOUNT, 'username' => 'thevenue',
            'access_token' => 'page-token', 'status' => ChannelConnection::STATUS_ACTIVE,
        ]);

        config([
            'services.instagram.app_secret' => self::SECRET,
            'services.instagram.verify_token' => 'my-verify-token',
            'services.instagram.validate_signature' => true,
        ]);

        // One stub for the whole test: a second Http::fake() APPENDS, and the first
        // matching stub wins, so a per-test override would silently never apply.
        Http::fake(fn () => Http::response($this->graphBody, $this->graphStatus));
    }

    /** @param array<string, mixed> $payload */
    private function webhook(array $payload, ?string $signature = null)
    {
        $raw = json_encode($payload);
        $signature ??= 'sha256=' . hash_hmac('sha256', $raw, self::SECRET);

        return $this->call(
            'POST', '/api/webhooks/meta/instagram', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $raw,
        );
    }

    /** @param array<string, mixed> $message */
    private function dm(array $message = [], string $account = self::ACCOUNT): array
    {
        return ['entry' => [['messaging' => [[
            'sender' => ['id' => 'igsid_999'],
            'recipient' => ['id' => $account],
            'message' => array_merge(['mid' => 'mid.abc', 'text' => 'hello'], $message),
        ]]]]];
    }

    // --- handshake ----------------------------------------------------------

    public function test_the_subscription_handshake_echoes_the_challenge(): void
    {
        $this->get('/api/webhooks/meta/instagram?hub_verify_token=my-verify-token&hub_challenge=abc123')
            ->assertStatus(200)
            ->assertSee('abc123');
    }

    public function test_the_handshake_rejects_a_wrong_verify_token(): void
    {
        $this->get('/api/webhooks/meta/instagram?hub_verify_token=wrong&hub_challenge=abc123')
            ->assertStatus(403);
    }

    // --- security -----------------------------------------------------------

    public function test_a_forged_signature_is_rejected(): void
    {
        $this->webhook($this->dm(), 'sha256=deadbeef')->assertStatus(403);

        $this->assertSame(0, MessageLog::count());
        Http::assertNothingSent();
    }

    public function test_it_fails_closed_with_no_app_secret(): void
    {
        config(['services.instagram.app_secret' => '']);

        $this->webhook($this->dm())->assertStatus(403);
        $this->assertSame(0, MessageLog::count());
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($this->dm(['text' => 'hello'])), self::SECRET);

        $this->webhook($this->dm(['text' => 'stop']), $signature)->assertStatus(403);
        $this->assertSame(0, MessagingOptOut::count());
    }

    // --- the echo loop ------------------------------------------------------

    public function test_our_own_echoed_message_is_ignored(): void
    {
        // Meta delivers our outgoing DMs back to us. Answering one would produce
        // another echo, and so on, out of the partner's account.
        AutomationRule::create([
            'name' => 'Catch-all', 'trigger_type' => 'fallback',
            'reply_body' => 'Thanks for the message!',
        ]);

        $this->webhook($this->dm(['is_echo' => true]))->assertStatus(200);

        Http::assertNothingSent();
        $this->assertSame(0, MessageLog::count());
    }

    public function test_non_message_events_are_ignored(): void
    {
        $payload = ['entry' => [['messaging' => [[
            'sender' => ['id' => 'igsid_999'],
            'recipient' => ['id' => self::ACCOUNT],
            'read' => ['watermark' => 1234567890],
        ]]]]];

        $this->webhook($payload)->assertStatus(200);
        $this->assertSame(0, MessageLog::count());
    }

    public function test_an_attachment_with_no_text_is_ignored(): void
    {
        $this->webhook($this->dm(['text' => '']))->assertStatus(200);

        $this->assertSame(0, MessageLog::count());
    }

    // --- routing ------------------------------------------------------------

    public function test_a_dm_is_recorded_and_attributed_to_the_account_owner(): void
    {
        $this->webhook($this->dm())->assertStatus(200);

        $log = MessageLog::where('direction', 'in')->first();
        $this->assertNotNull($log);
        // Exact attribution — the account maps to one partner, no heuristic.
        $this->assertSame($this->partner->id, $log->partner_id);
        $this->assertSame('instagram', $log->channel);
    }

    public function test_a_dm_to_an_unlinked_account_is_ignored(): void
    {
        $this->webhook($this->dm([], '17841499999999999'))->assertStatus(200);

        $this->assertSame(0, MessageLog::count());
        Http::assertNothingSent();
    }

    public function test_a_keyword_rule_replies_through_the_linked_account(): void
    {
        AutomationRule::create([
            'name' => 'Tickets', 'channel' => 'instagram', 'trigger_type' => 'keyword',
            'keywords' => ['ticket'], 'reply_body' => 'Grab tickets at haraan.app',
        ]);

        $this->webhook($this->dm(['text' => 'how do i get a ticket?']))->assertStatus(200);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return str_contains($request->url(), self::ACCOUNT . '/messages')
                && ($body['recipient']['id'] ?? null) === 'igsid_999'
                && str_contains((string) ($body['message']['text'] ?? ''), 'haraan.app');
        });
    }

    public function test_stop_opts_out_on_instagram_too(): void
    {
        $this->webhook($this->dm(['text' => 'STOP']))->assertStatus(200);

        $this->assertSame(1, MessagingOptOut::where('channel', 'instagram')->count());
    }

    public function test_a_partner_without_the_instagram_feature_gets_no_auto_reply(): void
    {
        PartnerSubscription::query()->update(['status' => PartnerSubscription::STATUS_CANCELLED]);
        AutomationRule::create([
            'name' => 'Catch-all', 'channel' => 'instagram', 'trigger_type' => 'fallback',
            'reply_body' => 'Thanks!',
        ]);

        $this->webhook($this->dm())->assertStatus(200);

        Http::assertNothingSent();
        // Still recorded: we heard them, we just aren't answering.
        $this->assertSame(1, MessageLog::where('direction', 'in')->count());
    }

    public function test_a_dead_token_marks_the_connection_as_broken(): void
    {
        $this->graphStatus = 401;
        $this->graphBody = ['error' => ['message' => 'Invalid OAuth access token']];
        AutomationRule::create([
            'name' => 'Catch-all', 'channel' => 'instagram', 'trigger_type' => 'fallback',
            'reply_body' => 'Thanks!',
        ]);

        $this->webhook($this->dm())->assertStatus(200);

        // Surfaced where an admin will look, not just in a log line.
        $this->assertSame(ChannelConnection::STATUS_ERROR, $this->connection->fresh()->status);
    }

    public function test_the_access_token_is_encrypted_at_rest(): void
    {
        $stored = \DB::table('channel_connections')->where('id', $this->connection->id)->value('access_token');

        $this->assertNotSame('page-token', $stored, 'a page token can read and send DMs');
        $this->assertSame('page-token', $this->connection->fresh()->access_token);
    }
}
