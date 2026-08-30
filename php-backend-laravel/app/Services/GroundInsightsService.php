<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchGround;
use Illuminate\Support\Facades\DB;

/**
 * What has actually happened at a ground.
 *
 * Every figure here is replayed from `match_actions` — the same ball log the scorecard
 * and the career tables read — so a ground's numbers cannot disagree with the matches
 * that produced them. Nothing is taken from the denormalised score columns, which are
 * written by several paths and drift.
 *
 * What is deliberately NOT computed, however much the reference cards show it:
 *
 *   · a pace/spin wicket split — the ball log has no bowler type yet. The scorer has
 *     only just started recording it, and until that has run for a season, any split
 *     would be a guess dressed as a measurement.
 *   · a "batting friendly" verdict — that is a conclusion, and it belongs to the
 *     confidence band, not to two matches at a school field.
 *
 * The stats are cached on the ground row and refreshed when a match there completes.
 * Replaying a season of deliveries to draw one card on every request is not a thing to
 * do to a shared server.
 */
final class GroundInsightsService
{
    /** Recompute and store the read on one ground. */
    public function refresh(MatchGround $ground): MatchGround
    {
        $matches = LiveMatch::query()
            ->where('ground_id', $ground->id)
            ->whereRaw('lower(status) = ?', ['completed'])
            ->get();

        $firstInningsTotals = [];
        $allTotals = [];
        $bestIndividual = 0;
        $bestIndividualBy = null;
        $boundaryRuns = 0;
        $totalRuns = 0;
        $totalBalls = 0;
        $battingFirstWins = 0;
        $decided = 0;

        foreach ($matches as $match) {
            $innings = $this->replay($match);
            if ($innings === []) {
                continue; // Not ball-by-ball scored; it contributes nothing honest.
            }

            foreach ($innings as $i => $inn) {
                $allTotals[] = $inn['runs'];
                if ($i === 0) {
                    $firstInningsTotals[] = $inn['runs'];
                }
                $boundaryRuns += $inn['boundaryRuns'];
                $totalRuns += $inn['runs'];
                $totalBalls += $inn['balls'];
                foreach ($inn['batters'] as $name => $runs) {
                    if ($runs > $bestIndividual) {
                        $bestIndividual = $runs;
                        $bestIndividualBy = $name;
                    }
                }
            }

            // Who won is only knowable when both sides batted. A match abandoned after
            // one innings is not a data point about chasing.
            if (count($innings) >= 2) {
                $decided++;
                if ($innings[0]['runs'] > $innings[1]['runs']) {
                    $battingFirstWins++;
                }
            }
        }

        $played = $matches->count();
        $ground->fill([
            'matches_played' => $played,
            'first_innings_avg' => $firstInningsTotals === []
                ? 0
                : (int) round(array_sum($firstInningsTotals) / count($firstInningsTotals)),
            'highest_total' => $allTotals === [] ? 0 : max($allTotals),
            'best_individual' => $bestIndividual,
            'best_individual_by' => $bestIndividualBy,
            'boundary_percent' => $totalRuns > 0 ? (int) round($boundaryRuns * 100 / $totalRuns) : 0,
            'run_rate' => $totalBalls > 0
                ? number_format($totalRuns * 6 / $totalBalls, 2, '.', '')
                : null,
            'batting_first_wins' => $battingFirstWins,
            'decided_matches' => $decided,
            'stats_at' => now(),
        ])->save();

        return $ground;
    }

    /**
     * One match's innings, replayed.
     *
     * Mirrors the attribution CareerBattingService uses, kept to what a ground card
     * needs: the total, the balls, the boundary runs and each batter's score.
     *
     * @return list<array{runs:int, balls:int, boundaryRuns:int, batters: array<string,int>}>
     */
    private function replay(LiveMatch $match): array
    {
        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id')
            ->get();

        $innings = [];
        $current = null;
        $striker = '';
        $names = $this->squadNames($match);

        $flush = function () use (&$current, &$innings): void {
            if ($current !== null && $current['balls'] > 0) {
                $innings[] = $current;
            }
        };

        foreach ($actions as $action) {
            $type = (string) $action->action_type;
            $p = json_decode((string) $action->payload, true) ?: [];

            if ($type === 'start') {
                $flush();
                $current = ['runs' => 0, 'balls' => 0, 'boundaryRuns' => 0, 'batters' => []];
                $striker = (string) ($p['striker_id'] ?? '');
                continue;
            }
            if ($current === null) {
                continue;
            }
            if ($type === 'change_batsman') {
                if (($p['role'] ?? 'striker') === 'striker') {
                    $striker = (string) ($p['id'] ?? '');
                }
                continue;
            }

            $offBat = 0;
            $extras = 0;
            $legal = true;
            switch ($type) {
                case 'runs':   $offBat = (int) ($p['value'] ?? 0); break;
                case 'wide':   $legal = false; $extras = (int) ($p['value'] ?? 1); break;
                case 'noball': $legal = false; $offBat = (int) ($p['runs_off_bat'] ?? 0); $extras = 1; break;
                case 'bye':
                case 'legbye': $extras = (int) ($p['value'] ?? 1); break;
                case 'wicket': break;
                default: continue 2;
            }

            $current['runs'] += $offBat + $extras;
            if ($legal) {
                $current['balls']++;
            }
            if ($offBat === 4 || $offBat === 6) {
                $current['boundaryRuns'] += $offBat;
            }
            if ($striker !== '' && $type !== 'wide') {
                $label = $names[$striker] ?? $striker;
                $current['batters'][$label] = ($current['batters'][$label] ?? 0) + $offBat;
            }
            if ($type === 'wicket') {
                $striker = (string) ($p['new_batsman_id'] ?? '');
            }
        }
        $flush();

        return $innings;
    }

    /** @return array<string,string> squad id => display name */
    private function squadNames(LiveMatch $match): array
    {
        $map = [];
        foreach (array_merge($match->home_squad ?? [], $match->away_squad ?? []) as $player) {
            $id = trim((string) ($player['id'] ?? ''));
            if ($id !== '' && strtolower($id) !== 'null') {
                $map[$id] = (string) ($player['name'] ?? $id);
            }
        }

        return $map;
    }
}
