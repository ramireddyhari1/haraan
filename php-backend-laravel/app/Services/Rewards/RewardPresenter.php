<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\LiveMatch;
use App\Models\MatchXpLedger;
use App\Models\PlayerBadge;
use App\Models\PlayerMatchStat;
use App\Models\RewardCode;
use App\Models\RewardGrant;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\MediaUrl;
use App\Support\Membership\MemberFeature;
use App\Support\PlatformRules;
use App\Support\Rewards\RewardContext;
use App\Support\Rewards\RewardTypes;
use Illuminate\Support\Facades\DB;

/**
 * The API shape of rewards. Competitive XP (ranked/casual, leaderboards) and Bonus XP are
 * reported in separate blocks and never summed. A secret (coupon or sponsor code) is only
 * included for its owner, and only once the reward is claimed.
 */
final class RewardPresenter
{
    public function __construct(
        private readonly MemberEntitlements $entitlements,
        private readonly BonusXp $bonusXp,
        private readonly WeeklyStreakService $streaks,
        private readonly RewardLedger $ledger,
        private readonly BadgeService $badges,
    ) {}

    /**
     * A member whose plan now skips ads (or hides them) is never left holding a reward that
     * waits on a video: its ad lock is cleared.
     */
    public function reconcileAdLocks(User $user): void
    {
        $set = $this->entitlements->for($user);
        if (! $set->allows(MemberFeature::REWARDS_AD_UNLOCK_SKIP) && ! $set->allows(MemberFeature::ADS_HIDDEN)) {
            return;
        }
        RewardGrant::query()->where('user_id', $user->id)->where('status', RewardGrant::LOCKED)->where('needs_ad', true)
            ->get()->each(fn (RewardGrant $g) => $this->ledger->clearLock($g, 'rewarded_ad'));
    }

    /** @return array<string, mixed> */
    public function matchSummary(User $user, LiveMatch $match): array
    {
        $side = (string) $match->sideOf($user);
        $outcome = RewardContext::outcomeFor($match, $side);

        $grants = RewardGrant::query()->where('user_id', $user->id)->where('match_id', $match->id)
            ->with(['program.sponsor'])->orderBy('id')->get();
        $rewards = $grants->filter(fn (RewardGrant $g) => ! in_array($g->type, [RewardTypes::BADGE, RewardTypes::STREAK], true));

        $badges = PlayerBadge::query()->with('definition')->where('user_id', $user->id)->where('match_id', $match->id)->orderBy('id')->get();
        $streak = $this->streaks->forUser($user);
        $streakRow = DB::table('player_streaks')->where('user_id', $user->id)->where('kind', 'play_week')->first();
        $seen = DB::table('reward_match_views')->where('user_id', $user->id)->where('match_id', $match->id)->exists();

        $group = fn (array $states) => $rewards->filter(fn (RewardGrant $g) => in_array($g->status, $states, true))
            ->map(fn (RewardGrant $g) => $this->grant($g))->values()->all();

        $stat = PlayerMatchStat::query()->where('match_id', $match->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->player_id) {
                    $q->orWhere('player_id', (string) $user->player_id);
                }
            })->first();

        $impact = null;
        if ($stat !== null && $stat->played) {
            $impact = [
                'runs' => $stat->runs !== null ? (int) $stat->runs : null,
                'balls' => $stat->balls !== null ? (int) $stat->balls : null,
                'wickets' => $stat->wickets !== null ? (int) $stat->wickets : null,
                'overs_bowled' => $stat->overs_bowled !== null ? (float) $stat->overs_bowled : null,
                'runs_conceded' => $stat->runs_conceded !== null ? (int) $stat->runs_conceded : null,
                'is_potm' => (string) ($match->mom_player_id ?? '') !== '' && (string) $match->mom_player_id === (string) ($user->player_id ?? $user->id),
                'player_name' => $user->name,
                'player_photo' => MediaUrl::resolve($user->avatar),
                'team_name' => $side === 'home' ? trim((string) ($match->home_full ?: $match->home)) : trim((string) ($match->away_full ?: $match->away)),
                'team_logo' => $side === 'home'
                    ? (MediaUrl::resolve($match->home_logo) ?: ($match->home_emblem ?: null))
                    : (MediaUrl::resolve($match->away_logo) ?: ($match->away_emblem ?: null)),
            ];
        }

        return [
            'match' => [
                'id' => (int) $match->id,
                'title' => $this->matchTitle($match),
                'sport' => strtolower((string) ($match->sport ?: 'cricket')),
                'match_type' => (string) ($match->match_type ?: 'casual'),
                'result_line' => (string) $match->status,
                'score_text' => (string) ($match->score_text ?: ''),
                'overs' => (string) ($match->overs ?: ''),
                'completed_at' => $match->completed_at?->toIso8601String(),
                'home' => trim((string) ($match->home_full ?: $match->home)),
                'away' => trim((string) ($match->away_full ?: $match->away)),
                'home_short' => (string) $match->home,
                'away_short' => (string) $match->away,
                'home_score' => (int) $match->home_score,
                'away_score' => (int) $match->away_score,
                'home_logo' => MediaUrl::resolve($match->home_logo) ?: ($match->home_emblem ?: null),
                'away_logo' => MediaUrl::resolve($match->away_logo) ?: ($match->away_emblem ?: null),
                // Public match page — what "Remind your captain" shares.
                'share_url' => url('/gamehub/actionboard/match/'.$match->id),
            ],
            'viewer' => ['side' => $side, 'outcome' => $outcome, 'impact' => $impact],
            'celebration' => [
                'enabled' => PlatformRules::bool('rewards.celebration_enabled'),
                'animation_url' => PlatformRules::string('rewards.celebration_animation_url') ?: null,
                'variant' => in_array($outcome, ['won', 'lost', 'tied'], true) ? $outcome : 'finished',
                'seen' => $seen,
            ],
            'competitive_xp' => $this->competitiveXp($user, $match),
            'bonus_xp' => [
                'this_match' => (int) DB::table('bonus_xp_ledger')->where('user_id', $user->id)->where('match_id', $match->id)->sum('amount'),
                'total' => $this->bonusXp->total($user),
                'counts_toward_leaderboard' => false,
            ],
            'badges' => $badges->map(fn (PlayerBadge $b) => [
                'key' => $b->badge_key,
                'name' => $b->definition?->name ?? $b->badge_key,
                'description' => $b->definition?->description,
                'icon' => $b->definition?->icon ?? 'EmojiEvents',
                'tier' => $b->definition?->tier ?? 'bronze',
                'unlocked_at' => $b->unlocked_at?->toIso8601String(),
                'is_new' => $b->celebrated_at === null,
            ])->values()->all(),
            'streak' => [
                'current' => $streak['current'],
                'best' => $streak['best'],
                'extended_this_match' => $streakRow !== null && (int) $streakRow->last_match_id === (int) $match->id,
                // Whether this ISO week already counts — the app's "play by Sunday" nudge needs it.
                'played_this_week' => $streakRow !== null && $streakRow->last_period === WeeklyStreakService::period(now()),
            ],
            'next_badge' => $this->badges->nextFor($user),
            'rewards' => [
                'ready' => $group([RewardGrant::AVAILABLE]),
                'locked' => $group([RewardGrant::LOCKED]),
                'claimed' => $group([RewardGrant::CLAIMED, RewardGrant::REDEEMED]),
                'closed' => $group([RewardGrant::EXPIRED, RewardGrant::REVOKED]),
            ],
            'rewarded_ads' => $this->adsBlock($user),
        ];
    }

    /** @return array<string, mixed> */
    public function grant(RewardGrant $g, bool $reveal = false): array
    {
        $value = (array) ($g->value ?? []);
        $sponsor = $value['sponsor'] ?? null;

        $out = [
            'id' => (int) $g->id,
            'match_id' => $g->match_id !== null ? (int) $g->match_id : null,
            'type' => $g->type,
            'source' => $g->source,
            'status' => $g->status,
            'lock_reasons' => $g->lockReasons(),
            'status_reason' => $g->status_reason,
            'title' => $g->title,
            'description' => $g->description,
            'sponsored' => (bool) ($value['sponsored'] ?? false),
            'sponsor' => is_array($sponsor) ? [
                'name' => $sponsor['name'] ?? null,
                'logo' => MediaUrl::resolve($sponsor['logo'] ?? null),
                'category' => $sponsor['category'] ?? null,
            ] : null,
            'disclosure' => $value['disclosure'] ?? null,
            'terms_url' => $value['terms_url'] ?? null,
            'brand_color' => $value['brand_color'] ?? null,
            'card_image' => MediaUrl::resolve($value['card_image'] ?? null),
            'bonus_xp' => $g->bonus_xp,
            'geo' => $this->geo($value),
            'stub' => $this->stub($g, $value),
            'claimable' => $g->status === RewardGrant::AVAILABLE && $g->type !== RewardTypes::BONUS_XP,
            'can_watch_ad' => $g->status === RewardGrant::LOCKED && $g->needs_ad && ! $g->needs_verification,
            'expires_at' => $g->expires_at?->toIso8601String(),
            'unlocked_at' => $g->unlocked_at?->toIso8601String(),
            'claimed_at' => $g->claimed_at?->toIso8601String(),
            'redeemed_at' => $g->redeemed_at?->toIso8601String(),
        ];

        if ($reveal && in_array($g->status, [RewardGrant::CLAIMED, RewardGrant::REDEEMED], true)) {
            $out['claim'] = $this->claimDetail($g, $value);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function wallet(User $user): array
    {
        $counts = RewardGrant::query()->where('user_id', $user->id)
            ->whereNotIn('type', [RewardTypes::BADGE, RewardTypes::STREAK])
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'bonus_xp_total' => $this->bonusXp->total($user),
            'streak' => $this->streaks->forUser($user),
            'badges_unlocked' => PlayerBadge::query()->where('user_id', $user->id)->count(),
            'ready' => (int) ($counts[RewardGrant::AVAILABLE] ?? 0),
            'locked' => (int) ($counts[RewardGrant::LOCKED] ?? 0),
            'claimed' => (int) (($counts[RewardGrant::CLAIMED] ?? 0) + ($counts[RewardGrant::REDEEMED] ?? 0)),
            'rewarded_ads' => $this->adsBlock($user),
        ];
    }

    /** @return array<string, mixed> */
    private function adsBlock(User $user): array
    {
        $hidden = $this->entitlements->allows($user, MemberFeature::ADS_HIDDEN);
        $available = ! $hidden && RewardEngine::rewardedAdsAvailable() && RewardEngine::enabled();

        return [
            'available' => $available,
            'ad_unit_id' => $available ? PlatformRules::string('rewards.admob_ad_unit_id') : null,
            'ssv_user_id' => $available ? RewardedAds::opaqueUserId((int) $user->id) : null,
        ];
    }

    /**
     * Where a location-targeted reward came from, and where it is redeemed — the vault's
     * distance chip and its Directions action. Null for everything else, which is most rewards.
     *
     * The distance reported is how far the MATCH was from the zone, measured when the reward was
     * won. It is not, and must never be read as, where the player is now.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>|null
     */
    private function geo(array $value): ?array
    {
        $geo = $value['geo'] ?? null;
        if (! is_array($geo) || ($geo['zone'] ?? null) === null) {
            return null;
        }

        $at = is_array($geo['redeem_at'] ?? null) ? $geo['redeem_at'] : null;

        return [
            'zone' => (string) $geo['zone'],
            'distance_km' => isset($geo['distance_km']) ? (float) $geo['distance_km'] : null,
            'redeem_at' => $at === null ? null : [
                'name' => (string) ($at['name'] ?? ''),
                'latitude' => (float) ($at['latitude'] ?? 0),
                'longitude' => (float) ($at['longitude'] ?? 0),
            ],
        ];
    }

    /**
     * The big value on a ticket-style card ("₹50" / "OFF"), from what was actually promised.
     * Null when the reward has no single number (a sponsor code, an offer link) — the card then
     * leads with the sponsor instead of an invented figure.
     *
     * @param  array<string, mixed>  $value
     * @return array{value: string, caption: string}|null
     */
    private function stub(RewardGrant $g, array $value): ?array
    {
        return match ($g->type) {
            RewardTypes::HARAAN_COUPON => isset($value['coupon']['discount']) ? [
                'value' => ($value['coupon']['discount_type'] ?? 'fixed') === 'percent'
                    ? (int) $value['coupon']['discount'].'%'
                    : '₹'.number_format((int) $value['coupon']['discount']),
                'caption' => 'OFF',
            ] : null,
            RewardTypes::MEMBERSHIP_TRIAL => ['value' => (string) (int) ($value['days'] ?? 0), 'caption' => 'DAYS'],
            RewardTypes::BONUS_XP => ['value' => '+'.(int) $g->bonus_xp, 'caption' => 'XP'],
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function competitiveXp(User $user, LiveMatch $match): array
    {
        $status = (string) ($match->verification_status ?? '');
        $xp = MatchXpLedger::query()->where('match_id', $match->id)->where('player_id', (string) $user->player_id)->first();

        if ($match->is_private) {
            return ['state' => 'not_eligible', 'xp' => null, 'is_ranked' => false, 'trust_level' => null,
                'deadline' => null, 'explanation' => 'Private matches don’t earn XP.'];
        }

        if ($xp !== null && in_array($status, ['settled', 'expired'], true)) {
            return [
                'state' => 'settled',
                'xp' => (int) $xp->xp,
                'is_ranked' => (bool) $xp->is_ranked,
                'trust_level' => $xp->trust_level,
                'deadline' => null,
                'explanation' => $xp->is_ranked ? 'Counts toward the leaderboards.' : 'Counts toward your casual XP.',
            ];
        }

        return [
            'state' => 'pending_verification',
            'xp' => null,
            'is_ranked' => null,
            'trust_level' => $match->trust_level,
            'deadline' => $match->verification_deadline?->toIso8601String(),
            'home_confirmed' => (bool) $match->home_captain_confirmed,
            'away_confirmed' => (bool) $match->away_captain_confirmed,
            'explanation' => 'XP is added once the result is confirmed by both captains (or an organiser). '
                .'Unconfirmed results settle at low trust when the window closes.',
        ];
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function claimDetail(RewardGrant $g, array $value): array
    {
        return match ($g->type) {
            RewardTypes::HARAAN_COUPON => $g->coupon === null ? [] : [
                'code' => $g->coupon->code,
                'expires_at' => $g->coupon->expires_at?->toIso8601String(),
                'scope' => $g->coupon->scope,
                'min_order' => $g->coupon->min_order,
                'used' => (int) $g->coupon->uses > 0,
            ],
            RewardTypes::SPONSOR_CODE => [
                'code' => $g->reward_code_id !== null ? (string) RewardCode::query()->find($g->reward_code_id)?->code : null,
                'instructions' => $value['instructions'] ?? null,
            ],
            RewardTypes::MEMBERSHIP_TRIAL => [
                'days' => (int) ($value['days'] ?? 0),
                'ends_at' => $g->claimed_at?->copy()->addDays((int) ($value['days'] ?? 0))->toIso8601String(),
            ],
            RewardTypes::OFFER_LINK => [
                'url' => $g->rule?->payload['url'] ?? null,
                'cta_text' => $value['cta_text'] ?? 'Open offer',
            ],
            default => [],
        };
    }

    private function matchTitle(LiveMatch $match): string
    {
        $home = trim((string) ($match->home_full ?: $match->home));
        $away = trim((string) ($match->away_full ?: $match->away));

        return $home !== '' && $away !== '' ? "{$home} vs {$away}" : 'Match';
    }
}
