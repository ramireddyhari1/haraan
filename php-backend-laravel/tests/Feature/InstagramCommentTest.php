<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\ChannelConnection;
use App\Models\InstagramCommentReply;
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
 * Comment-to-DM. Meta permits exactly ONE private reply per comment and
 * redelivers webhooks, so "reply once and only once" is a compliance rule, not
 * just good manners — it gets the hardest tests here.
 */
class InstagramCommentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'meta-app-secret';

    private const ACCOUNT = '17841400000000000';

    private User $partner;

    private ChannelConnection $connection;

    private int $graphStatus = 200;

    /** @var array<string, mixed> */
    private array $graphBody = ['message_id' => 'mid.123'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'name' => 'Comment Partner', 'email' => 'comment-partner@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);

        $plan = PartnerPlan::create([
            'code' => 'pro', 'name' => 'Pro', 'price_inr' => 999,
            'included_conversations' => 500,
            'features' => [PartnerPlan::FEATURE_INSTAGRAM, PartnerPlan::FEATURE_INBOUND],
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
            'services.instagram.validate_signature' => true,
        ]);

        Http::fake(fn () => Http::response($this->graphBody, $this->graphStatus));
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

    /** @param array<string, mixed> $value */
    private function comment(array $value = []): array
    {
        return ['entry' => [[
            'id' => self::ACCOUNT,
            'changes' => [[
                'field' => 'comments',
                'value' => array_merge([
                    'id' => 'comment_1',
                    'text' => 'price?',
                    'from' => ['id' => 'igsid_555', 'username' => 'curious_person'],
                    'media' => ['id' => 'media_9'],
                ], $value),
            ]],
        ]]];
    }

    private function rule(array $overrides = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'name' => 'Price questions',
            'channel' => 'instagram',
            'trigger_type' => AutomationRule::TRIGGER_COMMENT,
            'keywords' => ['price', 'cost', 'how much'],
            'reply_body' => 'Tickets are ₹499 — book here: haraan.app/e/123',
        ], $overrides));
    }

    // --- the happy path -----------------------------------------------------

    public function test_a_matching_comment_gets_a_private_reply(): void
    {
        $this->rule();

        $this->hook($this->comment())->assertStatus(200);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            // Addressed to the COMMENT, not the user — that's what makes an
            // unsolicited DM legal here.
            return ($body['recipient']['comment_id'] ?? null) === 'comment_1'
                && str_contains((string) ($body['message']['text'] ?? ''), 'haraan.app/e/123');
        });

        $this->assertSame('sent', InstagramCommentReply::first()->status);
    }

    public function test_it_also_posts_the_public_reply_when_configured(): void
    {
        $this->rule(['public_reply_body' => 'Just sent you a DM! 💬']);

        $this->hook($this->comment())->assertStatus(200);

        Http::assertSent(fn ($request): bool
            => str_contains($request->url(), 'comment_1/replies')
                && str_contains((string) ($request->data()['message'] ?? ''), 'sent you a DM'));
    }

    public function test_no_public_reply_when_none_is_configured(): void
    {
        $this->rule();

        $this->hook($this->comment())->assertStatus(200);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/replies'));
    }

    // --- one reply per comment, ever ----------------------------------------

    public function test_a_redelivered_comment_is_not_answered_twice(): void
    {
        $this->rule();

        // Meta retries. A second private reply to the same comment is an API
        // error and a rule violation, not just a duplicate.
        $this->hook($this->comment())->assertStatus(200);
        $this->hook($this->comment())->assertStatus(200);

        $this->assertSame(1, InstagramCommentReply::count());
        Http::assertSentCount(1);
    }

    public function test_the_comment_is_claimed_even_when_no_rule_matches(): void
    {
        $this->rule(['keywords' => ['parking']]);

        $this->hook($this->comment())->assertStatus(200);

        Http::assertNothingSent();
        // Recorded as handled, so a redelivery doesn't re-evaluate and possibly
        // fire once a rule is added later — the comment has passed.
        $this->assertSame('skipped', InstagramCommentReply::first()->status);
        $this->assertSame('no_matching_rule', InstagramCommentReply::first()->skip_reason);
    }

    // --- loops and edges ----------------------------------------------------

    public function test_our_own_comment_is_ignored(): void
    {
        // Including the public "sent you a DM!" this flow posts itself.
        $this->rule();

        $this->hook($this->comment(['from' => ['id' => self::ACCOUNT, 'username' => 'thevenue']]))
            ->assertStatus(200);

        Http::assertNothingSent();
        $this->assertSame(0, InstagramCommentReply::count());
    }

    public function test_an_empty_comment_is_ignored(): void
    {
        $this->rule();

        $this->hook($this->comment(['text' => '']))->assertStatus(200);

        $this->assertSame(0, InstagramCommentReply::count());
    }

    public function test_a_comment_on_an_unlinked_account_is_ignored(): void
    {
        $this->rule();
        $payload = $this->comment();
        $payload['entry'][0]['id'] = '17841499999999999';

        $this->hook($payload)->assertStatus(200);

        $this->assertSame(0, InstagramCommentReply::count());
        Http::assertNothingSent();
    }

    public function test_a_forged_signature_changes_nothing(): void
    {
        $this->rule();

        $this->hook($this->comment(), 'sha256=nope')->assertStatus(403);

        $this->assertSame(0, InstagramCommentReply::count());
        Http::assertNothingSent();
    }

    // --- gating -------------------------------------------------------------

    public function test_an_opted_out_commenter_is_not_dmed(): void
    {
        $this->rule();
        MessagingOptOut::record('instagram', 'igsid_555');

        $this->hook($this->comment())->assertStatus(200);

        Http::assertNothingSent();
        $this->assertSame('opted_out', InstagramCommentReply::first()->skip_reason);
    }

    public function test_a_partner_without_the_instagram_feature_is_gated(): void
    {
        $this->rule();
        PartnerSubscription::query()->update(['status' => PartnerSubscription::STATUS_CANCELLED]);

        $this->hook($this->comment())->assertStatus(200);

        Http::assertNothingSent();
        $this->assertSame('plan_excludes', InstagramCommentReply::first()->skip_reason);
    }

    public function test_a_keywordless_comment_rule_answers_everything(): void
    {
        $this->rule(['keywords' => []]);

        $this->hook($this->comment(['text' => 'looks amazing!']))->assertStatus(200);

        Http::assertSentCount(1);
    }

    public function test_a_comment_rule_never_fires_on_a_dm(): void
    {
        // A keyword-less comment rule matches any text; if it leaked into DM
        // handling it would answer every message with the comment reply.
        $this->rule(['keywords' => []]);

        $payload = ['entry' => [[
            'id' => self::ACCOUNT,
            'messaging' => [[
                'sender' => ['id' => 'igsid_999'],
                'recipient' => ['id' => self::ACCOUNT],
                'message' => ['mid' => 'mid.a', 'text' => 'hello there'],
            ]],
        ]]];

        $this->hook($payload)->assertStatus(200);

        Http::assertNothingSent();
        // The DM is still recorded — we heard them, there was just nothing to say.
        $this->assertSame(1, MessageLog::where('direction', 'in')->count());
    }

    // --- ledger -------------------------------------------------------------

    public function test_the_private_reply_lands_in_the_ledger(): void
    {
        $this->rule();

        $this->hook($this->comment())->assertStatus(200);

        $log = MessageLog::where('direction', 'out')->first();
        $this->assertSame($this->partner->id, $log->partner_id);
        $this->assertSame('instagram', $log->channel);
        $this->assertSame('comment.private_reply', $log->template_key);
    }
}
