<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\PlayerCareerBatting;
use App\Models\PlayerCareerBowling;
use App\Models\PlayerCareerFielding;
use App\Models\PlayerMatchStat;
use App\Models\PlayerSportCareer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Careers, rolled up from each player's FINISHED match rows in `player_match_stats`.
 *
 * Incremental by design: finishing a match refreshes only the players in it. A player's
 * career is always recomputed whole from their own match rows (never patched with a delta),
 * so a correction to an old match lands exactly once and nothing can double count.
 *
 * Cricket also keeps the detail tables the profile already reads (batting / bowling /
 * fielding) and the `users.career_*` columns the leaderboard and achievements read.
 */
final class PlayerCareerService
{
    /** Stats that are identities or labels, never summed. */
    private const NON_ADDITIVE = ['batting', 'bowling', 'fielding'];

    /**
     * @param  array<int, string>  $playerIds
     * @return bool whether any cricket career changed (so rankings need recomputing)
     */
    public function refresh(array $playerIds): bool
    {
        $cricketChanged = false;
        foreach (array_unique(array_filter($playerIds)) as $pid) {
            $cricketChanged = $this->refreshOne((string) $pid) || $cricketChanged;
        }

        return $cricketChanged;
    }

    /** @return array<int, string> every player id that has any match row */
    public function allPlayerIds(): array
    {
        return PlayerMatchStat::query()->whereNotNull('player_id')->distinct()->pluck('player_id')->all();
    }

    private function refreshOne(string $pid): bool
    {
        $rows = PlayerMatchStat::query()
            ->join('live_matches', 'live_matches.id', '=', 'player_match_stats.match_id')
            ->whereNotNull('live_matches.completed_at')
            ->where('player_match_stats.player_id', $pid)
            ->orderBy('live_matches.completed_at')
            ->get([
                'player_match_stats.*',
                'live_matches.completed_at as match_completed_at',
            ]);

        $bySport = $rows->groupBy('sport');
        $user = User::query()->where('player_id', $pid)->first();
        $name = (string) ($user?->name ?? $rows->last()?->player_name ?? $pid);

        return DB::transaction(function () use ($pid, $bySport, $user, $name): bool {
            PlayerSportCareer::query()->where('player_id', $pid)
                ->whereNotIn('sport', $bySport->keys()->all())
                ->delete();

            foreach ($bySport as $sport => $sportRows) {
                $totals = [];
                $bests = [];
                foreach ($sportRows as $row) {
                    foreach ((array) $row->stats as $key => $value) {
                        if (in_array($key, self::NON_ADDITIVE, true) || ! is_int($value)) {
                            continue;
                        }
                        $totals[$key] = ($totals[$key] ?? 0) + $value;
                        $bests[$key] = max($bests[$key] ?? 0, $value);
                    }
                }

                if ($sport === 'cricket') {
                    [$totals, $bests] = $this->cricketTotals($sportRows->all());
                }

                PlayerSportCareer::query()->updateOrCreate(
                    ['player_id' => $pid, 'sport' => (string) $sport],
                    [
                        'matches' => $sportRows->where('played', true)->count()
                            ?: $sportRows->count(),
                        'wins' => $sportRows->where('result', 'win')->count(),
                        'losses' => $sportRows->where('result', 'loss')->count(),
                        'draws' => $sportRows->where('result', 'draw')->count(),
                        'totals' => $totals,
                        'bests' => $bests,
                        'last_match_at' => $sportRows->last()?->match_completed_at,
                    ],
                );
            }

            return $this->writeCricketDetail($pid, $name, $user, $bySport->get('cricket')?->all() ?? []);
        });
    }

    /**
     * @param  array<int, PlayerMatchStat>  $rows
     * @return array{0: array<string, int>, 1: array<string, mixed>}
     */
    private function cricketTotals(array $rows): array
    {
        $t = [
            'batting_innings' => 0, 'runs' => 0, 'balls_faced' => 0, 'fours' => 0, 'sixes' => 0,
            'not_outs' => 0, 'outs' => 0, 'thirties' => 0, 'fifties' => 0, 'hundreds' => 0,
            'bowling_innings' => 0, 'wickets' => 0, 'balls_bowled' => 0, 'runs_conceded' => 0,
            'maidens' => 0, 'three_fers' => 0, 'five_fers' => 0,
            'catches' => 0, 'run_outs' => 0, 'stumpings' => 0,
        ];
        $high = 0;
        $highNotOut = false;
        $best = null;

        foreach ($rows as $row) {
            $s = (array) $row->stats;
            foreach ($s['batting']['innings'] ?? [] as $inn) {
                $runs = (int) $inn['runs'];
                $t['batting_innings']++;
                $t['runs'] += $runs;
                $t['balls_faced'] += (int) $inn['balls'];
                $t['fours'] += (int) $inn['fours'];
                $t['sixes'] += (int) $inn['sixes'];
                $inn['out'] ? $t['outs']++ : $t['not_outs']++;
                // Milestones belong to the INNINGS — no total can say whether 150 runs was
                // one hundred or five thirties. Each innings counts once, at its best band.
                if ($runs >= 100) {
                    $t['hundreds']++;
                } elseif ($runs >= 50) {
                    $t['fifties']++;
                } elseif ($runs >= 30) {
                    $t['thirties']++;
                }
                if ($runs > $high || ($runs === $high && ! $inn['out'])) {
                    $high = $runs;
                    $highNotOut = ! $inn['out'];
                }
            }
            foreach ($s['bowling']['innings'] ?? [] as $inn) {
                $w = (int) $inn['wickets'];
                $r = (int) $inn['runs'];
                $t['bowling_innings']++;
                $t['wickets'] += $w;
                $t['balls_bowled'] += (int) $inn['balls'];
                $t['runs_conceded'] += $r;
                $t['maidens'] += (int) ($inn['maidens'] ?? 0);
                if ($w >= 5) {
                    $t['five_fers']++;
                } elseif ($w >= 3) {
                    $t['three_fers']++;
                }
                // A scorebook's best figures: more wickets wins; for the same haul, fewer runs.
                if ($w > 0 && ($best === null || $w > $best[0] || ($w === $best[0] && $r < $best[1]))) {
                    $best = [$w, $r];
                }
            }
            foreach (['catches', 'run_outs', 'stumpings'] as $k) {
                $t[$k] += (int) ($s['fielding'][$k] ?? 0);
            }
        }

        return [$t, [
            'high_score' => $high,
            'high_score_not_out' => $highNotOut,
            'best_bowling' => $best === null ? null : ['wickets' => $best[0], 'runs' => $best[1]],
        ]];
    }

    /**
     * Cricket's detail tables and user columns, from the same rows.
     *
     * @param  array<int, PlayerMatchStat>  $rows
     */
    private function writeCricketDetail(string $pid, string $name, ?User $user, array $rows): bool
    {
        [$t, $b] = $this->cricketTotals($rows);

        $zones = [];
        foreach ($rows as $row) {
            foreach ((array) (((array) $row->stats)['batting']['zones'] ?? []) as $z => $zt) {
                $zones[$z] ??= ['shots' => 0, 'fours' => 0, 'sixes' => 0, 'runs' => 0];
                foreach ($zt as $k => $v) {
                    $zones[$z][$k] = ($zones[$z][$k] ?? 0) + (int) $v;
                }
            }
        }
        ksort($zones);

        if ($t['batting_innings'] > 0) {
            PlayerCareerBatting::query()->updateOrCreate(['player_id' => $pid], [
                'player_name' => $name, 'innings' => $t['batting_innings'], 'runs' => $t['runs'],
                'balls' => $t['balls_faced'], 'fours' => $t['fours'], 'sixes' => $t['sixes'],
                'outs' => $t['outs'], 'high_score' => $b['high_score'],
                'thirties' => $t['thirties'], 'fifties' => $t['fifties'], 'hundreds' => $t['hundreds'],
                'zones' => array_map(static fn ($z, $zt) => ['zone' => (int) $z] + $zt, array_keys($zones), array_values($zones)),
            ]);
        } else {
            PlayerCareerBatting::query()->where('player_id', $pid)->delete();
        }

        if ($t['bowling_innings'] > 0) {
            PlayerCareerBowling::query()->updateOrCreate(['player_id' => $pid], [
                'player_name' => $name, 'innings' => $t['bowling_innings'], 'balls' => $t['balls_bowled'],
                'runs' => $t['runs_conceded'], 'wickets' => $t['wickets'],
                'best_wickets' => (int) ($b['best_bowling']['wickets'] ?? 0),
                'best_runs' => (int) ($b['best_bowling']['runs'] ?? 0),
                'three_fers' => $t['three_fers'], 'five_fers' => $t['five_fers'], 'maidens' => $t['maidens'],
            ]);
        } else {
            PlayerCareerBowling::query()->where('player_id', $pid)->delete();
        }

        if ($t['catches'] + $t['run_outs'] + $t['stumpings'] > 0) {
            PlayerCareerFielding::query()->updateOrCreate(['player_id' => $pid], [
                'player_name' => $name, 'catches' => $t['catches'],
                'run_outs' => $t['run_outs'], 'stumpings' => $t['stumpings'],
            ]);
        } else {
            PlayerCareerFielding::query()->where('player_id', $pid)->delete();
        }

        if ($user === null) {
            return false;
        }

        $matches = count(array_filter($rows, static fn (PlayerMatchStat $r): bool => (bool) $r->played))
            ?: count($rows);
        $new = [
            'career_matches' => $matches,
            'career_runs' => $t['runs'],
            'career_balls' => $t['balls_faced'],
            'career_wickets' => $t['wickets'],
            'career_runs_conceded' => $t['runs_conceded'],
            'career_overs_bowled' => intdiv($t['balls_bowled'], 6) . '.' . ($t['balls_bowled'] % 6),
        ];
        $changed = (int) $user->career_runs !== $new['career_runs'];
        $user->forceFill($new)->saveQuietly();

        return $changed;
    }
}
