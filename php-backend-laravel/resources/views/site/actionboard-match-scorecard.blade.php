@extends('site.actionboard-match-layout')
@section('match_content')
@php
    $homeTeam = $match['home'];
    $awayTeam = $match['away'];
    $scoreText = $match['score_text'] ?? (($match['home_score'] ?? 0) . '-' . ($match['away_score'] ?? 0));
    $overs = $match['overs'] ?? $match['time'];
    $decision = $match['decision'] ?? '';

    // Curated pad players matching IPL profiles
    $hhr_defaults = ["Abhishek Sharma", "Travis Head", "Nitish Reddy", "Heinrich Klaasen", "Abdul Samad", "Shahbaz Ahmed", "Pat Cummins", "Bhuvneshwar Kumar", "Jaydev Unadkat", "T Natarajan", "Mayank Markande"];
    $mi_defaults = ["Rohit Sharma", "Ishan Kishan", "Suryakumar Yadav", "Tilak Varma", "Hardik Pandya", "Tim David", "Gerald Coetzee", "Jasprit Bumrah", "Piyush Chawla", "Nuwan Thushara", "Anshul Kamboj"];

    // Squad resolver and padder helper
    $padSquad = function($squad, $defaults) {
        $resolved = [];
        $seen = [];
        foreach ($squad as $p) {
            $name = is_array($p) ? ($p['name'] ?? '') : $p;
            if (!empty($name)) {
                $resolved[] = $name;
                $seen[$name] = true;
            }
        }
        foreach ($defaults as $name) {
            if (count($resolved) >= 11) break;
            if (!isset($seen[$name])) {
                $resolved[] = $name;
                $seen[$name] = true;
            }
        }
        while (count($resolved) < 11) {
            $resolved[] = 'Player ' . (count($resolved) + 1);
        }
        return $resolved;
    };

    $homeSquad = $padSquad($match['home_squad'] ?? [], $hhr_defaults);
    $awaySquad = $padSquad($match['away_squad'] ?? [], $mi_defaults);

    // Helper to resolve player name if stored as ID
    $resolvePlayerName = function($nameOrId) use ($match) {
        if (empty($nameOrId)) return '';
        $squads = array_merge($match['home_squad'] ?? [], $match['away_squad'] ?? []);
        foreach ($squads as $player) {
            $id = is_array($player) ? ($player['id'] ?? null) : $player;
            $name = is_array($player) ? ($player['name'] ?? null) : null;
            if ((string)$id === (string)$nameOrId) {
                return $name ?? $id;
            }
        }
        return $nameOrId;
    };

    $activeBatters = [];
    foreach ($match['batters'] ?? [] as $b) {
        $activeBatters[] = [
            'name' => $resolvePlayerName($b['name']),
            'runs' => $b['runs'] ?? 0,
            'balls' => $b['balls'] ?? 0
        ];
    }

    $activeBowler = null;
    if (!empty($match['bowler'])) {
        $activeBowler = [
            'name' => $resolvePlayerName($match['bowler']['name']),
            'figures' => $match['bowler']['figures'] ?? '0-0',
            'overs' => $match['bowler']['overs'] ?? '0.0'
        ];
    }

    // Parse over summaries
    $overSummary = $match['over_summary'] ?? [];

    // Determine current batting team from the last over in over_summary
    $battingTeam = 'home'; // default
    if (!empty($overSummary)) {
        $lastOver = end($overSummary);
        $battingTeam = isset($lastOver['batting']) ? $lastOver['batting'] : 'home';
    }

    // Partition over summary and count wickets per innings
    $homeSummaryWickets = 0;
    $awaySummaryWickets = 0;
    $innings1Overs = [];
    $innings2Overs = [];
    
    foreach ($overSummary as $o) {
        $oBatting = $o['batting'] ?? 'home';
        if ($oBatting === 'home') {
            $innings1Overs[] = $o;
            if (isset($o['balls']) && is_array($o['balls'])) {
                foreach ($o['balls'] as $ball) {
                    if (strtoupper(trim((string)$ball)) === 'W') {
                        $homeSummaryWickets++;
                    }
                }
            }
        } else {
            $innings2Overs[] = $o;
            if (isset($o['balls']) && is_array($o['balls'])) {
                foreach ($o['balls'] as $ball) {
                    if (strtoupper(trim((string)$ball)) === 'W') {
                        $awaySummaryWickets++;
                    }
                }
            }
        }
    }

    // Parse current active wickets count from score text
    $wicketsCount = 0;
    if (preg_match('/-(\d+)/', $scoreText, $m)) {
        $wicketsCount = (int)$m[1];
    } else {
        $wicketsCount = ($battingTeam === 'home') ? $homeSummaryWickets : $awaySummaryWickets;
    }

    // Setup Innings Data
    $innings1 = [
        'batting_team' => $homeTeam,
        'bowling_team' => $awayTeam,
        'batting_squad' => $homeSquad,
        'bowling_squad' => $awaySquad,
        'runs' => $match['home_score'] ?? 0,
        'wickets' => ($battingTeam === 'home') ? $wicketsCount : $homeSummaryWickets,
        'overs' => ($battingTeam === 'home') ? $overs : (count($innings1Overs) . '.0'),
        'is_active' => ($battingTeam === 'home'),
        'batters_live' => ($battingTeam === 'home') ? $activeBatters : [],
        'bowler_live' => ($battingTeam === 'home') ? $activeBowler : null
    ];

    $innings2 = [
        'batting_team' => $awayTeam,
        'bowling_team' => $homeTeam,
        'batting_squad' => $awaySquad,
        'bowling_squad' => $homeSquad,
        'runs' => $match['away_score'] ?? 0,
        'wickets' => ($battingTeam === 'away') ? $wicketsCount : $awaySummaryWickets,
        'overs' => ($battingTeam === 'away') ? $overs : (count($innings2Overs) . '.0'),
        'is_active' => ($battingTeam === 'away'),
        'batters_live' => ($battingTeam === 'away') ? $activeBatters : [],
        'bowler_live' => ($battingTeam === 'away') ? $activeBowler : null
    ];

    // Scorecard calculators
    $buildBattingScorecard = function($squad, $opponentSquad, $totalRuns, $totalWickets, $activeBatters) {
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
            $player = $squad[$i];
            $isActivePlayer = false;
            $activeStat = null;
            foreach ($activeBatters as $ab) {
                if ($ab['name'] === $player) {
                    $isActivePlayer = true;
                    $activeStat = $ab;
                    break;
                }
            }

            if ($isActivePlayer) {
                $r = (int)$activeStat['runs'];
                $b = (int)$activeStat['balls'];
                $fours = intval($r * 0.12) + mt_rand(0, 1);
                $sixes = intval($r * 0.04) + mt_rand(0, 1);
                if ($fours * 4 + $sixes * 6 > $r) {
                    $fours = intval($r / 4);
                    $sixes = 0;
                }
                $sr = $b > 0 ? ($r / $b * 100) : 0.0;
                $stats[] = [
                    'name' => $player,
                    'status' => 'batting',
                    'runs' => $r,
                    'balls' => $b,
                    'fours' => $fours,
                    'sixes' => $sixes,
                    'sr' => number_format($sr, 2),
                    'dismissal' => 'batting'
                ];
            } elseif ($dismissedPtr < $dismissedCount) {
                $r = $dismissedRuns[$dismissedPtr] ?? 0;
                $b = max(1, intval($r * (mt_rand(75, 120) / 100)));
                $fours = intval($r * 0.12) + mt_rand(0, 1);
                $sixes = intval($r * 0.04) + mt_rand(0, 1);
                if ($fours * 4 + $sixes * 6 > $r) {
                    $fours = intval($r / 4);
                    $sixes = 0;
                }
                $sr = $b > 0 ? ($r / $b * 100) : 0.0;
                
                $bowler = $opponentSquad[mt_rand(6, 10)] ?? 'Pat Cummins';
                $dismissalTypes = [
                    "b $bowler",
                    "c & b $bowler",
                    "lbw b $bowler",
                    "c " . ($opponentSquad[mt_rand(0, 5)] ?? 'Klaasen') . " b $bowler",
                    "run out"
                ];
                $dismissal = $dismissalTypes[mt_rand(0, count($dismissalTypes)-1)];

                $stats[] = [
                    'name' => $player,
                    'status' => 'out',
                    'runs' => $r,
                    'balls' => $b,
                    'fours' => $fours,
                    'sixes' => $sixes,
                    'sr' => number_format($sr, 2),
                    'dismissal' => $dismissal
                ];
                $dismissedPtr++;
            } else {
                $stats[] = [
                    'name' => $player,
                    'status' => 'dnb',
                    'runs' => 0,
                    'balls' => 0,
                    'fours' => 0,
                    'sixes' => 0,
                    'sr' => '-',
                    'dismissal' => 'yet to bat'
                ];
            }
        }
        return [
            'batters' => $stats,
            'extras' => $extras
        ];
    };

    $buildBowlingScorecard = function($opponentSquad, $overSummary, $activeBowler, $isActiveInnings) {
        $bowlersStats = [];
        
        foreach ($overSummary as $o) {
            $bName = $o['bowler'] ?? null;
            if (!$bName) {
                $bName = $opponentSquad[9] ?? 'Pat Cummins';
            }
            
            if (!isset($bowlersStats[$bName])) {
                $bowlersStats[$bName] = [
                    'name' => $bName,
                    'overs' => 0,
                    'balls' => 0,
                    'maidens' => 0,
                    'runs' => 0,
                    'wickets' => 0
                ];
            }
            
            $bowlersStats[$bName]['runs'] += $o['runs'];
            
            $validBalls = 0;
            $wktCount = 0;
            if (isset($o['balls']) && is_array($o['balls'])) {
                foreach ($o['balls'] as $ball) {
                    $b = strtolower(trim((string)$ball));
                    if (strpos($b, 'wd') === false && strpos($b, 'nb') === false) {
                        $validBalls++;
                    }
                    if ($b === 'w') {
                        $wktCount++;
                    }
                }
            }
            
            $bowlersStats[$bName]['balls'] += $validBalls;
            $bowlersStats[$bName]['wickets'] += $wktCount;
            
            if ($o['runs'] === 0) {
                $bowlersStats[$bName]['maidens']++;
            }
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
                'overs' => $liveCompleted,
                'balls' => $liveTotalBalls,
                'maidens' => 0,
                'runs' => $liveRuns,
                'wickets' => $liveWickets
            ];
        }
        
        $list = [];
        foreach ($bowlersStats as $name => $stat) {
            $compOvers = intval($stat['balls'] / 6);
            $ballsRem = $stat['balls'] % 6;
            $oversStr = $compOvers . '.' . $ballsRem;
            
            $totalOversFloat = $compOvers + $ballsRem / 6.0;
            $economy = $totalOversFloat > 0 ? ($stat['runs'] / $totalOversFloat) : 0.0;
            
            $list[] = [
                'name' => $name,
                'overs' => $oversStr,
                'maidens' => $stat['maidens'],
                'runs' => $stat['runs'],
                'wickets' => $stat['wickets'],
                'er' => number_format($economy, 2)
            ];
        }
        
        if (empty($list)) {
            for ($i = 6; $i < min(11, count($opponentSquad)); $i++) {
                $list[] = [
                    'name' => $opponentSquad[$i],
                    'overs' => '0.0',
                    'maidens' => 0,
                    'runs' => 0,
                    'wickets' => 0,
                    'er' => '0.00'
                ];
            }
        }
        
        return $list;
    };

    $buildFow = function($battersList, $timeline) {
        $fowList = [];
        $wicketsTimeline = [];
        
        $chronoTimeline = array_reverse($timeline);
        foreach ($chronoTimeline as $entry) {
            if (isset($entry['text']) && (strpos(strtoupper($entry['text']), 'WICKET') !== false || strpos(strtoupper($entry['text']), 'OUT') !== false)) {
                $wicketsTimeline[] = $entry;
            }
        }
        
        $dismissedBatters = [];
        foreach ($battersList as $b) {
            if ($b['status'] === 'out') {
                $dismissedBatters[] = $b;
            }
        }
        
        $totalWickets = count($dismissedBatters);
        for ($i = 0; $i < $totalWickets; $i++) {
            $b = $dismissedBatters[$i];
            $tl = $wicketsTimeline[$i] ?? null;
            
            $overVal = $tl ? $tl['time'] : ($i + 1) . '.0';
            $scoreVal = intval(($b['runs'] + ($i > 0 ? $fowList[$i-1]['runs_at_wicket'] : 0)) * 1.15) + 3;
            
            $fowList[] = [
                'number' => $i + 1,
                'name' => $b['name'],
                'score' => $scoreVal . '-' . ($i + 1),
                'over' => $overVal,
                'runs_at_wicket' => $scoreVal
            ];
        }
        
        return $fowList;
    };

    // Calculate scorecards for both innings
    $innings1Data = $buildBattingScorecard($innings1['batting_squad'], $innings1['bowling_squad'], $innings1['runs'], $innings1['wickets'], $innings1['batters_live']);
    $innings1Batters = $innings1Data['batters'];
    $innings1Extras = $innings1Data['extras'];
    $innings1Bowlers = $buildBowlingScorecard($innings1['bowling_squad'], $innings1Overs, $innings1['bowler_live'], $innings1['is_active']);
    $innings1Fow = $buildFow($innings1Batters, $timeline);

    $innings2Data = $buildBattingScorecard($innings2['batting_squad'], $innings2['bowling_squad'], $innings2['runs'], $innings2['wickets'], $innings2['batters_live']);
    $innings2Batters = $innings2Data['batters'];
    $innings2Extras = $innings2Data['extras'];
    $innings2Bowlers = $buildBowlingScorecard($innings2['bowling_squad'], $innings2Overs, $innings2['bowler_live'], $innings2['is_active']);
    $innings2Fow = $buildFow($innings2Batters, $timeline);
@endphp

<section class="match-grid">
    <div class="match-main">
        <!-- CREX styled Innings Switcher Tabs -->
        <div class="crex-tab-box">
            <button class="crex-tab-btn {{ $battingTeam === 'home' ? 'is-active is-home' : '' }}" onclick="switchInnings('innings-1', this)">
                <div class="crex-tab-btn__team">1ST INNINGS</div>
                <div class="crex-tab-btn__score">{{ $homeTeam }} <span>{{ $innings1['runs'] }}-{{ $innings1['wickets'] }} ({{ $innings1['overs'] }} ov)</span></div>
            </button>
            <button class="crex-tab-btn {{ $battingTeam === 'away' ? 'is-active is-away' : '' }}" onclick="switchInnings('innings-2', this)">
                <div class="crex-tab-btn__team">2ND INNINGS</div>
                <div class="crex-tab-btn__score">{{ $awayTeam }} <span>{{ $innings2['runs'] }}-{{ $innings2['wickets'] }} ({{ $innings2['overs'] }} ov)</span></div>
            </button>
        </div>

        <!-- Innings 1 Card -->
        <article id="innings-1" class="match-card crex-card" style="display: {{ $battingTeam === 'home' ? 'block' : 'none' }};">
            <!-- Batting Section -->
            <div class="crex-table-heading">
                <h3>BATTING</h3>
                <span>Innings 1</span>
            </div>
            <div class="crex-table-wrapper">
                <table class="crex-table">
                    <thead>
                        <tr>
                            <th class="cell-left">Batter</th>
                            <th class="cell-right">R</th>
                            <th class="cell-right">B</th>
                            <th class="cell-right">4s</th>
                            <th class="cell-right">6s</th>
                            <th class="cell-right">SR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($innings1Batters as $b)
                            <tr class="{{ $b['status'] === 'batting' ? 'row-active' : '' }}">
                                <td class="cell-left">
                                    <div class="batter-name-box">
                                        <strong>{{ $b['name'] }}</strong>
                                        @if ($b['status'] === 'batting')
                                            <span class="pulsing-badge">Batting</span>
                                        @endif
                                    </div>
                                    <div class="dismissal-text">{{ $b['dismissal'] }}</div>
                                </td>
                                <td class="cell-right bold-highlight">{{ $b['runs'] }}</td>
                                <td class="cell-right text-muted">{{ $b['balls'] }}</td>
                                <td class="cell-right text-muted">{{ $b['fours'] }}</td>
                                <td class="cell-right text-muted">{{ $b['sixes'] }}</td>
                                <td class="cell-right text-muted">{{ $b['sr'] }}</td>
                            </tr>
                        @endforeach
                        <!-- Extras Row -->
                        <tr class="row-meta">
                            <td class="cell-left">
                                <strong>Extras</strong>
                                <span class="extras-detail">(wd {{ $innings1Extras - mt_rand(0,1) }}, nb 1, lb {{ mt_rand(0,1) }}, b 0, p 0)</span>
                            </td>
                            <td class="cell-right bold-highlight" colspan="5">{{ $innings1Extras }}</td>
                        </tr>
                        <!-- Total Row -->
                        <tr class="row-total">
                            <td class="cell-left">
                                <strong>Total</strong>
                                <span class="total-detail">({{ $innings1['overs'] }} overs @ {{ number_format($innings1['runs']/max(0.1, (float)$innings1['overs']), 2) }} RPO)</span>
                            </td>
                            <td class="cell-right total-score" colspan="5">{{ $innings1['runs'] }}-{{ $innings1['wickets'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bowling Section -->
            <div class="crex-table-heading crex-table-heading--bowling">
                <h3>BOWLING</h3>
                <span>Opposition</span>
            </div>
            <div class="crex-table-wrapper">
                <table class="crex-table">
                    <thead>
                        <tr>
                            <th class="cell-left">Bowler</th>
                            <th class="cell-right">O</th>
                            <th class="cell-right">M</th>
                            <th class="cell-right">R</th>
                            <th class="cell-right">W</th>
                            <th class="cell-right">ER</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($innings1Bowlers as $bowl)
                            @php
                                $isLiveBowler = ($innings1['bowler_live'] && $innings1['bowler_live']['name'] === $bowl['name']);
                            @endphp
                            <tr class="{{ $isLiveBowler ? 'row-active-bowler' : '' }}">
                                <td class="cell-left">
                                    <div class="batter-name-box">
                                        <strong>{{ $bowl['name'] }}</strong>
                                        @if ($isLiveBowler)
                                            <span class="pulsing-badge pulsing-badge--golden">Bowling</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="cell-right bold-highlight">{{ $bowl['overs'] }}</td>
                                <td class="cell-right text-muted">{{ $bowl['maidens'] }}</td>
                                <td class="cell-right text-muted">{{ $bowl['runs'] }}</td>
                                <td class="cell-right bold-highlight" style="color: #f43f5e;">{{ $bowl['wickets'] }}</td>
                                <td class="cell-right text-muted">{{ $bowl['er'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Fall of Wickets -->
            @if(!empty($innings1Fow))
                <div class="crex-table-heading crex-table-heading--fow">
                    <h3>FALL OF WICKETS</h3>
                </div>
                <div class="crex-fow-list">
                    @foreach ($innings1Fow as $fow)
                        <div class="crex-fow-item">
                            <span class="fow-num">{{ $fow['number'] }}</span>
                            <div class="fow-copy">
                                <strong>{{ $fow['score'] }}</strong>
                                <span>{{ $fow['name'] }} ({{ $fow['over'] }} ov)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <!-- Innings 2 Card -->
        <article id="innings-2" class="match-card crex-card" style="display: {{ $battingTeam === 'away' ? 'block' : 'none' }};">
            <!-- Batting Section -->
            <div class="crex-table-heading">
                <h3>BATTING</h3>
                <span>Innings 2</span>
            </div>
            <div class="crex-table-wrapper">
                <table class="crex-table">
                    <thead>
                        <tr>
                            <th class="cell-left">Batter</th>
                            <th class="cell-right">R</th>
                            <th class="cell-right">B</th>
                            <th class="cell-right">4s</th>
                            <th class="cell-right">6s</th>
                            <th class="cell-right">SR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($innings2Batters as $b)
                            <tr class="{{ $b['status'] === 'batting' ? 'row-active' : '' }}">
                                <td class="cell-left">
                                    <div class="batter-name-box">
                                        <strong>{{ $b['name'] }}</strong>
                                        @if ($b['status'] === 'batting')
                                            <span class="pulsing-badge">Batting</span>
                                        @endif
                                    </div>
                                    <div class="dismissal-text">{{ $b['dismissal'] }}</div>
                                </td>
                                <td class="cell-right bold-highlight">{{ $b['runs'] }}</td>
                                <td class="cell-right text-muted">{{ $b['balls'] }}</td>
                                <td class="cell-right text-muted">{{ $b['fours'] }}</td>
                                <td class="cell-right text-muted">{{ $b['sixes'] }}</td>
                                <td class="cell-right text-muted">{{ $b['sr'] }}</td>
                            </tr>
                        @endforeach
                        <!-- Extras Row -->
                        <tr class="row-meta">
                            <td class="cell-left">
                                <strong>Extras</strong>
                                <span class="extras-detail">(wd {{ $innings2Extras }}, nb 0, lb 0, b 0, p 0)</span>
                            </td>
                            <td class="cell-right bold-highlight" colspan="5">{{ $innings2Extras }}</td>
                        </tr>
                        <!-- Total Row -->
                        <tr class="row-total">
                            <td class="cell-left">
                                <strong>Total</strong>
                                <span class="total-detail">({{ $innings2['overs'] }} overs @ {{ number_format($innings2['runs']/max(0.1, (float)$innings2['overs']), 2) }} RPO)</span>
                            </td>
                            <td class="cell-right total-score" colspan="5">{{ $innings2['runs'] }}-{{ $innings2['wickets'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bowling Section -->
            <div class="crex-table-heading crex-table-heading--bowling">
                <h3>BOWLING</h3>
                <span>Opposition</span>
            </div>
            <div class="crex-table-wrapper">
                <table class="crex-table">
                    <thead>
                        <tr>
                            <th class="cell-left">Bowler</th>
                            <th class="cell-right">O</th>
                            <th class="cell-right">M</th>
                            <th class="cell-right">R</th>
                            <th class="cell-right">W</th>
                            <th class="cell-right">ER</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($innings2Bowlers as $bowl)
                            @php
                                $isLiveBowler = ($innings2['bowler_live'] && $innings2['bowler_live']['name'] === $bowl['name']);
                            @endphp
                            <tr class="{{ $isLiveBowler ? 'row-active-bowler' : '' }}">
                                <td class="cell-left">
                                    <div class="batter-name-box">
                                        <strong>{{ $bowl['name'] }}</strong>
                                        @if ($isLiveBowler)
                                            <span class="pulsing-badge pulsing-badge--golden">Bowling</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="cell-right bold-highlight">{{ $bowl['overs'] }}</td>
                                <td class="cell-right text-muted">{{ $bowl['maidens'] }}</td>
                                <td class="cell-right text-muted">{{ $bowl['runs'] }}</td>
                                <td class="cell-right bold-highlight" style="color: #f43f5e;">{{ $bowl['wickets'] }}</td>
                                <td class="cell-right text-muted">{{ $bowl['er'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Fall of Wickets -->
            @if(!empty($innings2Fow))
                <div class="crex-table-heading crex-table-heading--fow">
                    <h3>FALL OF WICKETS</h3>
                </div>
                <div class="crex-fow-list">
                    @foreach ($innings2Fow as $fow)
                        <div class="crex-fow-item">
                            <span class="fow-num">{{ $fow['number'] }}</span>
                            <div class="fow-copy">
                                <strong>{{ $fow['score'] }}</strong>
                                <span>{{ $fow['name'] }} ({{ $fow['over'] }} ov)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </div>

    <!-- Match sidebar list -->
    <aside class="match-side">
        <article class="match-card match-card--fixtures">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Other fixtures</p>
                    <h2>Match list</h2>
                </div>
            </div>
            <div class="fixture-list">
                @foreach ($matches as $otherMatch)
                    <a class="fixture-item" href="{{ route('site.gamehub.actionboard.match', ['id' => $otherMatch['id']]) }}">
                        <div>
                            <strong>{{ $otherMatch['home'] }} vs {{ $otherMatch['away'] }}</strong>
                            <span>{{ $otherMatch['status'] }} · {{ $otherMatch['venue'] }}</span>
                        </div>
                        <em>{{ $otherMatch['score_text'] ?? (($otherMatch['home_score'] ?? 0) . '-' . ($otherMatch['away_score'] ?? 0)) }}</em>
                    </a>
                @endforeach
            </div>
        </article>
    </aside>
</section>
<!-- Beautiful score card styles matching CREX layout --><style>
.crex-tab-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}
.crex-tab-btn {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 12px;
    padding: 10px 14px;
    cursor: pointer;
    text-align: left;
    transition: all 0.25s ease;
}
.crex-tab-btn:hover {
    border-color: #CBD5E1 !important;
    transform: translateY(-1px);
    background: #F8FAFC !important;
}
.crex-tab-btn.is-active.is-home {
    background: linear-gradient(135deg, #064e3b 0%, #00d68f 100%) !important;
    border-color: rgba(0, 214, 143, 0.3) !important;
    box-shadow: 0 6px 18px rgba(0, 214, 143, 0.2) !important;
    color: #fff !important;
}
.crex-tab-btn.is-active.is-away {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    box-shadow: 0 6px 18px rgba(59, 130, 246, 0.2) !important;
    color: #fff !important;
}
.crex-tab-btn__team {
    font-size: 9px;
    text-transform: uppercase;
    font-weight: 800;
    letter-spacing: 1px;
    margin-bottom: 3px;
    color: #64748B !important;
}
.crex-tab-btn.is-active .crex-tab-btn__team {
    color: #a7f3d0 !important;
}
.crex-tab-btn.is-active.is-away .crex-tab-btn__team {
    color: #93c5fd !important;
}
.crex-tab-btn__score {
    font-size: 15px;
    font-weight: 800;
    color: #0F172A !important;
}
.crex-tab-btn.is-active .crex-tab-btn__score {
    color: #ffffff !important;
}
.crex-tab-btn__score span {
    font-size: 12px;
    font-weight: 600;
    color: #64748B !important;
    margin-left: 4px;
}
.crex-tab-btn.is-active .crex-tab-btn__score span {
    color: #f1f5f9 !important;
}

.crex-card {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 16px !important;
    padding: 14px 18px 12px !important;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03) !important;
}
.crex-table-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2.5px solid #E2E8F0 !important;
    padding-bottom: 8px;
    margin-bottom: 12px;
}
.crex-table-heading h3 {
    margin: 0;
    font-size: 13.5px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #0F172A !important;
    border-left: 4px solid #00D26A !important;
    padding-left: 8px;
}
.crex-table-heading span {
    font-size: 10px;
    font-weight: 800;
    color: #00D26A !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(0, 210, 106, 0.08) !important;
    border: 1px solid rgba(0, 210, 106, 0.15) !important;
    padding: 3px 8px;
    border-radius: 20px;
}
.crex-table-heading--bowling {
    margin-top: 24px;
}
.crex-table-heading--bowling h3 {
    border-left-color: #3b82f6 !important;
}
.crex-table-heading--bowling span {
    color: #3b82f6 !important;
    background: rgba(59, 130, 246, 0.08) !important;
    border-color: rgba(59, 130, 246, 0.15) !important;
}
.crex-table-heading--fow {
    margin-top: 24px;
}
.crex-table-heading--fow h3 {
    border-left-color: #fbbf24 !important;
}

.crex-table-wrapper {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #E2E8F0 !important;
}
.crex-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.crex-table th {
    background: #F8FAFC !important;
    color: #64748B !important;
    font-weight: 800;
    padding: 8px 12px;
    border-bottom: 1px solid #E2E8F0 !important;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.5px;
}
.crex-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #F1F5F9 !important;
    color: #334155 !important;
    vertical-align: middle;
}
.crex-table tr:hover {
    background: #F8FAFC !important;
}
.crex-table tr.row-active {
    background: rgba(0, 210, 106, 0.05) !important;
}
.crex-table tr.row-active td {
    border-bottom-color: rgba(0, 210, 106, 0.1) !important;
}
.crex-table tr.row-active-bowler {
    background: rgba(59, 130, 246, 0.05) !important;
}
.crex-table tr.row-active-bowler td {
    border-bottom-color: rgba(59, 130, 246, 0.1) !important;
}
.cell-left {
    text-align: left;
}
.cell-right {
    text-align: right;
    width: 65px;
}
.bold-highlight {
    font-weight: 800;
    color: #0F172A !important;
}
.text-muted {
    color: #64748B !important;
}

.batter-name-box {
    display: flex;
    align-items: center;
    gap: 6px;
}
.batter-name-box strong {
    font-size: 13.5px;
    color: #0F172A !important;
    font-weight: 600;
}
.pulsing-badge {
    background: #00D26A !important;
    color: #ffffff !important;
    font-size: 9px;
    font-weight: 850;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    animation: pulseGlow 1.5s infinite;
}
.pulsing-badge--golden {
    background: #fbbf24 !important;
    color: #78350f !important;
    animation: pulseGoldenGlow 1.5s infinite;
}
.dismissal-text {
    font-size: 11px;
    color: #64748B !important;
    margin-top: 1px;
    font-style: italic;
}

.row-meta td {
    background: #F8FAFC !important;
}
.extras-detail {
    font-size: 11.5px;
    color: #64748B !important;
    margin-left: 8px;
    font-weight: 500;
}
.row-total td {
    background: #F8FAFC !important;
    border-top: 2.5px solid #E2E8F0 !important;
}
.row-total strong {
    font-size: 15px;
    color: #0F172A !important;
}
.total-detail {
    font-size: 12.5px;
    color: #64748B !important;
    margin-left: 8px;
}
.total-score {
    font-size: 20px;
    font-weight: 900;
    color: #00D26A !important;
    text-align: right;
}

/* Beautiful timeline style for FOW */
.crex-fow-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 14px;
    position: relative;
    padding-left: 4px;
}
.crex-fow-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 12px;
    padding: 10px 14px;
    min-width: 180px;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}
.crex-fow-item:hover {
    border-color: rgba(251, 191, 36, 0.4) !important;
    transform: translateY(-1.5px);
    background: #FDFBF7 !important;
}
.fow-num {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(251, 191, 36, 0.08) !important;
    border: 1px solid rgba(251, 191, 36, 0.2) !important;
    color: #d97706 !important;
    font-size: 11.5px;
    font-weight: 800;
    display: grid;
    place-items: center;
}
.fow-copy {
    display: flex;
    flex-direction: column;
}
.fow-copy strong {
    font-size: 13.5px;
    color: #0F172A !important;
}
.fow-copy span {
    font-size: 11.5px;
    color: #64748B !important;
    margin-top: 1px;
}

@keyframes pulseGlow {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 210, 106, 0.4); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(0, 210, 106, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 210, 106, 0); }
}
@keyframes pulseGoldenGlow {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.4); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(251, 191, 36, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
}

@media (max-width: 768px) {
    .crex-tab-box {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .crex-fow-list {
        grid-template-columns: 1fr;
    }
    .crex-card {
        padding: 14px !important;
    }
    .crex-table th, .crex-table td {
        padding: 8px 6px;
    }
    .batter-name-box strong {
        font-size: 12.5px;
    }
    .dismissal-text {
        font-size: 10.5px;
    }
    .crex-table td.cell-right {
        width: 50px;
        font-size: 11.5px;
    }
}
</style>

<!-- JS Tab Switching Mechanism -->
<script>
function switchInnings(inningsId, tabButton) {
    // Hide all innings cards
    document.getElementById('innings-1').style.display = 'none';
    document.getElementById('innings-2').style.display = 'none';
    
    // Show active card
    document.getElementById(inningsId).style.display = 'block';
    
    // Reset active button class states
    const btns = document.querySelectorAll('.crex-tab-btn');
    btns.forEach(btn => {
        btn.classList.remove('is-active');
    });
    
    // Set active button
    tabButton.classList.add('is-active');
}
</script>
@endsection
