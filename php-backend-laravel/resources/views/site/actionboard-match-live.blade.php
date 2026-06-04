@extends('site.actionboard-match-layout')
@section('match_content')
@php
    $homeTeam = $match['home'];
    $awayTeam = $match['away'];
    $batters = $match['batters'] ?? [];
    $bowler = $match['bowler'] ?? [];
    $partnership = $match['partnership'] ?? '-';
    $lastWicket = $match['lastWicket'] ?? $match['last_wicket'] ?? '-';
    $overSummary = $match['overSummary'] ?? $match['over_summary'] ?? [];
    $decision = $match['decision'] ?? '';
    $probability = $match['probability'] ?? ['home' => 50, 'away' => 50];
    $projectedScore = $match['projectedScore'] ?? $match['projected_score'] ?? ['range' => 'TBD', 'label' => 'as per RR'];
    $crr = $match['crr'] ?? $match['runRate'] ?? $match['run_rate'] ?? '-';
    $timeline = $match['timeline'] ?? [];

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

    // ── 1. Calculate Momentum Data (Last 10 completed overs chronological) ──
    $momentumOvers = array_reverse(array_slice($overSummary, 0, 10));
    $momentumData = [];
    foreach ($momentumOvers as $mo) {
        $moRuns = 0;
        $moWickets = 0;
        $moBatting = $mo['batting'] ?? 'home';
        if (isset($mo['balls']) && is_array($mo['balls'])) {
            foreach ($mo['balls'] as $ball) {
                $bVal = trim((string)$ball);
                if (strtoupper($bVal) === 'W') {
                    $moWickets++;
                } elseif (is_numeric($bVal)) {
                    $moRuns += (int)$bVal;
                } elseif (strpos(strtoupper($bVal), 'WD') !== false || strpos(strtoupper($bVal), 'NB') !== false) {
                    $moRuns += 1;
                }
            }
        }
        
        // Base momentum formula: runs - (wickets * 8)
        $baseScore = $moRuns - ($moWickets * 8);
        $score = ($moBatting === 'away') ? -$baseScore : $baseScore;
        
        $momentumData[] = [
            'over' => $mo['over'],
            'score' => $score,
            'runs' => $moRuns,
            'wickets' => $moWickets,
            'batting' => $moBatting
        ];
    }

    // ── 2. Parse Run Rate Data Chronologically for cumulative Graph ──
    $inn1Runs = [];
    $inn2Runs = [];
    $cHome = 0;
    $cAway = 0;
    
    // Sort overSummary chronologically
    $chronoSummary = array_reverse($overSummary);
    foreach ($chronoSummary as $o) {
        $oBatting = $o['batting'] ?? 'home';
        $oRuns = 0;
        if (isset($o['balls']) && is_array($o['balls'])) {
            foreach ($o['balls'] as $ball) {
                $bVal = trim((string)$ball);
                if (is_numeric($bVal)) {
                    $oRuns += (int)$bVal;
                } elseif (strpos(strtoupper($bVal), 'WD') !== false || strpos(strtoupper($bVal), 'NB') !== false) {
                    $oRuns += 1;
                }
            }
        }
        
        if ($oBatting === 'home') {
            $cHome += $oRuns;
            $inn1Runs[$o['over']] = $cHome;
        } else {
            $cAway += $oRuns;
            $inn2Runs[$o['over']] = $cAway;
        }
    }
    
    $maxRunsVal = max(80, $cHome, $cAway);
    $totalPlannedOvers = 20;
    if (isset($match['competition']) && preg_match('/(\d+)/', $match['competition'], $matchesComp)) {
        $totalPlannedOvers = (int)$matchesComp[1];
    }
    
    // Construct cumulative coordinates for SVG pathing
    $points1 = "20,90";
    $lastX1 = 20;
    foreach ($inn1Runs as $ov => $runs) {
        $x = 20 + ($ov / $totalPlannedOvers) * 350;
        $y = 90 - ($runs / $maxRunsVal) * 80;
        $points1 .= " " . $x . "," . $y;
        $lastX1 = $x;
    }
    $areaPoints1 = "20,90 " . (strlen($points1) > 5 ? substr($points1, 6) : "") . " " . $lastX1 . ",90";
    
    $points2 = "";
    $areaPoints2 = "";
    if (!empty($inn2Runs)) {
        $points2 = "20,90";
        $lastX2 = 20;
        foreach ($inn2Runs as $ov => $runs) {
            $x = 20 + ($ov / $totalPlannedOvers) * 350;
            $y = 90 - ($runs / $maxRunsVal) * 80;
            $points2 .= " " . $x . "," . $y;
            $lastX2 = $x;
        }
        $areaPoints2 = "20,90 " . (strlen($points2) > 5 ? substr($points2, 6) : "") . " " . $lastX2 . ",90";
    }

    // Determine active batting team colors
    $activeBattingTeam = 'home';
    if (!empty($overSummary)) {
        $lastOver = end($overSummary);
        $activeBattingTeam = $lastOver['batting'] ?? 'home';
    }
@endphp
@php
    $getInitials = function($name) {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 3));
    };
    $homeAbbr = $getInitials($homeTeam);
    $awayAbbr = $getInitials($awayTeam);

    $batter1 = $batters[0] ?? null;
    $batter2 = $batters[1] ?? null;
    $resolvedBatter1 = $batter1 ? $resolvePlayerName($batter1['name']) : null;
    $resolvedBatter2 = $batter2 ? $resolvePlayerName($batter2['name']) : null;
    $resolvedBowler = ($bowler && isset($bowler['name'])) ? $resolvePlayerName($bowler['name']) : null;
    $getAvatarInitials = function($name) {
        $parts = preg_split('/\s+/', trim((string)$name));
        $first = $parts[0] ?? '';
        $second = $parts[1] ?? '';
        $initials = strtoupper(substr($first, 0, 1) . substr($second, 0, 1));
        return $initials !== '' ? $initials : strtoupper(substr((string)$name, 0, 2));
    };
@endphp

<!-- CREX-style Live Dashboard Header Card -->
<article class="match-card match-card--dashboard">
    <div class="db-topline">
        <div class="db-topline__left">
            <span class="db-topline__label">Match Info</span>
            <span class="db-topline__value">{{ $match['competition'] ?? 'Live Match Center' }}</span>
        </div>
        <div class="db-topline__right">
            <span class="db-topline__label">Toss</span>
            <span class="db-topline__value">{{ $decision ?: 'Awaiting update' }}</span>
        </div>
    </div>

    <div class="db-left-section">
        <!-- Players Info Row -->
        <div class="db-players-row">
            @if($batter1)
                <div class="db-player-card">
                    <div class="db-player-avatar">
                        <span class="db-player-avatar__text">{{ $getAvatarInitials($resolvedBatter1) }}</span>
                    </div>
                    <div class="db-player-info">
                        <span class="db-player-name">
                            {{ $resolvedBatter1 }}
                            <span class="striker-bat-icon">🏏</span>
                        </span>
                        <span class="db-player-runs">
                            {{ $batter1['runs'] }}
                            <small>({{ $batter1['balls'] }})</small>
                        </span>
                    </div>
                </div>
            @endif

            @if($batter1 && $batter2)
                <div class="db-partnership-plus">+</div>
            @endif

            @if($batter2)
                <div class="db-player-card">
                    <div class="db-player-avatar">
                        <span class="db-player-avatar__text">{{ $getAvatarInitials($resolvedBatter2) }}</span>
                    </div>
                    <div class="db-player-info">
                        <span class="db-player-name">{{ $resolvedBatter2 }}</span>
                        <span class="db-player-runs">
                            {{ $batter2['runs'] }}
                            <small>({{ $batter2['balls'] }})</small>
                        </span>
                    </div>
                </div>
            @endif

            @if(!empty($bowler) && isset($bowler['name']))
                <div class="db-vertical-divider"></div>

                <div class="db-player-card bowler">
                    <div class="db-player-avatar">
                        <span class="db-player-avatar__text">{{ $getAvatarInitials($resolvedBowler) }}</span>
                    </div>
                    <div class="db-player-info">
                        <span class="db-player-name">
                            <span class="bowler-ball-icon">🥎</span>
                            {{ $resolvedBowler }}
                        </span>
                        <span class="db-player-runs">
                            {{ $bowler['figures'] ?? '0-0' }}
                            <small>({{ $bowler['overs'] ?? '0.0' }} ov)</small>
                        </span>
                    </div>
                </div>
            @endif

            @if(!$batter1 && !$batter2 && empty($bowler))
                <div style="padding: 12px; text-align: center; color: #64748B; font-weight: 600; width: 100%;">
                    Match center is preparing... Live stats will appear once play resumes.
                </div>
            @endif
        </div>

        <!-- Partnership / Last Wicket metadata strip -->
        <div class="db-meta-strip">
            <span class="db-meta-item">P'ship : <strong>{{ $partnership }}</strong></span>
            <span class="db-meta-item" style="margin-left: 16px;">Last Wkt : <strong>{{ $lastWicket }}</strong></span>
        </div>

        <div class="db-horizontal-divider"></div>

        <!-- Recent Overs Summary Row -->
        <div class="db-overs-row">
            @if(!empty($overSummary))
                @foreach (array_slice($overSummary, 0, 2) as $summary)
                    <div class="db-over-item">
                        <span class="db-over-num">Over {{ $summary['over'] }}</span>
                        <div class="db-over-balls">
                            @php
                                $overRuns = 0;
                            @endphp
                            @foreach ($summary['balls'] as $ball)
                                @php
                                    $ballStr = trim((string)$ball);
                                    $ballLower = strtolower($ballStr);
                                    $isWicket = ($ballLower === 'w');
                                    $isFour = ($ballStr === '4');
                                    $isSix = ($ballStr === '6');
                                    $isExtra = (strpos($ballLower, 'wd') !== false || strpos($ballLower, 'nb') !== false);
                                    
                                    if (is_numeric($ballStr)) {
                                        $overRuns += (int)$ballStr;
                                    } elseif ($isExtra) {
                                        $overRuns += 1;
                                    }

                                    $bClass = 'db-ball-txt';
                                    if ($isWicket) $bClass = 'db-ball-pill db-ball-pill--wicket';
                                    elseif ($isFour) $bClass = 'db-ball-pill db-ball-pill--four';
                                    elseif ($isSix) $bClass = 'db-ball-pill db-ball-pill--six';
                                    elseif ($isExtra) $bClass = 'db-ball-pill db-ball-pill--extra';
                                @endphp
                                <span class="{{ $bClass }}">{{ $ballStr }}</span>
                            @endforeach
                            <span class="db-over-runs-sum">- {{ $overRuns }}</span>
                        </div>
                    </div>
                @endforeach
            @else
                <span class="db-no-overs">Over starting...</span>
            @endif
        </div>
    </div>

    <div class="db-vertical-section-divider"></div>

    <div class="db-right-section">
        <!-- Probability -->
        <div class="db-prob-section">
            <div class="db-section-header">
                <span class="db-section-title">Probability</span>
                <div class="db-toggle-pill">
                    <span class="toggle-item active">% View</span>
                    <span class="toggle-item">Number View</span>
                </div>
            </div>
            <div class="db-prob-bar-wrapper">
                <div class="db-prob-label">{{ $homeAbbr }} {{ $probability['home'] ?? 50 }}%</div>
                <div class="db-prob-bar">
                    <div class="db-prob-fill" style="width: {{ $probability['home'] ?? 50 }}%"></div>
                </div>
                <div class="db-prob-label text-right">{{ $awayAbbr }} {{ $probability['away'] ?? 50 }}%</div>
            </div>
        </div>

        <!-- Projected Score -->
        <div class="db-proj-section">
            <div class="db-section-header">
                <span class="db-section-title">Projected Score <small>as per RR</small></span>
            </div>
            <div class="db-proj-table-wrapper">
                <table class="db-proj-table">
                    <thead>
                        <tr>
                            <th>Run Rate</th>
                            <th class="current-rr">{{ $crr }}*</th>
                            <th>9.00</th>
                            <th>10.00</th>
                            <th>11.00</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @php
                                $totalPlannedOvers = $totalPlannedOvers ?? 20;
                                $crrFloat = floatval($crr) ?: 6.0;
                                $projCurr = intval($crrFloat * $totalPlannedOvers);
                                if (isset($projectedScore['range']) && $projectedScore['range'] !== 'TBD') {
                                    $projCurr = $projectedScore['range'];
                                }
                            @endphp
                            <td>{{ $totalPlannedOvers }} Overs</td>
                            <td class="current-rr">{{ $projCurr }}</td>
                            <td>{{ intval(9.00 * $totalPlannedOvers) }}</td>
                            <td>{{ intval(10.00 * $totalPlannedOvers) }}</td>
                            <td>{{ intval(11.00 * $totalPlannedOvers) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</article>

<section class="match-grid">
    <div class="match-main">

        <!-- Live Commentary timeline -->
        <article class="match-card match-card--commentary">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Commentary</p>
                    <h2>Ball-by-ball updates</h2>
                </div>
            </div>
            
            <div class="crex-commentary-timeline">
                @if (!empty($timeline))
                    @foreach ($timeline as $index => $event)
                        @php
                            $eText = strtoupper($event['text'] ?? '');
                            $code = '';
                            $klass = '';
                            
                            $isWicket = false;
                            $isSix = false;
                            $isFour = false;
                            $isExtra = false;
                            $isDot = false;
                            
                            if (strpos($eText, 'FOUR') !== false || $eText === '4') {
                                $code = '4';
                                $klass = 'ball-pill-crex--four';
                                $isFour = true;
                            } elseif (strpos($eText, 'SIX') !== false || $eText === '6') {
                                $code = '6';
                                $klass = 'ball-pill-crex--six';
                                $isSix = true;
                            } elseif (strpos($eText, 'WICKET') !== false || strpos($eText, 'OUT') !== false || $eText === 'W') {
                                $code = 'W';
                                $klass = 'ball-pill-crex--wicket';
                                $isWicket = true;
                            } elseif (strpos($eText, 'WD') !== false || strpos($eText, 'WIDE') !== false) {
                                $code = 'WD';
                                $klass = 'ball-pill-crex--extra';
                                $isExtra = true;
                            } elseif (strpos($eText, 'NB') !== false || strpos($eText, 'NO BALL') !== false) {
                                $code = 'NB';
                                $klass = 'ball-pill-crex--extra';
                                $isExtra = true;
                            } elseif (strpos($eText, 'DOT') !== false || $eText === '0' || strpos($eText, 'DOT BALL') !== false) {
                                $code = '0';
                                $klass = 'ball-pill-crex--dot';
                                $isDot = true;
                            } elseif (preg_match('/(\d+)\s*RUN/', $eText, $mRuns)) {
                                $code = $mRuns[1];
                                $klass = 'ball-pill-crex--run';
                            } else {
                                $code = substr($event['text'] ?? '', 0, 3);
                                $klass = 'ball-pill-crex--run';
                            }

                            // Define CSS alert wrappers
                            $commItemClass = 'crex-commentary-item';
                            if ($isWicket) {
                                $commItemClass .= ' comm-alert-wicket';
                            } elseif ($isSix) {
                                $commItemClass .= ' comm-alert-six';
                            } elseif ($isFour) {
                                $commItemClass .= ' comm-alert-four';
                            }
                        @endphp
                        <div class="{{ $commItemClass }}">
                            <div class="comm-over-indicator">
                                {{ $event['time'] }}
                            </div>
                            
                            <span class="ball-pill-crex {{ $klass }}">
                                {{ $code }}
                            </span>
                            
                            <div class="comm-body">
                                <span class="comm-tag {{ $isWicket ? 'comm-tag--wicket' : ($isSix ? 'comm-tag--six' : ($isFour ? 'comm-tag--four' : '')) }}">
                                    {{ $event['tag'] ?? ($isWicket ? 'WICKET' : ($isSix ? 'SIX' : ($isFour ? 'FOUR' : 'Update'))) }}
                                </span>
                                <p class="comm-text">
                                    {{ $event['text'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="comm-empty-card">
                        No commentary events logged yet. Keep updating the control room to see the live timeline here!
                    </div>
                @endif
            </div>
        </article>
    </div>

    <!-- Sidebar widgets -->
    <aside class="match-side">
        <!-- Wagon Wheel Stats Block -->
        <article class="match-card match-card--stats">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Analysis</p>
                    <h2>Wagon Wheel</h2>
                </div>
            </div>
            <div class="wagon-wheel-container">
                <!-- Stadium Outfield SVG Layout -->
                <svg viewBox="0 0 250 250" class="wagon-wheel-svg">
                    <defs>
                        <!-- Turf green radial gradient -->
                        <radialGradient id="turf-gradient" cx="50%" cy="50%" r="50%">
                            <stop offset="0%" stop-color="#1b5e20" />
                            <stop offset="100%" stop-color="#123e17" />
                        </radialGradient>
                    </defs>
                    <!-- Circular outfield boundary (fills the box and removes dead spaces) -->
                    <circle cx="125" cy="125" r="118" class="outfield-boundary" />
                    <!-- Inner Circle (30-yard ring) -->
                    <circle cx="125" cy="125" r="72" class="outfield-inner" />
                    <!-- Cricket Pitch Turf block -->
                    <rect x="121" y="105" width="8" height="40" class="stadium-pitch" />
                    <!-- Crease markings -->
                    <line x1="121" y1="110" x2="129" y2="110" class="pitch-crease" />
                    <line x1="121" y1="140" x2="129" y2="140" class="pitch-crease" />
                    <line x1="123" y1="108" x2="127" y2="108" class="pitch-stumps" />
                    <line x1="123" y1="142" x2="127" y2="142" class="pitch-stumps" />
                    
                    <!-- Shot vectors radiating from pitch center (125, 125) -->
                    <!-- Six to long-on (purple) -->
                    <line x1="125" y1="125" x2="195" y2="65" class="shot-vector shot-vector--six" />
                    <!-- Four to deep cover (blue) -->
                    <line x1="125" y1="125" x2="50" y2="80" class="shot-vector shot-vector--four" />
                    <!-- Single to third man (gray) -->
                    <line x1="125" y1="125" x2="45" y2="160" class="shot-vector shot-vector--single" />
                    <!-- Two to deep mid-wicket (green) -->
                    <line x1="125" y1="125" x2="215" y2="155" class="shot-vector shot-vector--run" />
                    <!-- Four to fine leg (blue) -->
                    <line x1="125" y1="125" x2="80" y2="210" class="shot-vector shot-vector--four" />
                    <!-- Dot block back to bowler -->
                    <line x1="125" y1="125" x2="125" y2="85" class="shot-vector shot-vector--dot" />
                    
                    <!-- Labels representing directions -->
                    <text x="125" y="24" class="direction-label">OFF SIDE</text>
                    <text x="125" y="236" class="direction-label">LEG SIDE</text>
                </svg>
                <div class="wagon-wheel-legend">
                    <span class="legend-item"><span class="legend-dot legend-dot--six"></span> 6s</span>
                    <span class="legend-item"><span class="legend-dot legend-dot--four"></span> 4s</span>
                    <span class="legend-item"><span class="legend-dot legend-dot--run"></span> Runs</span>
                </div>
            </div>
        </article>

        <!-- Cumulative Run Rate Graph -->
        <article class="match-card match-card--stats">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Match Flow</p>
                    <h2>Run Rate Graph</h2>
                </div>
            </div>
            <div class="run-rate-graph-container">
                <svg viewBox="0 0 400 110" class="run-rate-svg">
                    <defs>
                        <!-- Translucent gradient fills under cumulative run rate lines -->
                        <linearGradient id="area-grad-home" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#00D26A" stop-opacity="0.15" />
                            <stop offset="100%" stop-color="#00D26A" stop-opacity="0.0" />
                        </linearGradient>
                        <linearGradient id="area-grad-away" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.15" />
                            <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>

                    <!-- Grid background lines -->
                    <line x1="20" y1="10" x2="370" y2="10" stroke="rgba(15, 23, 42, 0.08)" stroke-width="0.75" />
                    <line x1="20" y1="50" x2="370" y2="50" stroke="rgba(15, 23, 42, 0.08)" stroke-width="0.75" />
                    <line x1="20" y1="90" x2="370" y2="90" stroke="rgba(15, 23, 42, 0.08)" stroke-width="0.75" />
                    
                    <!-- Graph curves & area fills -->
                    @if($points1 !== "20,90")
                        <polygon points="{{ $areaPoints1 }}" fill="url(#area-grad-home)" />
                        <polyline points="{{ $points1 }}" class="graph-path graph-path--home" />
                    @endif
                    @if($points2 !== "")
                        <polygon points="{{ $areaPoints2 }}" fill="url(#area-grad-away)" />
                        <polyline points="{{ $points2 }}" class="graph-path graph-path--away" />
                    @endif
                    
                    <!-- Axis lines -->
                    <line x1="20" y1="10" x2="20" y2="90" stroke="rgba(15, 23, 42, 0.15)" stroke-width="1" />
                    <line x1="20" y1="90" x2="370" y2="90" stroke="rgba(15, 23, 42, 0.15)" stroke-width="1" />
                    
                    <!-- Labels -->
                    <text x="15" y="94" class="axis-label" text-anchor="end">0</text>
                    <text x="15" y="54" class="axis-label" text-anchor="end">{{ (int)($maxRunsVal / 2) }}</text>
                    <text x="15" y="14" class="axis-label" text-anchor="end">{{ $maxRunsVal }}</text>
                    <text x="370" y="102" class="axis-label" text-anchor="end">{{ $totalPlannedOvers }} Ov</text>
                </svg>
                <div class="graph-legend">
                    <span class="legend-item"><span class="legend-line legend-line--home"></span> {{ $homeTeam }}</span>
                    @if(!empty($inn2Runs))
                        <span class="legend-item"><span class="legend-line legend-line--away"></span> {{ $awayTeam }}</span>
                    @endif
                </div>
            </div>
        </article>


        <!-- Expert Insights Card -->
        <article class="match-card match-card--stats">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Expert Analysis</p>
                    <h2>Match Insights</h2>
                </div>
            </div>
            <ul class="insights-list">
                <li class="insight-item">
                    <span class="insight-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </span>
                    <span class="insight-desc">Pitch favors spinners in second innings. Average par score is around 145 on this deck.</span>
                </li>
                <li class="insight-item">
                    <span class="insight-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </span>
                    <span class="insight-desc">{{ $homeTeam }} has won 70% of matches when batting first in the county cup.</span>
                </li>
            </ul>
        </article>

        <!-- Game Shift Momentum Chart -->
        <article class="match-card match-card--stats">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Game Shift</p>
                    <h2>Momentum</h2>
                </div>
                <div class="momentum-legend-box">
                    <span class="legend-badge legend-badge--home">{{ substr($homeTeam, 0, 3) }}</span>
                    <span class="legend-badge legend-badge--away">{{ substr($awayTeam, 0, 3) }}</span>
                </div>
            </div>
            
            <div class="momentum-container">
                <!-- Zero baseline separator -->
                <div class="momentum-baseline"></div>
                
                <div class="momentum-bars-grid">
                    @if(empty($momentumData))
                        <div class="momentum-empty">No completed overs yet to calculate momentum.</div>
                    @else
                        @foreach($momentumData as $md)
                            @php
                                $absScore = min(15, abs($md['score']));
                                $heightPercent = max(10, ($absScore / 15) * 45); 
                                $isHomeAdvantage = $md['score'] >= 0;
                                $tooltip = "Ov " . $md['over'] . ": " . $md['runs'] . " runs" . ($md['wickets'] > 0 ? ", " . $md['wickets'] . " W" : "");
                            @endphp
                            <div class="momentum-bar-wrapper {{ $isHomeAdvantage ? 'momentum-bar-wrapper--up' : 'momentum-bar-wrapper--down' }}" title="{{ $tooltip }}">
                                <div class="momentum-bar" style="height: {{ $heightPercent }}%; background: {{ $isHomeAdvantage ? 'linear-gradient(to top, #10b981, #34d399)' : 'linear-gradient(to bottom, #3b82f6, #60a5fa)' }}; box-shadow: {{ $isHomeAdvantage ? '0 0 8px rgba(16, 185, 129, 0.4)' : '0 0 8px rgba(59, 130, 246, 0.4)' }};"></div>
                                <span class="momentum-over-lbl">O{{ $md['over'] }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </article>
    </aside>
</section>

<style>
/* Tighter grids & Glassmorphism card styles */
.match-grid {
    display: grid;
    grid-template-columns: 1fr minmax(0, 300px); /* Slightly narrower sidebar */
    gap: 12px; /* Tighter margins */
    align-items: start;
}

/* Consolidated Live Dashboard Header Card Styles */
.match-card--dashboard {
    display: grid;
    grid-template-columns: 1.25fr 1px 0.95fr;
    gap: 20px;
    background: #FFFFFF !important;
    border: 1px solid #cdeedd !important;
    border-radius: 18px !important;
    padding: 14px 20px 16px !important;
    box-shadow: 0 8px 24px rgba(9, 55, 39, 0.06) !important;
    margin-bottom: 12px;
}

.db-topline {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding-bottom: 10px;
    margin-bottom: 12px;
    border-bottom: 1px solid #e8f3ec;
}

.db-topline__left,
.db-topline__right {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.db-topline__right {
    justify-content: flex-end;
}

.db-topline__label {
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: #64748b;
}

.db-topline__value {
    font-size: 12px;
    font-weight: 800;
    color: #093727;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.db-left-section {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;
}
.db-players-row {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}
.db-player-card {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 12px;
}
.db-player-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #F1F5F9;
    border: 2px solid #E2E8F0;
    overflow: hidden;
    flex-shrink: 0;
    display: grid;
    place-items: center;
}
.db-player-avatar__text {
    font-size: 13px;
    font-weight: 900;
    color: #0F172A;
    letter-spacing: 0.6px;
}
.db-player-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.db-player-name {
    font-size: 14px;
    font-weight: 800;
    color: #0F172A;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 4px;
}
.striker-bat-icon {
    font-size: 12px;
    color: #059669;
}
.bowler-ball-icon {
    font-size: 12px;
    color: #3b82f6;
}
.db-player-runs {
    font-size: 16px;
    font-weight: 900;
    color: #059669;
}
.db-player-card.bowler .db-player-runs {
    color: #3b82f6;
}
.db-player-runs small {
    font-size: 11.5px;
    font-weight: 500;
    color: #64748B;
}
.db-partnership-plus {
    font-size: 18px;
    font-weight: 800;
    color: #94a3b8;
    padding: 0 4px;
}
.db-vertical-divider {
    width: 1px;
    height: 42px;
    background: #E2E8F0;
}
.db-meta-strip {
    display: flex;
    gap: 16px;
    font-size: 11.5px;
    color: #64748B;
    margin-bottom: 10px;
    padding-top: 2px;
}
.db-meta-item strong {
    color: #0F172A;
    font-weight: 700;
}
.db-horizontal-divider {
    height: 1px;
    background: #F1F5F9;
    margin: 8px 0;
}
.db-overs-row {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding: 6px 0 0;
}
.db-over-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-right: 12px;
    border-right: 1px solid #eef2f7;
}
.db-over-item:last-child {
    border-right: none;
    padding-right: 0;
}
.db-over-num {
    font-size: 11px;
    font-weight: 900;
    color: #64748B;
    text-transform: uppercase;
}
.db-over-balls {
    display: flex;
    align-items: center;
    gap: 4px;
}
.db-ball-txt {
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
    margin: 0 4px;
}
.db-ball-pill {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: inline-grid;
    place-items: center;
    font-size: 10px;
    font-weight: 950;
    margin: 0 3px;
}
.db-ball-pill--wicket {
    background: #ff4d6d;
    color: #ffffff;
}
.db-ball-pill--four {
    background: #3b82f6;
    color: #ffffff;
}
.db-ball-pill--six {
    background: #a855f7;
    color: #ffffff;
}
.db-ball-pill--extra {
    background: #fbbf24;
    color: #78350f;
}
.db-over-runs-sum {
    font-size: 11.5px;
    font-weight: 800;
    color: #64748B;
}

.db-vertical-section-divider {
    width: 1px;
    background: #E2E8F0;
    align-self: stretch;
}

.db-right-section {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-width: 0;
}
.db-prob-section, .db-proj-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 12px;
}
.db-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.db-section-title {
    font-size: 11.5px;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.db-section-title small {
    font-size: 10px;
    font-weight: 500;
    color: #94a3b8;
    text-transform: lowercase;
}
.db-toggle-pill {
    display: flex;
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    padding: 2px;
}
.toggle-item {
    font-size: 9px;
    font-weight: 700;
    color: #64748B;
    padding: 2px 6px;
    border-radius: 4px;
    cursor: pointer;
}
.toggle-item.active {
    background: #FFFFFF;
    color: #0F172A;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.db-prob-bar-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}
.db-prob-label {
    font-size: 12px;
    font-weight: 800;
    color: #0F172A;
    width: 60px;
    flex-shrink: 0;
}
.db-prob-bar {
    flex: 1;
    height: 10px;
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}
.db-prob-fill {
    height: 100%;
    background: linear-gradient(90deg, #d946ef 0%, #8b5cf6 100%);
    border-radius: 4px;
}
.db-proj-table-wrapper {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    overflow: hidden;
}
.db-proj-table {
    width: 100%;
    border-collapse: collapse;
}
.db-proj-table th {
    background: #F1F5F9;
    font-size: 10px;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
    padding: 4px 8px;
    border-bottom: 1px solid #E2E8F0;
    text-align: center;
}
.db-proj-table th:first-child, .db-proj-table td:first-child {
    text-align: left;
}
.db-proj-table td {
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    padding: 6px 8px;
    text-align: center;
}
.db-proj-table .current-rr {
    background: rgba(0, 210, 106, 0.04);
    color: #00D26A;
}

@media (max-width: 768px) {
    .match-card--dashboard {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 12px !important;
    }
    .db-topline {
        flex-direction: column;
        align-items: flex-start;
    }
    .db-topline__right {
        justify-content: flex-start;
    }
    .db-vertical-section-divider {
        display: none;
    }
    .db-vertical-divider {
        display: none;
    }
    .db-players-row {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    .db-partnership-plus {
        text-align: center;
        margin: -4px 0;
    }
    .db-player-card.bowler {
        border-top: 1px solid #F1F5F9;
        padding-top: 8px;
    }
    .db-prob-section, .db-proj-section {
        padding: 10px;
    }
}
.match-main, .match-side {
    display: grid;
    gap: 12px; /* Tighter margins */
    min-width: 0;
}
.match-card {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 16px !important;
    padding: 14px 18px 12px !important; /* Reduced vertical margins */
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03) !important;
    position: relative;
    overflow: hidden;
    min-width: 0;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.match-card:hover {
    transform: translateY(-1.5px) !important;
    border-color: #CBD5E1 !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03) !important;
}
.card-accent-strip {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}
.match-card__heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.match-card h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #0F172A !important;
    letter-spacing: -0.2px;
}
.match-kicker {
    margin: 0 0 2px;
    font-size: 9px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #00D26A !important;
    font-weight: 800;
}

/* Compressed live stats table */
.crex-live-dashboard {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.crex-live-section {
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 10px;
    padding: 6px 10px;
}
.crex-live-table {
    width: 100%;
    border-collapse: collapse;
}
.crex-live-table th {
    color: #64748B !important;
    font-weight: 700;
    padding: 4px 6px;
    border-bottom: 1px solid #E2E8F0 !important;
    font-size: 9.5px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    text-align: right;
}
.crex-live-table th.cell-left {
    text-align: left;
}
.crex-live-table td {
    padding: 6px 6px;
    border-bottom: 1px solid #F1F5F9 !important;
    text-align: right;
    color: #334155 !important;
    font-weight: 500;
    font-size: 13px;
}
.crex-live-table td.cell-left {
    text-align: left;
}
.crex-live-table tr:last-child td {
    border-bottom: none;
}
.crex-live-player-name {
    display: flex;
    align-items: center;
    gap: 6px;
}
.crex-live-player-name strong {
    font-size: 13px;
    color: #0F172A !important;
    font-weight: 600;
}
.active-dot {
    width: 5px;
    height: 5px;
    background-color: #00D26A;
    border-radius: 50%;
    display: none;
    box-shadow: 0 0 6px #00D26A;
}
.active-dot--visible {
    display: inline-block;
    animation: activeDotBlink 1.2s infinite alternate;
}
.active-dot--blue {
    background-color: #3b82f6;
    box-shadow: 0 0 6px #3b82f6;
}
@keyframes activeDotBlink {
    0% { opacity: 0.4; }
    100% { opacity: 1; }
}

.live-striker-row {
    background: rgba(0, 210, 106, 0.05) !important;
}
.live-striker-row strong {
    color: #00D26A !important;
}
.live-bowler-row {
    background: rgba(59, 130, 246, 0.05) !important;
}
.live-bowler-row strong {
    color: #3b82f6 !important;
}
.bold-runs {
    font-weight: 800 !important;
    color: #0F172A !important;
}
.text-blue {
    color: #3b82f6 !important;
}
.crex-empty-bowler {
    padding: 12px;
    text-align: center;
    color: #64748B !important;
    font-size: 12px;
    font-weight: 500;
}

/* Partnership / Last Wicket meta strip */
.match-meta-strip {
    display: flex;
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 10px;
    padding: 6px 12px;
    justify-content: space-around;
    align-items: center;
}
.match-meta-strip > div {
    display: flex;
    flex-direction: column;
    text-align: center;
}
.meta-label {
    font-size: 9px;
    text-transform: uppercase;
    color: #64748B !important;
    font-weight: 700;
    margin-bottom: 1px;
}
.meta-value {
    font-size: 13px;
    color: #0F172A !important;
    font-weight: 800;
}
.meta-divider {
    width: 1px;
    height: 20px;
    background: #E2E8F0 !important;
}

/* Over summary styling */
.over-summary-block {
    margin-top: 10px;
    border-top: 1px dashed #E2E8F0 !important;
    padding-top: 10px;
}
.over-summary-title {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748B !important;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
}
.over-row-crex {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.over-chip-crex {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 8px;
    padding: 5px 8px;
}
.over-chip-crex__head {
    width: 40px;
    font-weight: 800;
    color: #00D26A !important;
    font-size: 12px;
    flex-shrink: 0;
}
.over-chip-crex__balls {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

/* TV graphic ball summary chips styling */
.ball-pill-crex {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 10px;
    font-weight: 900;
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    cursor: default;
    user-select: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.ball-pill-crex:hover {
    transform: scale(1.15);
}
.ball-pill-crex--dot {
    background: #F1F5F9 !important;
    border: 1px solid #E2E8F0 !important;
    color: #64748B !important;
}
.ball-pill-crex--run {
    background: #00D26A !important;
    border: 1px solid #00D26A !important;
    color: #FFFFFF !important;
}
.ball-pill-crex--four {
    background: #3b82f6;
    border: 1px solid #3b82f6;
    color: #ffffff;
    box-shadow: 0 0 6px rgba(59, 130, 246, 0.2);
}
.ball-pill-crex--six {
    background: #a855f7;
    border: 1px solid #a855f7;
    color: #ffffff;
    box-shadow: 0 0 6px rgba(168, 85, 247, 0.2);
}
.ball-pill-crex--wicket {
    background: #ff4d6d;
    border: 1px solid #ff4d6d;
    color: #ffffff;
    box-shadow: 0 0 8px rgba(255, 77, 109, 0.3);
    animation: ballWicketPulse 1.5s infinite alternate;
}
.ball-pill-crex--extra {
    background: #fbbf24;
    border: 1px solid #fbbf24;
    color: #78350f;
}
@keyframes ballWicketPulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.08); }
}

/* Commentary list timeline guide */
.crex-commentary-timeline {
    position: relative;
    margin-top: 6px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 380px;
    overflow-y: auto;
    padding-left: 20px;
    padding-right: 4px;
    scrollbar-width: thin;
}
.crex-commentary-timeline::before {
    content: '';
    position: absolute;
    top: 10px;
    bottom: 10px;
    left: 42px; /* aligns beautifully behind the over tags */
    width: 2px;
    background: #E2E8F0 !important;
    z-index: 1;
    pointer-events: none;
}
.crex-commentary-item {
    display: flex;
    gap: 14px;
    padding: 10px 14px;
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 12px !important;
    align-items: center;
    position: relative;
    z-index: 2;
    transition: all 0.22s ease !important;
}
.crex-commentary-item:hover {
    transform: translateX(3px) !important;
    background: #F8FAFC !important;
    border-color: #CBD5E1 !important;
}
.comm-over-indicator {
    background: #F1F5F9 !important;
    border: 1px solid #E2E8F0 !important;
    color: #64748B !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    padding: 3px 8px !important;
    border-radius: 20px !important;
    font-family: 'Inter', monospace !important;
    min-width: 44px !important;
    text-align: center !important;
    z-index: 3;
}
.comm-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    flex: 1;
}
.comm-tag {
    font-size: 9.5px !important;
    text-transform: uppercase;
    color: #64748B !important;
    font-weight: 800;
    letter-spacing: 0.5px;
}
.comm-tag--wicket {
    color: #ff4d6d !important;
}
.comm-tag--six {
    color: #a855f7 !important;
}
.comm-tag--four {
    color: #3b82f6 !important;
}
.comm-text {
    margin: 0;
    font-size: 13px !important;
    line-height: 1.4;
    color: #334155 !important;
    font-weight: 400 !important;
}
.comm-empty-card {
    padding: 20px;
    text-align: center;
    color: #64748B !important;
    font-size: 12.5px;
    font-weight: 500;
    background: #F8FAFC !important;
    border: 1px dashed #E2E8F0 !important;
    border-radius: 10px;
}

/* Wicket Shake Commentary Alert */
.comm-alert-wicket {
    background: linear-gradient(135deg, rgba(255, 77, 109, 0.08) 0%, rgba(255, 77, 109, 0.02) 100%) !important;
    border: 1.5px solid rgba(255, 77, 109, 0.3) !important;
    box-shadow: 0 4px 12px rgba(255, 77, 109, 0.06) !important;
    animation: commShake 0.6s ease-in-out;
}
.comm-alert-wicket .comm-over-indicator {
    background: rgba(255, 77, 109, 0.12) !important;
    border-color: rgba(255, 77, 109, 0.25) !important;
    color: #ff4d6d !important;
}

/* Six Scale Commentary Alert */
.comm-alert-six {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.08) 0%, rgba(168, 85, 247, 0.02) 100%) !important;
    border: 1.5px solid rgba(168, 85, 247, 0.3) !important;
    box-shadow: 0 4px 12px rgba(168, 85, 247, 0.06) !important;
    animation: commScale 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.comm-alert-six .comm-over-indicator {
    background: rgba(168, 85, 247, 0.12) !important;
    border-color: rgba(168, 85, 247, 0.25) !important;
    color: #a855f7 !important;
}

/* Four Scale Commentary Alert */
.comm-alert-four {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(59, 130, 246, 0.02) 100%) !important;
    border: 1.5px solid rgba(59, 130, 246, 0.3) !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.06) !important;
    animation: commScale 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.comm-alert-four .comm-over-indicator {
    background: rgba(59, 130, 246, 0.12) !important;
    border-color: rgba(59, 130, 246, 0.25) !important;
    color: #3b82f6 !important;
}

@keyframes commShake {
    0%, 100% { transform: translateX(0); }
    15% { transform: translateX(-4px); }
    30% { transform: translateX(3px); }
    45% { transform: translateX(-2px); }
    60% { transform: translateX(1px); }
}
@keyframes commScale {
    0% { transform: scale(0.98); }
    100% { transform: scale(1); }
}

/* Wagon wheel visual styling */
.wagon-wheel-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.wagon-wheel-svg {
    width: 100%;
    max-width: 200px; /* Compressed */
    height: auto;
}
.outfield-boundary {
    fill: url(#turf-gradient);
    stroke: rgba(0, 210, 106, 0.3);
    stroke-width: 1.5;
}
.outfield-inner {
    fill: none;
    stroke: rgba(255, 255, 255, 0.25);
    stroke-dasharray: 4 4;
    stroke-width: 1;
}
.stadium-pitch {
    fill: #dfc187;
    stroke: #bda06a;
    stroke-width: 0.5;
}
.pitch-crease {
    stroke: rgba(255, 255, 255, 0.7);
    stroke-width: 0.5px;
}
.pitch-stumps {
    stroke: #ffffff;
    stroke-width: 0.75px;
}
.shot-vector {
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: all 0.2s ease;
}
.shot-vector:hover {
    stroke-width: 3.5px !important;
    filter: drop-shadow(0 0 3px currentColor);
}
.shot-vector--six {
    stroke: #a855f7;
    stroke-width: 2;
}
.shot-vector--four {
    stroke: #3b82f6;
    stroke-width: 1.8;
}
.shot-vector--run {
    stroke: #00d68f;
    stroke-width: 1.2;
}
.shot-vector--single {
    stroke: #e2e8f0;
    stroke-width: 1;
    stroke-opacity: 0.7;
}
.shot-vector--dot {
    stroke: rgba(255, 255, 255, 0.2);
    stroke-width: 0.8;
    stroke-dasharray: 2 2;
}
.direction-label {
    fill: rgba(255, 255, 255, 0.75);
    font-size: 8px;
    font-weight: 700;
    text-anchor: middle;
    letter-spacing: 1px;
}
.wagon-wheel-legend {
    display: flex;
    gap: 12px;
    font-size: 10.5px;
    font-weight: 700;
    color: #64748B !important;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.legend-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.legend-dot--six { background: #a855f7; }
.legend-dot--four { background: #3b82f6; }
.legend-dot--run { background: #00d68f; }

/* Cumulative run rate chart */
.run-rate-graph-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.run-rate-svg {
    width: 100%;
    height: auto;
}
.graph-path {
    fill: none;
    stroke-width: 1.5px;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.graph-path--home {
    stroke: #00D26A;
}
.graph-path--away {
    stroke: #3b82f6;
}
.axis-label {
    fill: #64748B !important;
    font-size: 8px;
    font-weight: 600;
}
.graph-legend {
    display: flex;
    gap: 12px;
    font-size: 10.5px;
    font-weight: 700;
    color: #64748B !important;
    justify-content: center;
    margin-top: 2px;
}
.legend-line {
    display: inline-block;
    width: 12px;
    height: 3px;
    border-radius: 1.5px;
    vertical-align: middle;
}
.legend-line--home { background: #00D26A; }
.legend-line--away { background: #3b82f6; }

/* Win chance progress bar */
.probability-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.prob-labels-row {
    display: flex;
    justify-content: space-between;
}
.prob-label {
    font-size: 12px;
    font-weight: 700;
    color: #0F172A !important;
    max-width: 48%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.probability-bar-glowing {
    height: 6px; /* Compressed */
    background: #F1F5F9 !important;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    border: 1px solid #E2E8F0;
}
.prob-fill-home {
    display: block;
    height: 100%;
    background: #00D26A;
    border-radius: 4px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}
.probability-values-row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #64748B !important;
}

/* Projected runs card */
.projection-box-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(0, 210, 106, 0.05) !important;
    border: 1px solid rgba(0, 210, 106, 0.15) !important;
    border-radius: 10px;
    padding: 8px 12px;
}
.proj-runs-container {
    display: flex;
    flex-direction: column;
}
.proj-runs-title {
    font-size: 9px;
    text-transform: uppercase;
    color: #64748B !important;
    font-weight: 700;
}
.proj-runs-val {
    font-size: 20px;
    color: #00D26A;
    font-weight: 800;
}
.proj-crr-box {
    text-align: right;
    display: flex;
    flex-direction: column;
}
.proj-crr-lbl {
    font-size: 9px;
    text-transform: uppercase;
    color: #64748B !important;
    font-weight: 700;
}
.proj-crr-val {
    font-size: 13.5px;
    color: #0F172A !important;
    font-weight: 800;
}

/* Expert insights list */
.insights-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.insight-item {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.insight-icon {
    color: #00D26A;
    flex-shrink: 0;
    width: 12px;
    height: 12px;
    margin-top: 2px;
}
.insight-desc {
    font-size: 12px;
    color: #334155 !important;
    line-height: 1.4;
    font-weight: 400;
}

/* Momentum card zero-baseline vertical bars chart */
.momentum-legend-box {
    display: flex;
    gap: 6px;
}
.legend-badge {
    font-size: 8.5px;
    font-weight: 800;
    padding: 1px 5px;
    border-radius: 3px;
    text-transform: uppercase;
    color: #ffffff;
}
.legend-badge--home {
    background: #00D26A;
}
.legend-badge--away {
    background: #3b82f6;
}
.momentum-container {
    position: relative;
    height: 80px; /* Compressed */
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 10px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.momentum-baseline {
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    height: 1px;
    background: #E2E8F0 !important;
    z-index: 1;
}
.momentum-bars-grid {
    display: flex;
    justify-content: space-around;
    align-items: stretch;
    height: 100%;
    position: relative;
    z-index: 2;
}
.momentum-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    color: #64748B !important;
    font-size: 11px;
    font-weight: 600;
}
.momentum-bar-wrapper {
    display: flex;
    flex-direction: column;
    width: 8%;
    height: 50%;
    position: relative;
    cursor: pointer;
}
.momentum-bar-wrapper--up {
    justify-content: flex-end;
    align-self: flex-start;
}
.momentum-bar-wrapper--down {
    justify-content: flex-start;
    align-self: flex-end;
}
.momentum-bar {
    width: 100%;
    border-radius: 2px 2px 0 0;
    transition: all 0.25s ease;
}
.momentum-bar-wrapper--down .momentum-bar {
    border-radius: 0 0 2px 2px;
}
.momentum-bar-wrapper:hover .momentum-bar {
    transform: scaleX(1.15);
    filter: brightness(1.1);
}
.momentum-over-lbl {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    font-size: 7px;
    font-weight: 700;
    color: #64748B !important;
}
.momentum-bar-wrapper--up .momentum-over-lbl {
    bottom: -12px;
}
.momentum-bar-wrapper--down .momentum-over-lbl {
    top: -12px;
}

@keyframes drawPath {
    from { stroke-dashoffset: 1000; opacity: 0; }
    to { stroke-dashoffset: 0; opacity: 1; }
}
.graph-path {
    stroke-dasharray: 1000;
    stroke-dashoffset: 1000;
    animation: drawPath 2s ease-out forwards;
}

/* Tablet and mobile media overrides for layouts */
@media (max-width: 1024px) {
    .match-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .match-card {
        padding: 12px 14px 10px !important;
    }
    .crex-live-table th {
        padding: 3px 4px !important;
        font-size: 9px !important;
    }
    .crex-live-table td {
        padding: 5px 4px !important;
        font-size: 12px !important;
    }
    .bold-runs {
        font-size: 13px !important;
    }
    .over-chip-crex {
        padding: 4px 6px !important;
    }
    .crex-commentary-item {
        padding: 8px 10px !important;
        gap: 10px !important;
    }
    .comm-text {
        font-size: 12px !important;
    }
}
</style>
@endsection
