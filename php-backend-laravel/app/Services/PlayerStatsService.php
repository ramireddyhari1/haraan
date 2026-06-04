<?php

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\PlayerMatchStat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlayerStatsService
{
    /**
     * Freeze match stats and update all rankings.
     */
    public static function freezeMatchStats(LiveMatch $match): void
    {
        // 1. Delete existing stats for this match (idempotent)
        PlayerMatchStat::where('match_id', $match->id)->delete();

        // 2. Parse squads
        $homeSquad = $match->home_squad ?? [];
        $awaySquad = $match->away_squad ?? [];

        // Build default lists (IPL profiles) as fallback just like in scorecard view
        $hhr_defaults = ["Abhishek Sharma", "Travis Head", "Nitish Reddy", "Heinrich Klaasen", "Abdul Samad", "Shahbaz Ahmed", "Pat Cummins", "Bhuvneshwar Kumar", "Jaydev Unadkat", "T Natarajan", "Mayank Markande"];
        $mi_defaults = ["Rohit Sharma", "Ishan Kishan", "Suryakumar Yadav", "Tilak Varma", "Hardik Pandya", "Tim David", "Gerald Coetzee", "Jasprit Bumrah", "Piyush Chawla", "Nuwan Thushara", "Anshul Kamboj"];

        // Squad padding helper
        $padSquad = function($squad, $defaults) {
            $resolved = [];
            $seen = [];
            foreach ($squad as $p) {
                $name = is_array($p) ? ($p['name'] ?? '') : $p;
                $id = is_array($p) ? ($p['id'] ?? '') : '';
                if (!empty($name)) {
                    $resolved[] = ['id' => $id, 'name' => $name];
                    $seen[$name] = true;
                }
            }
            foreach ($defaults as $name) {
                if (count($resolved) >= 11) break;
                if (!isset($seen[$name])) {
                    $resolved[] = ['id' => null, 'name' => $name];
                    $seen[$name] = true;
                }
            }
            while (count($resolved) < 11) {
                $num = count($resolved) + 1;
                $resolved[] = ['id' => null, 'name' => 'Player ' . $num];
            }
            return $resolved;
        };

        $resolvedHomeSquad = $padSquad($homeSquad, $hhr_defaults);
        $resolvedAwaySquad = $padSquad($awaySquad, $mi_defaults);

        // Helper to resolve player name/ID
        $resolvePlayerDetails = function($nameOrId, $squads) {
            foreach ($squads as $player) {
                if ($player['id'] === $nameOrId || $player['name'] === $nameOrId) {
                    return $player;
                }
            }
            return ['id' => null, 'name' => $nameOrId];
        };

        // Active stats
        $activeBatters = [];
        $allSquads = array_merge($resolvedHomeSquad, $resolvedAwaySquad);
        foreach ($match->batters ?? [] as $b) {
            $pDetails = $resolvePlayerDetails($b['name'], $allSquads);
            $activeBatters[] = [
                'id' => $pDetails['id'],
                'name' => $pDetails['name'],
                'runs' => $b['runs'] ?? 0,
                'balls' => $b['balls'] ?? 0
            ];
        }

        $activeBowler = null;
        if (!empty($match->bowler)) {
            $pDetails = $resolvePlayerDetails($match->bowler['name'], $allSquads);
            $activeBowler = [
                'id' => $pDetails['id'],
                'name' => $pDetails['name'],
                'figures' => $match->bowler['figures'] ?? '0-0',
                'overs' => $match->bowler['overs'] ?? '0.0'
            ];
        }

        $overSummary = $match->over_summary ?? [];
        $battingTeam = 'home';
        if (!empty($overSummary)) {
            $lastOver = end($overSummary);
            $battingTeam = $lastOver['batting'] ?? 'home';
        }

        $homeSummaryWickets = 0;
        $awaySummaryWickets = 0;
        $innings1Overs = [];
        $innings2Overs = [];

        foreach ($overSummary as $o) {
            $oBatting = $o['batting'] ?? 'home';
            if ($oBatting === 'home') {
                $innings1Overs[] = $o;
                foreach ($o['balls'] ?? [] as $ball) {
                    if (strtoupper(trim((string)$ball)) === 'W') {
                        $homeSummaryWickets++;
                    }
                }
            } else {
                $innings2Overs[] = $o;
                foreach ($o['balls'] ?? [] as $ball) {
                    if (strtoupper(trim((string)$ball)) === 'W') {
                        $awaySummaryWickets++;
                    }
                }
            }
        }

        $scoreText = $match->score_text ?? (($match->home_score ?? 0) . '-' . ($match->away_score ?? 0));
        $wicketsCount = 0;
        if (preg_match('/-(\d+)/', $scoreText, $m)) {
            $wicketsCount = (int)$m[1];
        } else {
            $wicketsCount = ($battingTeam === 'home') ? $homeSummaryWickets : $awaySummaryWickets;
        }

        // Innings parameters
        $i1Runs = $match->home_score ?? 0;
        $i1Wickets = ($battingTeam === 'home') ? $wicketsCount : $homeSummaryWickets;
        $i1Overs = ($battingTeam === 'home') ? ($match->overs ?? '0.0') : (count($innings1Overs) . '.0');
        $i1BattersLive = ($battingTeam === 'home') ? $activeBatters : [];
        $i1BowlerLive = ($battingTeam === 'home') ? $activeBowler : null;

        $i2Runs = $match->away_score ?? 0;
        $i2Wickets = ($battingTeam === 'away') ? $wicketsCount : $awaySummaryWickets;
        $i2Overs = ($battingTeam === 'away') ? ($match->overs ?? '0.0') : (count($innings2Overs) . '.0');
        $i2BattersLive = ($battingTeam === 'away') ? $activeBatters : [];
        $i2BowlerLive = ($battingTeam === 'away') ? $activeBowler : null;

        // Deterministic Batting Scorecard Generator (matching actionboard-match-scorecard.blade.php)
        $buildBattingStats = function($squad, $opponentSquad, $totalRuns, $totalWickets, $activeBatters) {
            $stats = [];
            $activeRunsSum = 0;
            foreach ($activeBatters as $ab) {
                $activeRunsSum += (int)$ab['runs'];
            }

            $extras = min(6, max(1, $totalRuns - $activeRunsSum));
            $remainingRuns = max(0, $totalRuns - $activeRunsSum - $extras);
            $dismissedCount = $totalWickets;

            mt_srand($totalRuns + 12);
            
            $dismissedRuns = [];
            if ($dismissedCount > 0) {
                $avgRuns = intval($remainingRuns / $dismissedCount);
                for ($i = 0; $i < $dismissedCount; $i++) {
                    if ($i == $dismissedCount - 1) {
                        $dismissedRuns[] = $remainingRuns - array_sum($dismissedRuns);
                    } else {
                        $r = ($avgRuns > 0) ? mt_rand(intval($avgRuns * 0.4), intval($avgRuns * 1.4)) : 0;
                        $dismissedRuns[] = $r;
                    }
                }
            }

            $dismissedPtr = 0;
            for ($i = 0; $i < 11; $i++) {
                $player = $squad[$i]; // array with id & name
                $isActivePlayer = false;
                $activeStat = null;
                foreach ($activeBatters as $ab) {
                    if ($ab['name'] === $player['name']) {
                        $isActivePlayer = true;
                        $activeStat = $ab;
                        break;
                    }
                }

                if ($isActivePlayer) {
                    $r = (int)$activeStat['runs'];
                    $b = (int)$activeStat['balls'];
                    $stats[] = [
                        'id' => $player['id'],
                        'name' => $player['name'],
                        'runs' => $r,
                        'balls' => $b,
                    ];
                } elseif ($dismissedPtr < $dismissedCount) {
                    $r = $dismissedRuns[$dismissedPtr] ?? 0;
                    $b = max(1, intval($r * (mt_rand(75, 120) / 100)));
                    $stats[] = [
                        'id' => $player['id'],
                        'name' => $player['name'],
                        'runs' => $r,
                        'balls' => $b,
                    ];
                    $dismissedPtr++;
                } else {
                    $stats[] = [
                        'id' => $player['id'],
                        'name' => $player['name'],
                        'runs' => 0,
                        'balls' => 0,
                    ];
                }
            }
            return $stats;
        };

        // Deterministic Bowling Scorecard Generator (matching actionboard-match-scorecard.blade.php)
        $buildBowlingStats = function($opponentSquad, $overSummary, $activeBowler, $isActiveInnings) {
            $bowlersStats = [];
            
            foreach ($overSummary as $o) {
                $bName = $o['bowler'] ?? null;
                if (!$bName) {
                    $bName = $opponentSquad[9]['name'] ?? 'Pat Cummins';
                }
                
                if (!isset($bowlersStats[$bName])) {
                    $bowlersStats[$bName] = [
                        'name' => $bName,
                        'id' => null,
                        'balls' => 0,
                        'runs' => 0,
                        'wickets' => 0
                    ];
                }
                
                // Find ID if exists
                foreach ($opponentSquad as $opp) {
                    if ($opp['name'] === $bName) {
                        $bowlersStats[$bName]['id'] = $opp['id'];
                        break;
                    }
                }
                
                $bowlersStats[$bName]['runs'] += $o['runs'];
                
                $validBalls = 0;
                $wktCount = 0;
                foreach ($o['balls'] ?? [] as $ball) {
                    $b = strtolower(trim((string)$ball));
                    if (strpos($b, 'wd') === false && strpos($b, 'nb') === false) {
                        $validBalls++;
                    }
                    if ($b === 'w') {
                        $wktCount++;
                    }
                }
                
                $bowlersStats[$bName]['balls'] += $validBalls;
                $bowlersStats[$bName]['wickets'] += $wktCount;
            }
            
            if ($isActiveInnings && !empty($activeBowler) && isset($activeBowler['name'])) {
                $abName = $activeBowler['name'];
                $figures = $activeBowler['figures'] ?? '0-0';
                $figParts = explode('-', $figures);
                $liveWickets = isset($figParts[0]) ? (int)$figParts[0] : 0;
                $liveRuns = isset($figParts[1]) ? (int)$figParts[1] : 0;
                
                $liveOversStr = $activeBowler['overs'] ?? '0.0';
                $overParts = explode('.', $liveOversStr);
                $liveCompleted = isset($overParts[0]) ? (int)$overParts[0] : 0;
                $liveBalls = isset($overParts[1]) ? (int)$overParts[1] : 0;
                $liveTotalBalls = $liveCompleted * 6 + $liveBalls;
                
                $bowlersStats[$abName] = [
                    'name' => $abName,
                    'id' => $activeBowler['id'],
                    'balls' => $liveTotalBalls,
                    'runs' => $liveRuns,
                    'wickets' => $liveWickets
                ];
            }
            
            $list = [];
            foreach ($bowlersStats as $name => $stat) {
                $compOvers = intval($stat['balls'] / 6);
                $ballsRem = $stat['balls'] % 6;
                $oversStr = $compOvers . '.' . $ballsRem;
                
                $list[] = [
                    'id' => $stat['id'],
                    'name' => $name,
                    'overs' => $oversStr,
                    'runs' => $stat['runs'],
                    'wickets' => $stat['wickets']
                ];
            }
            
            if (empty($list)) {
                for ($i = 6; $i < min(11, count($opponentSquad)); $i++) {
                    $list[] = [
                        'id' => $opponentSquad[$i]['id'],
                        'name' => $opponentSquad[$i]['name'],
                        'overs' => '0.0',
                        'runs' => 0,
                        'wickets' => 0
                    ];
                }
            }
            
            return $list;
        };

        // 3. Build Innings Batting scores
        $i1BattingStats = $buildBattingStats($resolvedHomeSquad, $resolvedAwaySquad, $i1Runs, $i1Wickets, $i1BattersLive);
        $i2BattingStats = $buildBattingStats($resolvedAwaySquad, $resolvedHomeSquad, $i2Runs, $i2Wickets, $i2BattersLive);

        // 4. Build Innings Bowling scores
        $i1BowlingStats = $buildBowlingStats($resolvedAwaySquad, $innings1Overs, $i1BowlerLive, $battingTeam === 'home');
        $i2BowlingStats = $buildBowlingStats($resolvedHomeSquad, $innings2Overs, $i2BowlerLive, $battingTeam === 'away');

        // 5. Store them in database table for players with a valid player_id
        $matchStats = [];

        // We combine the resolved lists
        // Innings 1: Home bats, Away bowls
        foreach ($i1BattingStats as $bat) {
            if (!$bat['id']) continue;
            $matchStats[$bat['id']] = [
                'match_id' => $match->id,
                'player_id' => $bat['id'],
                'player_name' => $bat['name'],
                'runs' => $bat['runs'],
                'balls' => $bat['balls'],
                'wickets' => 0,
                'overs_bowled' => '0.0',
                'runs_conceded' => 0
            ];
        }

        foreach ($i1BowlingStats as $bowl) {
            if (!$bowl['id']) continue;
            if (!isset($matchStats[$bowl['id']])) {
                $matchStats[$bowl['id']] = [
                    'match_id' => $match->id,
                    'player_id' => $bowl['id'],
                    'player_name' => $bowl['name'],
                    'runs' => 0,
                    'balls' => 0
                ];
            }
            $matchStats[$bowl['id']]['wickets'] = $bowl['wickets'];
            $matchStats[$bowl['id']]['overs_bowled'] = $bowl['overs'];
            $matchStats[$bowl['id']]['runs_conceded'] = $bowl['runs'];
        }

        // Innings 2: Away bats, Home bowls
        foreach ($i2BattingStats as $bat) {
            if (!$bat['id']) continue;
            if (!isset($matchStats[$bat['id']])) {
                $matchStats[$bat['id']] = [
                    'match_id' => $match->id,
                    'player_id' => $bat['id'],
                    'player_name' => $bat['name'],
                    'runs' => 0,
                    'balls' => 0,
                    'wickets' => 0,
                    'overs_bowled' => '0.0',
                    'runs_conceded' => 0
                ];
            }
            $matchStats[$bat['id']]['runs'] = $bat['runs'];
            $matchStats[$bat['id']]['balls'] = $bat['balls'];
        }

        foreach ($i2BowlingStats as $bowl) {
            if (!$bowl['id']) continue;
            if (!isset($matchStats[$bowl['id']])) {
                $matchStats[$bowl['id']] = [
                    'match_id' => $match->id,
                    'player_id' => $bowl['id'],
                    'player_name' => $bowl['name'],
                    'runs' => 0,
                    'balls' => 0
                ];
            }
            $matchStats[$bowl['id']]['wickets'] = $bowl['wickets'];
            $matchStats[$bowl['id']]['overs_bowled'] = $bowl['overs'];
            $matchStats[$bowl['id']]['runs_conceded'] = $bowl['runs'];
        }

        // Insert into player_match_stats DB
        foreach ($matchStats as $pId => $data) {
            PlayerMatchStat::create($data);
            
            // Reaggregate career stats for the player
            self::reaggregatePlayerStats($pId);
        }

        // 6. Recalculate ranks across all players
        self::recalculateLeaderboardRankings();
    }

    /**
     * Recalculate Career Stats for a specific player from match stats history.
     */
    public static function reaggregatePlayerStats(string $playerId): void
    {
        $user = User::where('player_id', $playerId)->first();
        if (!$user) return;

        $stats = PlayerMatchStat::where('player_id', $playerId)->get();

        $careerRuns = 0;
        $careerBalls = 0;
        $careerMatches = $stats->count();
        $careerWickets = 0;
        $careerRunsConceded = 0;
        $totalOversBalls = 0;

        foreach ($stats as $stat) {
            $careerRuns += $stat->runs;
            $careerBalls += $stat->balls;
            $careerWickets += $stat->wickets;
            $careerRunsConceded += $stat->runs_conceded;

            // Parse overs_bowled to balls
            $oversStr = $stat->overs_bowled ?? '0.0';
            $parts = explode('.', $oversStr);
            $completed = isset($parts[0]) ? (int)$parts[0] : 0;
            $balls = isset($parts[1]) ? (int)$parts[1] : 0;
            $totalOversBalls += ($completed * 6) + $balls;
        }

        $completedOvers = intval($totalOversBalls / 6);
        $ballsRem = $totalOversBalls % 6;
        $careerOversBowled = "{$completedOvers}.{$ballsRem}";

        $user->update([
            'career_runs' => $careerRuns,
            'career_balls' => $careerBalls,
            'career_matches' => $careerMatches,
            'career_wickets' => $careerWickets,
            'career_runs_conceded' => $careerRunsConceded,
            'career_overs_bowled' => $careerOversBowled,
        ]);
    }

    /**
     * Recalculate all rankings (Country, State, District) based on career runs.
     */
    public static function recalculateLeaderboardRankings(): void
    {
        // 1. Country (India) rankings
        $countryPlayers = User::where('is_guest', false)
            ->whereNotNull('district')
            ->whereNotNull('state')
            ->orderBy('career_runs', 'desc')
            ->get();
        
        $rank = 1;
        foreach ($countryPlayers as $p) {
            $p->update(['rank_country' => $rank++]);
        }

        // 2. State-level rankings
        $states = User::where('is_guest', false)
            ->whereNotNull('state')
            ->pluck('state')
            ->unique();

        foreach ($states as $state) {
            $statePlayers = User::where('is_guest', false)
                ->where('state', $state)
                ->orderBy('career_runs', 'desc')
                ->get();
            
            $rank = 1;
            foreach ($statePlayers as $p) {
                $p->update(['rank_state' => $rank++]);
            }
        }

        // 3. District-level rankings
        $districts = User::where('is_guest', false)
            ->whereNotNull('district')
            ->pluck('district')
            ->unique();

        foreach ($districts as $district) {
            $districtPlayers = User::where('is_guest', false)
                ->where('district', $district)
                ->orderBy('career_runs', 'desc')
                ->get();
            
            $rank = 1;
            foreach ($districtPlayers as $p) {
                $p->update(['rank_district' => $rank++]);
            }
        }
    }
}
