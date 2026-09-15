<?php

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\PlayerCareerBatting;
use App\Models\PlayerCareerBowling;
use App\Models\PlayerCareerFielding;
use App\Models\User;
use App\Support\CricketRules;
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
    /**
     * Full rebuild for a backfill (`php artisan stats:rebuild`): re-derive every finished
     * match's player rows, then every career from them, then the rankings.
     *
     * Never call this on a request path — it is proportional to every match ever played.
     * Finishing a match goes through {@see MatchCompletion}, which touches only that match
     * and its players.
     *
     * @return int players whose careers were refreshed
     */
    public static function rebuildAll(?callable $progress = null): int
    {
        $stats = app(\App\Services\Stats\MatchPlayerStatsService::class);
        $careers = app(\App\Services\Stats\PlayerCareerService::class);

        LiveMatch::query()->finished()->orderBy('id')->chunkById(100, function ($matches) use ($stats, $progress): void {
            foreach ($matches as $match) {
                $stats->rebuild($match);
                if ($progress !== null) {
                    $progress($match);
                }
            }
        });

        // Careers of players who no longer have any finished match must also be cleared.
        $ids = array_values(array_unique(array_merge(
            $careers->allPlayerIds(),
            PlayerCareerBatting::query()->pluck('player_id')->all(),
            PlayerCareerBowling::query()->pluck('player_id')->all(),
            PlayerCareerFielding::query()->pluck('player_id')->all(),
            \App\Models\PlayerSportCareer::query()->distinct()->pluck('player_id')->all(),
            // Accounts still carrying career figures from the retired mt_rand generator.
            User::query()->whereNotNull('player_id')
                ->where(fn ($q) => $q->where('career_runs', '>', 0)->orWhere('career_wickets', '>', 0)->orWhere('career_matches', '>', 0))
                ->pluck('player_id')->all(),
        )));
        $careers->refresh($ids);
        app(\App\Services\Stats\LeaderboardRankService::class)->recalculate();

        return count($ids);
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
    /**
     * One match's replay, by innings — the per-match stats calculator's cricket source.
     *
     * @return array<int, array{batting: array<string, array>, bowling: array<string, array>, fielding: array<string, array>}>
     */
    public static function replayMatch(LiveMatch $match): array
    {
        return self::replayInnings($match);
    }

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
        $field = null;      // current innings fielding tally
        $legal = 0;         // legal balls in the current innings (for the over flip)
        $strikerId = '';
        $nonStrikerId = '';
        $bowlerId = '';
        // Runs charged to the bowler in the over being bowled, and who is bowling it.
        // A maiden is decided at the over boundary, never from a total.
        $overCharged = 0;
        $overBowler = '';

        $ensureBat = function (&$tally, string $id) use ($idName) {
            if ($id !== '' && !isset($tally[$id])) {
                $tally[$id] = [
                    'name' => $idName[$id] ?? $id,
                    'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'out' => false,
                    // zone index => ['shots','fours','sixes','runs']. Only the balls the
                    // scorer actually placed; a missing zone is left out, never guessed.
                    'zones' => [],
                ];
            }
        };
        $ensureBowl = function (&$tally, string $id) use ($idName) {
            if ($id !== '' && !isset($tally[$id])) {
                $tally[$id] = [
                    'name' => $idName[$id] ?? $id,
                    'wickets' => 0, 'balls' => 0, 'runs' => 0, 'maidens' => 0,
                ];
            }
        };

        $ensureField = function (&$tally, string $id) use ($idName) {
            if ($id !== '' && !isset($tally[$id])) {
                $tally[$id] = [
                    'name' => $idName[$id] ?? $id,
                    'catches' => 0, 'run_outs' => 0, 'stumpings' => 0,
                ];
            }
        };

        $flush = function () use (&$innings, &$bat, &$bowl, &$field) {
            if ($bat !== null) {
                $innings[] = [
                    'batting' => $bat,
                    'bowling' => $bowl ?? [],
                    'fielding' => $field ?? [],
                ];
            }
        };

        foreach ($actions as $act) {
            $type = (string) $act->action_type;
            $p = json_decode($act->payload, true) ?: [];

            if ($type === 'start') {
                $flush();
                $bat = []; $bowl = []; $field = []; $legal = 0;
                $strikerId = self::normalizeId($p['striker_id'] ?? null);
                $nonStrikerId = self::normalizeId($p['non_striker_id'] ?? null);
                $bowlerId = self::normalizeId($p['bowler_id'] ?? null);
                $overCharged = 0;
                $overBowler = $bowlerId;
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
                // Where it went, when the scorer said. The picker only asks on boundaries
                // and is skippable, so most deliveries carry no zone at all.
                $zone = $p['zone'] ?? null;
                if ($type === 'runs' && is_numeric($zone) && (int) $zone >= 0 && (int) $zone <= 7) {
                    $z = (int) $zone;
                    $bat[$strikerId]['zones'][$z] ??= ['shots' => 0, 'fours' => 0, 'sixes' => 0, 'runs' => 0];
                    $bat[$strikerId]['zones'][$z]['shots'] += 1;
                    $bat[$strikerId]['zones'][$z]['runs'] += $runsOffBat;
                    if ($runsOffBat === 4) $bat[$strikerId]['zones'][$z]['fours'] += 1;
                    if ($runsOffBat === 6) $bat[$strikerId]['zones'][$z]['sixes'] += 1;
                }
            }

            // Bowler: charged everything except byes/legbyes; counts legal balls; wickets.
            if ($bowlerId !== '' && isset($bowl[$bowlerId])) {
                if ($type !== 'bye' && $type !== 'legbye') {
                    $bowl[$bowlerId]['runs'] += $total;
                    // Byes and leg-byes are not the bowler's runs, and by the same rule
                    // they do not cost him the maiden.
                    $overCharged += $total;
                }
                if ($overBowler === '') {
                    $overBowler = $bowlerId;
                }
                if ($isLegal) {
                    $bowl[$bowlerId]['balls'] += 1;
                }
                if ($wicket && CricketRules::bowlerCredited($p)) {
                    $bowl[$bowlerId]['wickets'] += 1;
                }
            }

            // Strike rotation on odd runs (byes/legbyes swap on their run count too).
            $runsToSwap = ($type === 'bye' || $type === 'legbye') ? $extras : $runsOffBat;
            if ($runsToSwap % 2 === 1) {
                [$strikerId, $nonStrikerId] = [$nonStrikerId, $strikerId];
            }

            if ($wicket) {
                $nonStrikerOut = CricketRules::nonStrikerOut($p);
                $outId = $nonStrikerOut ? $nonStrikerId : $strikerId;
                if ($outId !== '' && isset($bat[$outId]) && CricketRules::countsAsWicket($p)) {
                    $bat[$outId]['out'] = true;
                }
                // Who actually made the dismissal. Absent on every ball scored before
                // the scorer asked the question, and on a bowled/LBW, which belong to
                // the bowler alone - so a missing fielder is normal, not a data fault.
                $fielderId = self::normalizeId($p['fielder_id'] ?? null);
                $how = strtolower((string) ($p['dismissal'] ?? ''));
                if ($fielderId !== '' && in_array($how, ['caught', 'runout', 'stumped'], true)) {
                    $ensureField($field, $fielderId);
                    $key = match ($how) {
                        'caught' => 'catches',
                        'runout' => 'run_outs',
                        default => 'stumpings',
                    };
                    $field[$fielderId][$key] += 1;
                }
                $incoming = self::normalizeId($p['new_batsman_id'] ?? null);
                if ($nonStrikerOut) {
                    $nonStrikerId = $incoming;
                } else {
                    $strikerId = $incoming;
                }
                $ensureBat($bat, $incoming);
            }

            if ($isLegal) {
                $legal++;
                if ($legal % 6 === 0) {
                    if ($overCharged === 0 && $overBowler !== '' && isset($bowl[$overBowler])) {
                        $bowl[$overBowler]['maidens'] += 1;
                    }
                    $overCharged = 0;
                    $overBowler = '';
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
