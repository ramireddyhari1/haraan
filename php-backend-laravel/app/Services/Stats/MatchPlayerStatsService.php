<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\LiveMatch;
use App\Models\PlayerMatchStat;
use Illuminate\Support\Facades\DB;

/**
 * Stores one match's per-player figures (from {@see MatchPlayerStatsCalculator}) in
 * `player_match_stats`, replacing whatever that match had before.
 *
 * Rebuilding is per MATCH, so the cost of finishing a match is the size of that match — not
 * the size of every match ever played, which is what the old full-career rebuild cost on
 * every completion.
 */
final class MatchPlayerStatsService
{
    public function __construct(private readonly MatchPlayerStatsCalculator $calculator) {}

    /**
     * @return array<int, string> registered player ids whose figures this match touched —
     *     now and before, so a player removed from a squad also has their career corrected
     */
    public function rebuild(LiveMatch $match): array
    {
        $rows = $this->calculator->forMatch($match);
        $now = now();

        $before = PlayerMatchStat::query()->where('match_id', $match->id)
            ->whereNotNull('player_id')->pluck('player_id')->all();

        DB::transaction(function () use ($match, $rows, $now): void {
            PlayerMatchStat::query()->where('match_id', $match->id)->delete();

            $insert = array_map(static function (array $r) use ($match, $now): array {
                $s = $r['stats'];
                $bat = $s['batting'] ?? [];
                $bowl = $s['bowling'] ?? [];
                $bowlBalls = (int) ($bowl['balls'] ?? 0);

                return [
                    'match_id' => $match->id,
                    'sport' => $r['sport'],
                    'side' => $r['side'],
                    'player_key' => $r['player_key'],
                    'player_id' => $r['player_id'],
                    'user_id' => $r['user_id'],
                    'player_name' => mb_substr((string) $r['player_name'], 0, 190),
                    'played' => $r['played'],
                    'result' => $r['result'],
                    // Cricket's headline figures stay in their columns for existing readers.
                    'runs' => (int) ($bat['runs'] ?? 0),
                    'balls' => (int) ($bat['balls'] ?? 0),
                    'wickets' => (int) ($bowl['wickets'] ?? 0),
                    'overs_bowled' => intdiv($bowlBalls, 6) . '.' . ($bowlBalls % 6),
                    'runs_conceded' => (int) ($bowl['runs'] ?? 0),
                    'stats' => json_encode($s === [] ? new \stdClass() : $s),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $rows);

            foreach (array_chunk($insert, 200) as $chunk) {
                PlayerMatchStat::query()->insert($chunk);
            }
        });

        $after = array_filter(array_map(static fn (array $r): ?string => $r['player_id'], $rows));

        return array_values(array_unique(array_merge($before, $after)));
    }

    /**
     * The figures for a match as the API serves them, computed live for a match in progress
     * and read from storage once it has finished.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forMatch(LiveMatch $match): array
    {
        $rows = $match->isFinished()
            ? PlayerMatchStat::query()->where('match_id', $match->id)->get()
                ->map(static fn (PlayerMatchStat $s): array => [
                    'player_key' => $s->player_key,
                    'player_id' => $s->player_id,
                    'player_name' => $s->player_name,
                    'side' => $s->side,
                    'played' => (bool) $s->played,
                    'result' => $s->result,
                    'stats' => is_array($s->stats) ? $s->stats : [],
                ])->all()
            : $this->calculator->forMatch($match);

        if ($rows === [] && $match->isFinished()) {
            $rows = $this->calculator->forMatch($match);
        }

        return array_values(array_map(static fn (array $r): array => [
            'playerKey' => $r['player_key'],
            'playerId' => $r['player_id'],
            'name' => $r['player_name'],
            'side' => $r['side'],
            'played' => (bool) $r['played'],
            'result' => $r['result'],
            'stats' => (object) $r['stats'],
        ], $rows));
    }
}
