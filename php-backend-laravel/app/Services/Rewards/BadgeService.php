<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\BadgeDefinition;
use App\Models\PlayerBadge;
use App\Models\PlayerStreak;
use App\Models\User;
use App\Support\Rewards\BadgeMetrics;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Persistent badges. A badge unlocks once — the row in player_badges records when and in
 * which match — and is never re-locked. Definitions (name, icon, tier, metric, threshold) are
 * edited in /control → Rewards → Badges; every metric is computed from real data.
 */
final class BadgeService
{
    /** @return Collection<int, BadgeDefinition> */
    public function definitions(): Collection
    {
        return BadgeDefinition::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * The player's value for every metric.
     *
     * @return array<string, int|null>
     */
    public function metrics(User $user): array
    {
        $pid = (string) $user->player_id;
        $ledger = $pid === '' ? collect() : DB::table('match_xp_ledger')
            ->where('player_id', $pid)->orderBy('awarded_at')->orderBy('id')->get(['won', 'mom']);

        $bestWinStreak = 0;
        $run = 0;
        foreach ($ledger as $r) {
            $run = (bool) $r->won ? $run + 1 : 0;
            $bestWinStreak = max($bestWinStreak, $run);
        }

        $highScore = $pid === '' ? 0 : (int) (DB::table('player_career_batting')->where('player_id', $pid)->value('high_score') ?? 0);
        $streakBest = (int) (PlayerStreak::query()->where('user_id', $user->id)->where('kind', PlayerStreak::PLAY_WEEK)->value('best') ?? 0);

        return [
            'matches_played' => $ledger->count(),
            'wins' => $ledger->filter(fn ($r) => (bool) $r->won)->count(),
            'player_of_match' => $ledger->filter(fn ($r) => (bool) $r->mom)->count(),
            'best_win_streak' => $bestWinStreak,
            'high_score' => $highScore,
            'career_wickets' => (int) ($user->career_wickets ?? 0),
            'district_rank' => $user->rank_district !== null ? (int) $user->rank_district : null,
            'play_streak_weeks' => $streakBest,
        ];
    }

    /**
     * Record every badge the player has now reached but doesn't hold yet.
     *
     * @param  bool  $celebrate  false records them as already celebrated (a baseline or a
     *                           profile read), so the app never announces them
     * @return list<PlayerBadge> the badges unlocked by this call
     */
    public function sync(User $user, ?int $matchId, bool $celebrate): array
    {
        $metrics = $this->metrics($user);
        $held = PlayerBadge::query()->where('user_id', $user->id)->pluck('badge_key')->all();
        $new = [];

        foreach ($this->definitions() as $def) {
            if (in_array($def->key, $held, true) || ! BadgeMetrics::exists($def->metric)) {
                continue;
            }
            if (! BadgeMetrics::reached($def->metric, $metrics[$def->metric] ?? null, (int) $def->threshold)) {
                continue;
            }

            try {
                $new[] = PlayerBadge::query()->create([
                    'user_id' => $user->id,
                    'badge_key' => $def->key,
                    'match_id' => $matchId,
                    'unlocked_at' => now(),
                    'celebrated_at' => $celebrate ? null : now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Unlocked concurrently by another run — that run owns the celebration.
            }
        }

        return $new;
    }

    /**
     * The profile's achievements list — same shape and keys the app always read
     * (key, icon, label, tier, unlocked, progress), now backed by persisted unlocks.
     *
     * @return list<array<string, mixed>>
     */
    public function forProfile(User $user): array
    {
        // Anything reached but not yet recorded is recorded quietly (no celebration on a read).
        $this->sync($user, null, false);

        $metrics = $this->metrics($user);
        $held = PlayerBadge::query()->where('user_id', $user->id)->pluck('unlocked_at', 'badge_key');

        return $this->definitions()->map(function (BadgeDefinition $def) use ($metrics, $held): array {
            $unlocked = $held->has($def->key);
            $value = $metrics[$def->metric] ?? null;
            $progress = null;
            if (! $unlocked && $def->show_progress && $def->metric !== 'district_rank') {
                $progress = min((int) $value, (int) $def->threshold).'/'.(int) $def->threshold;
            }

            return [
                'key' => $def->key,
                'icon' => $def->icon,
                'label' => $def->name,
                'tier' => $def->tier,
                'unlocked' => $unlocked,
                'progress' => $progress,
                'unlocked_at' => $unlocked ? optional($held->get($def->key))->toIso8601String() : null,
            ];
        })->values()->all();
    }

    /**
     * The locked badge the player is closest to, with their real value against its threshold —
     * "what am I about to unlock?". Rank badges are left out (lower is better, no bar fits).
     *
     * @return array{key: string, name: string, icon: string, tier: string, value: int, threshold: int}|null
     */
    public function nextFor(User $user): ?array
    {
        $metrics = $this->metrics($user);
        $held = PlayerBadge::query()->where('user_id', $user->id)->pluck('badge_key')->all();
        $best = null;
        $bestRatio = -1.0;

        foreach ($this->definitions() as $def) {
            if (in_array($def->key, $held, true) || $def->metric === 'district_rank' || ! BadgeMetrics::exists($def->metric)) {
                continue;
            }
            $value = (int) ($metrics[$def->metric] ?? 0);
            $threshold = max(1, (int) $def->threshold);
            $ratio = $value / $threshold;
            if ($ratio < 1 && $ratio > $bestRatio) {
                $bestRatio = $ratio;
                $best = ['key' => $def->key, 'name' => $def->name, 'icon' => $def->icon, 'tier' => $def->tier, 'metric' => $def->metric, 'value' => $value, 'threshold' => $threshold];
            }
        }

        return $best;
    }

    /** Badges unlocked in this match that the player hasn't seen celebrated yet, plus seen ones. */
    public function forMatch(User $user, int $matchId): Collection
    {
        return PlayerBadge::query()->with('definition')
            ->where('user_id', $user->id)->where('match_id', $matchId)
            ->orderBy('id')->get();
    }
}
