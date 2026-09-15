<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\MatchUpdated;
use App\Models\LiveMatch;
use App\Services\Stats\LeaderboardRankService;
use App\Services\Stats\MatchPlayerStatsService;
use App\Services\Stats\PlayerCareerService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one way a match becomes finished, whichever path ends it — the creator's "Finish"
 * button (any sport), cricket's chase closing on the last ball, or the web console.
 *
 * Marking finished is cheap and synchronous. Everything that follows from it — per-player
 * match stats, the careers of the players in it, the leaderboard, the ground's record and
 * result verification — runs after the response has been sent, so the scorer's tap returns
 * at once and a slow rebuild can never time out their request.
 */
final class MatchCompletion
{
    public function __construct(
        private readonly MatchPlayerStatsService $matchStats,
        private readonly PlayerCareerService $careers,
        private readonly LeaderboardRankService $ranks,
    ) {}

    /**
     * Stamp the match finished. Returns true only the first time, so side effects that must
     * happen once (opening verification) aren't repeated by a second tap.
     *
     * @param  string|null  $status  a result line to store ("KDW won by 3 wickets"); null keeps
     *                               a result the match already carries, else "Completed"
     */
    public function markFinished(LiveMatch $match, ?string $status = null): bool
    {
        $first = $match->completed_at === null;

        if ($status !== null) {
            $match->status = $status;
        } elseif (! LiveMatch::statusMeansFinished($match->status)) {
            $match->status = 'Completed';
        }
        if ($first) {
            $match->completed_at = now();
        }
        $match->save();

        return $first;
    }

    /**
     * Finish a match and queue its follow-through for after the response.
     */
    public function finish(LiveMatch $match, ?string $status = null, ?bool $firstCompletion = null): LiveMatch
    {
        $first = $this->markFinished($match, $status);
        // A caller that already saved the finishing status (the web console) knows better
        // whether this is the first completion than the stamp does by now.
        $first = $firstCompletion ?? $first;
        $id = (int) $match->id;

        dispatch(function () use ($id, $first): void {
            app(self::class)->followThrough($id, $first);
        })->afterResponse();

        MatchUpdated::dispatch($match->id);

        return $match;
    }

    /**
     * Everything a finished match changes. Safe to run again (e.g. after a late correction to
     * the log): each step rebuilds from the source rather than adding to what is there.
     */
    public function followThrough(int $matchId, bool $firstCompletion = false): void
    {
        $match = LiveMatch::query()->find($matchId);
        if ($match === null) {
            return;
        }

        $this->step('player stats', $matchId, function () use ($match): void {
            $players = $this->matchStats->rebuild($match);
            if ($this->careers->refresh($players)) {
                $this->ranks->recalculate();
            }
        });

        $this->step('ground record', $matchId, function () use ($match): void {
            $ground = app(GroundResolver::class)->resolve($match);
            if ($ground !== null) {
                app(GroundInsightsService::class)->refresh($ground);
            }
        });

        if ($firstCompletion) {
            // Auto-verify Haraan turf matches; otherwise open the captain window.
            $this->step('verification', $matchId, fn () => VenueVerificationService::onMatchCompleted($match->fresh() ?? $match));
        }
    }

    /**
     * A finished match's log changed (a late correction): its stats and careers follow.
     */
    public function refreshAfterCorrection(LiveMatch $match): void
    {
        if (! $match->isFinished()) {
            return;
        }
        $id = (int) $match->id;
        dispatch(function () use ($id): void {
            app(self::class)->followThrough($id, false);
        })->afterResponse();
    }

    /**
     * A finished match became unfinished (its winning ball was undone). Its player rows go,
     * and the careers of the players in it are recomputed without it.
     */
    public function reopen(LiveMatch $match): void
    {
        $match->completed_at = null;
        $match->save();
        $id = (int) $match->id;

        dispatch(function () use ($id): void {
            $players = \App\Models\PlayerMatchStat::query()->where('match_id', $id)
                ->whereNotNull('player_id')->pluck('player_id')->all();
            \App\Models\PlayerMatchStat::query()->where('match_id', $id)->delete();
            if (app(PlayerCareerService::class)->refresh($players)) {
                app(LeaderboardRankService::class)->recalculate();
            }
        })->afterResponse();
    }

    /** One failed step is logged and never stops the others (or the scorer's response). */
    private function step(string $name, int $matchId, callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            Log::error("match completion: {$name} failed", ['match_id' => $matchId, 'error' => $e->getMessage()]);
            report($e);
        }
    }
}
