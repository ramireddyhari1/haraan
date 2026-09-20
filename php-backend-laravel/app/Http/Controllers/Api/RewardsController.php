<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\PlayerBadge;
use App\Models\RewardGrant;
use App\Models\User;
use App\Services\Rewards\InvalidAdSignature;
use App\Services\Rewards\RewardClaimException;
use App\Services\Rewards\RewardClaims;
use App\Services\Rewards\RewardedAds;
use App\Services\Rewards\RewardEngine;
use App\Services\Rewards\RewardPresenter;
use App\Support\Rewards\RewardGeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Post-match rewards for the signed-in player.
 *
 * Every grant lookup is scoped to the caller (`user_id = me`), so another player's reward id
 * answers 404 — never 403, which would confirm it exists. A match's rewards are shown only to a
 * registered player in its squads. The AdMob callback is the one unauthenticated route; it is
 * trusted only through Google's signature.
 */
final class RewardsController extends Controller
{
    /** How many of a player's own rewards are considered when sorting the vault by distance. */
    private const NEAR_WINDOW = 200;

    public function __construct(
        private readonly RewardPresenter $presenter,
        private readonly RewardEngine $engine,
    ) {}

    /** GET /api/matches/{id}/rewards — the post-match screen. */
    public function forMatch(Request $request, string $id): JsonResponse
    {
        $user = $this->user($request);
        $match = LiveMatch::query()->find($id);
        if ($match === null || $match->sideOf($user) === null) {
            return response()->json(['error' => 'Match not found', 'code' => 'not_found'], 404);
        }
        if (! $match->isFinished()) {
            return response()->json(['error' => 'This match hasn’t finished yet.', 'code' => 'match_not_finished'], 409);
        }

        // The follow-through runs after the scorer's response; a player who opens the screen
        // first gets the same (idempotent) evaluation now rather than an empty screen.
        if (RewardEngine::enabled() && ! $this->engine->evaluated($match)) {
            $this->engine->onCompleted($match);
        }
        $this->presenter->reconcileAdLocks($user);

        return response()->json(['data' => $this->presenter->matchSummary($user, $match->fresh() ?? $match)]);
    }

    /** POST /api/matches/{id}/rewards/seen — the celebration played; don't play it again. */
    public function seen(Request $request, string $id): JsonResponse
    {
        $user = $this->user($request);
        $match = LiveMatch::query()->find($id);
        if ($match === null || $match->sideOf($user) === null) {
            return response()->json(['error' => 'Match not found', 'code' => 'not_found'], 404);
        }

        DB::table('reward_match_views')->insertOrIgnore(['user_id' => $user->id, 'match_id' => $match->id, 'seen_at' => now()]);
        PlayerBadge::query()->where('user_id', $user->id)->where('match_id', $match->id)->whereNull('celebrated_at')
            ->update(['celebrated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** GET /api/rewards — history, newest first. ?status=ready|locked|claimed|closed */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $states = match ((string) $request->query('status', '')) {
            'ready' => [RewardGrant::AVAILABLE],
            'locked' => [RewardGrant::LOCKED],
            'claimed' => [RewardGrant::CLAIMED, RewardGrant::REDEEMED],
            'closed' => [RewardGrant::EXPIRED, RewardGrant::REVOKED],
            default => null,
        };

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $near = $this->near($request);

        if ($near !== null) {
            return response()->json($this->byDistance($user, $states, $near, $perPage, (int) $request->query('page', 1)));
        }

        $page = RewardGrant::query()->ownedBy($user)
            ->when($states !== null, fn ($q) => $q->whereIn('status', $states))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (RewardGrant $g) => $this->presenter->grant($g))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * `?near=lat,lng` — the caller's own position, used to put the rewards they can walk to at
     * the top of the vault.
     *
     * This is the ONE place a client-supplied position is accepted, and it can only change the
     * ORDER of what the player already owns. It never decides eligibility and never changes a
     * reward's value: those come from the match's verified fix alone. A spoofed position here
     * buys nothing but a differently sorted list.
     *
     * @return array{0: float, 1: float}|null
     */
    private function near(Request $request): ?array
    {
        $raw = $request->query('near');
        if (! is_string($raw) || ! preg_match('/^(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)$/', trim($raw), $m)) {
            return null;
        }

        $lat = (float) $m[1];
        $lng = (float) $m[2];

        return abs($lat) <= 90 && abs($lng) <= 180 ? [$lat, $lng] : null;
    }

    /**
     * Sorting has to happen in PHP — the redemption point lives inside the grant's JSON
     * snapshot, so no ORDER BY can reach it. The window is bounded so this stays a list the
     * player owns, not a scan.
     *
     * @param  list<string>|null  $states
     * @param  array{0: float, 1: float}  $near
     * @return array<string, mixed>
     */
    private function byDistance(User $user, ?array $states, array $near, int $perPage, int $page): array
    {
        $rows = RewardGrant::query()->ownedBy($user)
            ->when($states !== null, fn ($q) => $q->whereIn('status', $states))
            ->orderByDesc('id')
            ->limit(self::NEAR_WINDOW)
            ->get();

        $sorted = $rows->sortBy(function (RewardGrant $g) use ($near): array {
            $at = ($g->value['geo']['redeem_at'] ?? null);
            // A reward with nowhere to go keeps its place behind the ones that have somewhere,
            // newest first — never pretending to a distance it doesn't have.
            if (! is_array($at) || ! isset($at['latitude'], $at['longitude'])) {
                return [1, 0.0, -(int) $g->id];
            }

            return [0, RewardGeo::haversine($near[0], $near[1], (float) $at['latitude'], (float) $at['longitude']), -(int) $g->id];
        })->values();

        $page = max(1, $page);
        $items = $sorted->slice(($page - 1) * $perPage, $perPage);

        return [
            'data' => $items->map(fn (RewardGrant $g) => $this->presenter->grant($g))->values(),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($sorted->count() / $perPage)),
                'total' => $sorted->count(),
                'sorted_by' => 'distance',
            ],
        ];
    }

    /** GET /api/rewards/summary — wallet totals for the profile. */
    public function summary(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->presenter->reconcileAdLocks($user);

        return response()->json(['data' => $this->presenter->wallet($user)]);
    }

    /** GET /api/rewards/{id} — one reward; the code is included once it's claimed. */
    public function show(Request $request, string $id): JsonResponse
    {
        $grant = $this->owned($request, $id);
        if ($grant === null) {
            return response()->json(['error' => 'Reward not found', 'code' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->presenter->grant($grant, true)]);
    }

    /** POST /api/rewards/{id}/claim */
    public function claim(Request $request, string $id, RewardClaims $claims): JsonResponse
    {
        $grant = $this->owned($request, $id);
        if ($grant === null) {
            return response()->json(['error' => 'Reward not found', 'code' => 'not_found'], 404);
        }

        try {
            $claimed = $claims->claim($this->user($request), $grant);
        } catch (RewardClaimException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        return response()->json(['data' => $this->presenter->grant($claimed, true)]);
    }

    /** POST /api/rewards/{id}/ad-session — the player chose to watch a video to unlock this. */
    public function startAd(Request $request, string $id, RewardedAds $ads): JsonResponse
    {
        $grant = $this->owned($request, $id);
        if ($grant === null) {
            return response()->json(['error' => 'Reward not found', 'code' => 'not_found'], 404);
        }

        try {
            $session = $ads->start($this->user($request), $grant);
        } catch (RewardClaimException $e) {
            return response()->json(['error' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        return response()->json(['data' => [
            'nonce' => $session->nonce,
            'ad_unit_id' => $session->ad_unit,
            'ssv_user_id' => RewardedAds::opaqueUserId((int) $session->user_id),
            'expires_at' => $session->expires_at->toIso8601String(),
        ]], 201);
    }

    /** GET /api/rewards/ad-sessions/{nonce} — has Google verified it yet? */
    public function adStatus(Request $request, string $nonce, RewardedAds $ads): JsonResponse
    {
        $status = $ads->status($this->user($request), $nonce);
        if ($status === null) {
            return response()->json(['error' => 'Session not found', 'code' => 'not_found'], 404);
        }

        return response()->json(['data' => $status]);
    }

    /**
     * GET /api/webhooks/admob/rewarded — Google's server-side verification callback. Public; trusted
     * only through the ECDSA signature. A valid callback always answers 200 (so Google stops
     * retrying) even when it unlocks nothing; an invalid signature answers 400.
     */
    public function admobCallback(Request $request, RewardedAds $ads): JsonResponse
    {
        try {
            $result = $ads->callback((string) $request->server('QUERY_STRING', ''));
        } catch (InvalidAdSignature) {
            return response()->json(['ok' => false], 400);
        }

        return response()->json(['ok' => true, 'result' => $result]);
    }

    private function owned(Request $request, string $id): ?RewardGrant
    {
        return ctype_digit($id)
            ? RewardGrant::query()->ownedBy($this->user($request))->with(['coupon', 'rule'])->find((int) $id)
            : null;
    }

    private function user(Request $request): User
    {
        $user = $request->attributes->get('auth_user');
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
