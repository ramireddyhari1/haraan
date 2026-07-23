<?php

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\PlayerCareerBatting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds REAL career stats by replaying the ball-by-ball `match_actions` log — the same
 * attribution the live scorecard uses (LiveMatchController::buildInningsCards) — so a
 * player's career runs/balls/outs/high-score AND wickets/overs/runs-conceded are the honest
 * sum of what actually happened, not the synthetic mt_rand fill the legacy PlayerStatsService
 * writes.
 *
 * Batting detail lands in `player_career_batting`; the aggregate totals are also written back
 * onto the `users.career_*` columns (what the leaderboard, profile and achievements read),
 * then rankings are recomputed on the now-real career_runs.
 *
 * Keyed by squad player id (== User.player_id). Guests have no persistent id, so they carry
 * no career line.
 */
class CareerBattingService
{
    /** Full rebuild: re-aggregate every completed match and rewrite career + rankings. */
    public static function rebuildAll(): int
    {
        $careerBat = [];   // id => batting aggregate (also rows for player_career_batting)
        $careerBowl = [];  // id => ['wickets','balls','runs']
        $matchesOf = [];   // id => [matchId => true]  (for a real match count)

        $matches = LiveMatch::query()
            ->whereRaw('lower(status) = ?', ['completed'])
            ->get();

        foreach ($matches as $match) {
            foreach (self::replayInnings($match) as $inn) {
                foreach ($inn['batting'] as $pid => $t) {
                    if ($t['balls'] <= 0 && !$t['out']) {
                        continue; // didn't actually bat
                    }
                    if (!isset($careerBat[$pid])) {
                        $careerBat[$pid] = [
                            'player_id' => $pid, 'player_name' => $t['name'],
                            'innings' => 0, 'runs' => 0, 'balls' => 0,
                            'fours' => 0, 'sixes' => 0, 'outs' => 0, 'high_score' => 0,
                        ];
                    }
                    $c = &$careerBat[$pid];
                    $c['player_name'] = $t['name'] ?: $c['player_name'];
                    $c['innings']    += 1;
                    $c['runs']       += $t['runs'];
                    $c['balls']      += $t['balls'];
                    $c['fours']      += $t['fours'];
                    $c['sixes']      += $t['sixes'];
                    $c['outs']       += $t['out'] ? 1 : 0;
                    $c['high_score']  = max($c['high_score'], $t['runs']);
                    unset($c);
                    $matchesOf[$pid][$match->id] = true;
                }
                foreach ($inn['bowling'] as $pid => $b) {
                    if ($b['balls'] <= 0 && $b['wickets'] <= 0) {
                        continue;
                    }
                    if (!isset($careerBowl[$pid])) {
                        $careerBowl[$pid] = ['wickets' => 0, 'balls' => 0, 'runs' => 0];
                    }
                    $careerBowl[$pid]['wickets'] += $b['wickets'];
                    $careerBowl[$pid]['balls']   += $b['balls'];
                    $careerBowl[$pid]['runs']    += $b['runs'];
                    $matchesOf[$pid][$match->id] = true;
                }
            }
        }

        // 1. Batting detail table.
        DB::transaction(function () use ($careerBat) {
            PlayerCareerBatting::query()->delete();
            foreach (array_chunk($careerBat, 200, true) as $chunk) {
                PlayerCareerBatting::insert(array_map(function ($row) {
                    $row['created_at'] = now();
                    $row['updated_at'] = now();
                    return $row;
                }, array_values($chunk)));
            }
        });

        // 2. Real aggregate totals onto users.career_* (what leaderboard/profile read).
        $allIds = array_unique(array_merge(array_keys($careerBat), array_keys($careerBowl)));
        foreach ($allIds as $pid) {
            $user = User::where('player_id', $pid)->first();
            if (!$user) {
                continue; // guests / players without an account carry no career columns
            }
            $bat = $careerBat[$pid] ?? ['runs' => 0, 'balls' => 0];
            $bowl = $careerBowl[$pid] ?? ['wickets' => 0, 'balls' => 0, 'runs' => 0];
            $ballsBowled = (int) $bowl['balls'];
            $user->update([
                'career_matches'        => count($matchesOf[$pid] ?? []),
                'career_runs'           => (int) $bat['runs'],
                'career_balls'          => (int) $bat['balls'],
                'career_wickets'        => (int) $bowl['wickets'],
                'career_runs_conceded'  => (int) $bowl['runs'],
                'career_overs_bowled'   => intdiv($ballsBowled, 6) . '.' . ($ballsBowled % 6),
            ]);
        }

        // 3. Re-rank on the now-real career_runs.
        PlayerStatsService::recalculateLeaderboardRankings();

        return count($careerBat);
    }

    /** The current real career batting line for one player id, or null if none yet. */
    public static function forPlayer(?string $playerId): ?PlayerCareerBatting
    {
        $id = self::normalizeId($playerId);
        if ($id === '') {
            return null;
        }
        return PlayerCareerBatting::where('player_id', $id)->first();
    }

    /**
     * Replay one match's action log into per-innings batting + bowling tallies keyed by
     * player id. Mirrors buildInningsCards' attribution, tracking IDs (not names) so it can
     * be aggregated across matches.
     *
     * @return array<int, array{batting: array<string, array>, bowling: array<string, array>}>
     */
    private static function replayInnings(LiveMatch $match): array
    {
        $idName = self::squadIdNameMap($match);
        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id', 'asc')
            ->get();

        $innings = [];
        $bat = null;        // current innings batting tally
        $bowl = null;       // current innings bowling tally
        $legal = 0;         // legal balls in the current innings (for the over flip)
        $strikerId = '';
        $nonStrikerId = '';
        $bowlerId = '';

        $ensureBat = function (&$tally, string $id) use ($idName) {
            if ($id !== '' && !isset($tally[$id])) {
                $tally[$id] = [
                    'name' => $idName[$id] ?? $id,
                    'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'out' => false,
                ];
            }
        };
        $ensureBowl = function (&$tally, string $id) {
            if ($id !== '' && !isset($tally[$id])) {
                $tally[$id] = ['wickets' => 0, 'balls' => 0, 'runs' => 0];
            }
        };

        $flush = function () use (&$innings, &$bat, &$bowl) {
            if ($bat !== null) {
                $innings[] = ['batting' => $bat, 'bowling' => $bowl ?? []];
            }
        };

        foreach ($actions as $act) {
            $type = (string) $act->action_type;
            $p = json_decode($act->payload, true) ?: [];

            if ($type === 'start') {
                $flush();
                $bat = []; $bowl = []; $legal = 0;
                $strikerId = self::normalizeId($p['striker_id'] ?? null);
                $nonStrikerId = self::normalizeId($p['non_striker_id'] ?? null);
                $bowlerId = self::normalizeId($p['bowler_id'] ?? null);
                $ensureBat($bat, $strikerId);
                $ensureBat($bat, $nonStrikerId);
                $ensureBowl($bowl, $bowlerId);
                continue;
            }
            if ($bat === null) {
                continue;
            }
            if ($type === 'change_bowler') {
                $bowlerId = self::normalizeId($p['bowler_id'] ?? null);
                $ensureBowl($bowl, $bowlerId);
                continue;
            }
            if ($type === 'change_batsman') {
                $id = self::normalizeId($p['id'] ?? null);
                if (($p['role'] ?? 'striker') === 'striker') { $strikerId = $id; } else { $nonStrikerId = $id; }
                $ensureBat($bat, $id);
                continue;
            }

            // ── A delivery ──
            $isLegal = true;
            $runsOffBat = 0;
            $extras = 0;
            $wicket = false;
            switch ($type) {
                case 'runs':   $runsOffBat = (int) ($p['value'] ?? 0); break;
                case 'wide':   $isLegal = false; $extras = (int) ($p['value'] ?? 1); break;
                case 'noball': $isLegal = false; $runsOffBat = (int) ($p['runs_off_bat'] ?? 0); $extras = 1; break;
                case 'bye':    $extras = (int) ($p['value'] ?? 1); break;
                case 'legbye': $extras = (int) ($p['value'] ?? 1); break;
                case 'wicket': $wicket = true; break;
                default: continue 2;
            }
            $total = $runsOffBat + $extras;

            // Striker faces every ball except a wide; byes/legbyes add no batting runs.
            if ($strikerId !== '' && isset($bat[$strikerId]) && $type !== 'wide') {
                $bat[$strikerId]['runs'] += $runsOffBat;
                $bat[$strikerId]['balls'] += 1;
                if ($type === 'runs' && $runsOffBat === 4) $bat[$strikerId]['fours'] += 1;
                if ($type === 'runs' && $runsOffBat === 6) $bat[$strikerId]['sixes'] += 1;
            }

            // Bowler: charged everything except byes/legbyes; counts legal balls; wickets.
            if ($bowlerId !== '' && isset($bowl[$bowlerId])) {
                if ($type !== 'bye' && $type !== 'legbye') {
                    $bowl[$bowlerId]['runs'] += $total;
                }
                if ($isLegal) {
                    $bowl[$bowlerId]['balls'] += 1;
                }
                if ($wicket) {
                    $bowl[$bowlerId]['wickets'] += 1;
                }
            }

            // Strike rotation on odd runs (byes/legbyes swap on their run count too).
            $runsToSwap = ($type === 'bye' || $type === 'legbye') ? $extras : $runsOffBat;
            if ($runsToSwap % 2 === 1) {
                [$strikerId, $nonStrikerId] = [$nonStrikerId, $strikerId];
            }

            if ($wicket) {
                if ($strikerId !== '' && isset($bat[$strikerId])) {
                    $bat[$strikerId]['out'] = true;
                }
                $strikerId = self::normalizeId($p['new_batsman_id'] ?? null);
                $ensureBat($bat, $strikerId);
            }

            if ($isLegal) {
                $legal++;
                if ($legal % 6 === 0) {
                    [$strikerId, $nonStrikerId] = [$nonStrikerId, $strikerId];
                }
            }
        }

        $flush();
        return $innings;
    }

    /** Map squad member id → display name for this match (registered players only). */
    private static function squadIdNameMap(LiveMatch $match): array
    {
        $map = [];
        foreach (array_merge($match->home_squad ?? [], $match->away_squad ?? []) as $pl) {
            $id = self::normalizeId($pl['id'] ?? null);
            if ($id !== '') {
                $map[$id] = (string) ($pl['name'] ?? $id);
            }
        }
        return $map;
    }

    /** Normalise a raw id to a clean string; '' for guests / missing / literal "null". */
    private static function normalizeId($v): string
    {
        $s = trim((string) ($v ?? ''));
        return ($s === '' || strtolower($s) === 'null') ? '' : $s;
    }
}
