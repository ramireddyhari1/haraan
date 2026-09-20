<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Models\BonusXpEntry;
use App\Models\RewardedAdSession;
use App\Models\RewardGrant;
use App\Models\RewardProgram;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Services\Rewards\RewardedAds;
use App\Support\Rewards\RewardTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * AdMob rewarded ads, verified server-side. A local EC key pair stands in for Google's: the
 * public half is served as the "verifier keys" JSON, the private half signs callbacks exactly
 * the way AdMob does (ECDSA SHA-256 over the query string before &signature=).
 */
final class RewardedAdTest extends TestCase
{
    use RefreshDatabase;
    use RewardFixtures;

    private const KEY_ID = '3335741209';

    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Pre-generated P-256 test keys (tests/Fixtures/admob) — test-only, never used anywhere
        // else. Generating keys at run time needs an openssl.cnf that Windows PHP often lacks.
        $this->privateKey = openssl_pkey_get_private((string) file_get_contents(base_path('tests/Fixtures/admob/google-test.key')));
        $pem = (string) file_get_contents(base_path('tests/Fixtures/admob/google-test.pub'));

        Http::fake(['www.gstatic.com/*' => Http::response(['keys' => [['keyId' => (int) self::KEY_ID, 'pem' => $pem]]])]);
        $this->enableRewardedAds();
    }

    /** @return array{0: User, 1: RewardGrant} */
    private function adLockedGrant(): array
    {
        $this->rule($this->program(), RewardTypes::BONUS_XP, ['amount' => 40], ['unlock_method' => 'rewarded_ad']);
        $a = $this->player();
        $this->finishedMatch([$a], []);

        return [$a, RewardGrant::query()->where('user_id', $a->id)->where('type', RewardTypes::BONUS_XP)->sole()];
    }

    /** @param array<string, string> $params */
    private function googleCallback(array $params, $signWith = null): TestResponse
    {
        $query = http_build_query($params);
        openssl_sign($query, $sig, $signWith ?? $this->privateKey, OPENSSL_ALGO_SHA256);
        $query .= '&signature='.rtrim(strtr(base64_encode($sig), '+/', '-_'), '=').'&key_id='.self::KEY_ID;

        return $this->get('/api/webhooks/admob/rewarded?'.$query);
    }

    /** @return array<string, string> */
    private function params(RewardedAdSession $session, array $overrides = []): array
    {
        return array_merge([
            'ad_network' => '5450213213286189855',
            'ad_unit' => '5224354917',
            'custom_data' => $session->nonce,
            'reward_amount' => '1',
            'reward_item' => 'unlock',
            'timestamp' => (string) (now()->getTimestamp() * 1000),
            'transaction_id' => 'txn-'.$session->id,
            'user_id' => RewardedAds::opaqueUserId((int) $session->user_id),
        ], $overrides);
    }

    private function start(User $user, RewardGrant $grant): RewardedAdSession
    {
        $nonce = $this->asMember($user)->postJson("/api/rewards/{$grant->id}/ad-session")->assertCreated()->json('data.nonce');

        return RewardedAdSession::query()->where('nonce', $nonce)->sole();
    }

    public function test_a_google_signed_callback_unlocks_the_reward(): void
    {
        [$a, $grant] = $this->adLockedGrant();
        self::assertSame(['rewarded_ad'], $grant->lockReasons());
        $session = $this->start($a, $grant);

        // The app saying "done" changes nothing.
        $this->asMember($a)->getJson("/api/rewards/ad-sessions/{$session->nonce}")->assertJsonPath('data.status', 'pending');
        self::assertSame(RewardGrant::LOCKED, $grant->fresh()->status);

        $this->googleCallback($this->params($session))->assertOk()->assertJsonPath('result', 'verified');

        self::assertSame(RewardGrant::CLAIMED, $grant->fresh()->status, 'Bonus XP credits itself once unlocked');
        $this->asMember($a)->getJson("/api/rewards/ad-sessions/{$session->nonce}")->assertJsonPath('data.status', 'verified');
    }

    public function test_a_forged_signature_unlocks_nothing(): void
    {
        [$a, $grant] = $this->adLockedGrant();
        $session = $this->start($a, $grant);
        $attacker = openssl_pkey_get_private((string) file_get_contents(base_path('tests/Fixtures/admob/attacker-test.key')));

        $this->googleCallback($this->params($session), $attacker)->assertStatus(400);
        $this->get('/api/webhooks/admob/rewarded?'.http_build_query($this->params($session)))->assertStatus(400);

        // A valid signature over DIFFERENT params can't be re-pointed at this session.
        $query = http_build_query($this->params($session, ['custom_data' => 'other']));
        openssl_sign($query, $sig, $this->privateKey, OPENSSL_ALGO_SHA256);
        $tampered = str_replace('custom_data=other', 'custom_data='.$session->nonce, $query)
            .'&signature='.rtrim(strtr(base64_encode($sig), '+/', '-_'), '=').'&key_id='.self::KEY_ID;
        $this->get('/api/webhooks/admob/rewarded?'.$tampered)->assertStatus(400);

        self::assertSame(RewardGrant::LOCKED, $grant->fresh()->status);
        self::assertSame(RewardedAdSession::PENDING, $session->fresh()->status);
    }

    public function test_a_replayed_transaction_counts_once(): void
    {
        [$a, $grant] = $this->adLockedGrant();
        $session = $this->start($a, $grant);

        $this->googleCallback($this->params($session))->assertJsonPath('result', 'verified');
        $this->googleCallback($this->params($session))->assertOk()->assertJsonPath('result', 'duplicate');
        self::assertSame(1, RewardedAdSession::query()->where('status', 'verified')->count());
        self::assertSame(40, BonusXpEntry::totalFor($a->id));
    }

    public function test_a_callback_for_someone_else_or_a_late_one_is_rejected(): void
    {
        [$a, $grant] = $this->adLockedGrant();
        $session = $this->start($a, $grant);

        $this->googleCallback($this->params($session, ['user_id' => RewardedAds::opaqueUserId(999999)]))->assertOk()->assertJsonPath('result', 'ignored');
        self::assertSame(RewardedAdSession::REJECTED, $session->fresh()->status);
        self::assertSame(RewardGrant::LOCKED, $grant->fresh()->status);

        $late = $this->start($a, $grant);
        Carbon::setTestNow(now()->addHours(2));
        $this->googleCallback($this->params($late, ['transaction_id' => 'late-1']))->assertJsonPath('result', 'ignored');
        Carbon::setTestNow();
        self::assertSame(RewardGrant::LOCKED, $grant->fresh()->status);
    }

    public function test_a_no_ads_member_can_never_start_an_ad(): void
    {
        [$a, $grant] = $this->adLockedGrant();
        $this->paidSubscription($a, 'pro');
        MemberEntitlements::flush();

        $this->asMember($a)->postJson("/api/rewards/{$grant->id}/ad-session")->assertStatus(403)->assertJsonPath('code', 'ads_hidden');

        // …and their reward no longer waits on a video they'll never be shown.
        $this->asMember($a)->getJson('/api/rewards/summary')->assertOk();
        self::assertSame(RewardGrant::CLAIMED, $grant->fresh()->status);
    }

    public function test_the_daily_video_cap_and_the_ads_switch_hold(): void
    {
        $this->rules(['rewards.ad_daily_cap' => 1]);
        [$a, $grant] = $this->adLockedGrant();
        $first = $this->start($a, $grant);
        $this->googleCallback($this->params($first));

        $this->rule(RewardProgram::query()->first(), RewardTypes::BONUS_XP, ['amount' => 3], ['unlock_method' => 'rewarded_ad']);
        $this->finishedMatch([$a], []);
        $next = RewardGrant::query()->where('user_id', $a->id)->where('status', RewardGrant::LOCKED)->first();
        $this->asMember($a)->postJson("/api/rewards/{$next->id}/ad-session")->assertStatus(429);

        $this->rules(['rewards.ad_daily_cap' => 10, 'ops.rewarded_ads_disabled' => true]);
        $this->asMember($a)->postJson("/api/rewards/{$next->id}/ad-session")->assertStatus(503);
    }
}
