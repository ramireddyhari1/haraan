@extends('site.layout')
@section('body_class', 'match-page-body')
@section('content')
@php
    $homeTeam = $match['home'];
    $awayTeam = $match['away'];
    $scoreLine = $match['score_text'] ?? (($match['home_score'] ?? 0) . '-' . ($match['away_score'] ?? 0));
    $overs = $match['overs'] ?? $match['time'];
    $decision = $match['decision'] ?? '';
    $crr = $match['crr'] ?? $match['runRate'] ?? $match['run_rate'] ?? '-';

    // Resolve current status text (e.g. Over, Single, Boundary, Six, Wicket, Live) dynamically
    $timeline = $match['timeline'] ?? [];
    $latestEvent = $timeline[0] ?? null;
    $latestText = $latestEvent ? strtoupper($latestEvent['text']) : '';
    $oversVal = floatval($overs);
    
    $statusText = 'Live';
    if ($oversVal > 0 && intval($oversVal) == $oversVal) {
        $statusText = 'Over';
    } elseif ($latestEvent) {
        if (strpos($latestText, 'FOUR') !== false || strpos($latestText, 'BOUNDAR') !== false || $latestText === '4') {
            $statusText = 'Boundary';
        } elseif (strpos($latestText, 'SIX') !== false || $latestText === '6') {
            $statusText = 'Six';
        } elseif (strpos($latestText, 'WICKET') !== false || strpos($latestText, 'OUT') !== false || $latestText === 'W') {
            $statusText = 'Wicket';
        } elseif (strpos($latestText, '1 RUN') !== false || $latestText === '1') {
            $statusText = 'Single';
        } elseif (strpos($latestText, '2 RUN') !== false || $latestText === '2') {
            $statusText = 'Double';
        } elseif (strpos($latestText, '3 RUN') !== false || $latestText === '3') {
            $statusText = 'Three';
        } elseif (strpos($latestText, 'WD') !== false || strpos($latestText, 'WIDE') !== false) {
            $statusText = 'Wide';
        } elseif (strpos($latestText, 'NB') !== false || strpos($latestText, 'NO BALL') !== false) {
            $statusText = 'No Ball';
        } elseif (strpos($latestText, 'DOT') !== false || $latestText === '0') {
            $statusText = 'Dot';
        }
    }

    // Parse over summaries
    $overSummary = $match['over_summary'] ?? [];

    // Determine batting team from the last over in over_summary
    $battingTeam = 'home'; // default
    if (!empty($overSummary)) {
        $lastOver = end($overSummary);
        $battingTeam = isset($lastOver['batting']) ? $lastOver['batting'] : 'home';
    }

    // Separately count completed overs and wickets for home and away from over_summary
    $homeSummaryWickets = 0;
    $awaySummaryWickets = 0;
    $innings1OversCount = 0;
    $innings2OversCount = 0;
    
    foreach ($overSummary as $o) {
        $oBatting = $o['batting'] ?? 'home';
        if ($oBatting === 'home') {
            $innings1OversCount++;
            if (isset($o['balls']) && is_array($o['balls'])) {
                foreach ($o['balls'] as $ball) {
                    if (strtoupper(trim((string)$ball)) === 'W') {
                        $homeSummaryWickets++;
                    }
                }
            }
        } else {
            $innings2OversCount++;
            if (isset($o['balls']) && is_array($o['balls'])) {
                foreach ($o['balls'] as $ball) {
                    if (strtoupper(trim((string)$ball)) === 'W') {
                        $awaySummaryWickets++;
                    }
                }
            }
        }
    }

    // Determine current active wickets count
    $scoreText = $match['score_text'] ?? '';
    $wicketsCount = 0;
    if (preg_match('/-(\d+)/', $scoreText, $m)) {
        $wicketsCount = (int)$m[1];
    } else {
        $wicketsCount = ($battingTeam === 'home') ? $homeSummaryWickets : $awaySummaryWickets;
    }

    // Set individual team details
    $homeScoreVal = $match['home_score'] ?? 0;
    $awayScoreVal = $match['away_score'] ?? 0;

    $homeWkts = ($battingTeam === 'home') ? $wicketsCount : $homeSummaryWickets;
    $awayWkts = ($battingTeam === 'away') ? $wicketsCount : $awaySummaryWickets;

    $homeOversVal = ($battingTeam === 'home') ? $overs : ($innings1OversCount . '.0');
    $awayOversVal = ($battingTeam === 'away') ? $overs : ($innings2OversCount . '.0');

    // Total Match Overs (usually 20, or parsed from competition)
    $totalMatchOvers = 20;
    if (isset($match['competition']) && preg_match('/(\d+)/', $match['competition'], $matchesComp)) {
        $totalMatchOvers = (int)$matchesComp[1];
    }

    // Build Equation
    $equationText = '';
    if (strcasecmp($match['status'], 'Live') === 0) {
        if ($battingTeam === 'away' && $homeScoreVal > 0) {
            $target = $homeScoreVal + 1;
            $runsNeeded = $target - $awayScoreVal;
            
            // Parse active overs to get balls bowled
            $oParts = explode('.', (string)$overs);
            $completedOvers = isset($oParts[0]) ? (int)$oParts[0] : 0;
            $additionalBalls = isset($oParts[1]) ? (int)$oParts[1] : 0;
            $ballsBowled = $completedOvers * 6 + $additionalBalls;
            $totalBalls = $totalMatchOvers * 6;
            $ballsRemaining = max(0, $totalBalls - $ballsBowled);
            
            if ($runsNeeded > 0 && $ballsRemaining >= 0) {
                $equationText = $awayTeam . " need " . $runsNeeded . " runs in " . $ballsRemaining . " balls";
            } elseif ($runsNeeded <= 0) {
                $equationText = $awayTeam . " won the match";
            }
        } elseif ($battingTeam === 'home' && $awayScoreVal > 0) {
            // Home is chasing Away (Innings 2 home batting)
            $target = $awayScoreVal + 1;
            $runsNeeded = $target - $homeScoreVal;
            
            $oParts = explode('.', (string)$overs);
            $completedOvers = isset($oParts[0]) ? (int)$oParts[0] : 0;
            $additionalBalls = isset($oParts[1]) ? (int)$oParts[1] : 0;
            $ballsBowled = $completedOvers * 6 + $additionalBalls;
            $totalBalls = $totalMatchOvers * 6;
            $ballsRemaining = max(0, $totalBalls - $ballsBowled);
            
            if ($runsNeeded > 0 && $ballsRemaining >= 0) {
                $equationText = $homeTeam . " need " . $runsNeeded . " runs in " . $ballsRemaining . " balls";
            } elseif ($runsNeeded <= 0) {
                $equationText = $homeTeam . " won the match";
            }
        } else {
            $equationText = $decision ?: "Match is Live";
        }
    } elseif (strcasecmp($match['status'], 'Completed') === 0) {
        if ($homeScoreVal > $awayScoreVal) {
            $equationText = $homeTeam . " won by " . ($homeScoreVal - $awayScoreVal) . " runs";
        } elseif ($awayScoreVal > $homeScoreVal) {
            $equationText = $awayTeam . " won by " . ($awayScoreVal - $homeScoreVal) . " runs";
        } else {
            $equationText = "Match Tied";
        }
    } else {
        $equationText = "Match Scheduled";
    }

    // Calculate Required Run Rate (RRR)
    $rrr = '-';
    if (strcasecmp($match['status'], 'Live') === 0) {
        if ($battingTeam === 'away' && $homeScoreVal > 0) {
            $target = $homeScoreVal + 1;
            $runsNeeded = $target - $awayScoreVal;
            $oParts = explode('.', (string)$overs);
            $completedOvers = isset($oParts[0]) ? (int)$oParts[0] : 0;
            $additionalBalls = isset($oParts[1]) ? (int)$oParts[1] : 0;
            $ballsBowled = $completedOvers * 6 + $additionalBalls;
            $totalBalls = $totalMatchOvers * 6;
            $ballsRemaining = max(0, $totalBalls - $ballsBowled);
            if ($ballsRemaining > 0) {
                $rrr = number_format(($runsNeeded / $ballsRemaining) * 6, 2);
            }
        } elseif ($battingTeam === 'home' && $awayScoreVal > 0) {
            $target = $awayScoreVal + 1;
            $runsNeeded = $target - $homeScoreVal;
            $oParts = explode('.', (string)$overs);
            $completedOvers = isset($oParts[0]) ? (int)$oParts[0] : 0;
            $additionalBalls = isset($oParts[1]) ? (int)$oParts[1] : 0;
            $ballsBowled = $completedOvers * 6 + $additionalBalls;
            $totalBalls = $totalMatchOvers * 6;
            $ballsRemaining = max(0, $totalBalls - $ballsBowled);
            if ($ballsRemaining > 0) {
                $rrr = number_format(($runsNeeded / $ballsRemaining) * 6, 2);
            }
        }
    }

    // Calculate Momentum Data for layout (Last 10 completed overs chronological)
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
@endphp
<!-- Sticky Broadcast Scorebar -->
<div class="sticky-broadcast-scorebar">
    <div class="sticky-inner container">
        <div class="sticky-left">
            <span class="live-indicator-pulse"></span>
            <span class="sticky-league-txt">Andhra County League • LIVE</span>
        </div>
        <div class="sticky-center">
            <span class="sticky-score-txt" id="sticky_score_text">
                {{ $homeTeam }} {{ $homeScoreVal }}-{{ $homeWkts }} ({{ $homeOversVal }} ov) vs {{ $awayTeam }} {{ $awayScoreVal > 0 ? $awayScoreVal . '-' . $awayWkts : 'yet to bat' }}
            </span>
            <!-- Tiny Momentum Sparkline -->
            @if(!empty($momentumData))
                <span class="sticky-momentum-wrap" style="display: inline-flex; align-items: center; margin-left: 12px; border-left: 1px solid rgba(255,255,255,0.15); padding-left: 12px; vertical-align: middle;">
                    <span style="font-size: 9px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">MOMENTUM</span>
                    <svg width="60" height="20" style="vertical-align: middle; margin-left: 8px;">
                        @php
                            $recentMomentum = array_slice($momentumData, -5);
                            $sparkPoints = [];
                            foreach ($recentMomentum as $idx => $md) {
                                $sx = $idx * 12 + 6;
                                // Map score (-15 to +15) to height (2 to 18)
                                $sy = 10 - ($md['score'] / 15) * 8;
                                $sy = max(2, min(18, $sy));
                                $sparkPoints[] = "$sx,$sy";
                            }
                            $sparkPath = implode(" ", $sparkPoints);
                        @endphp
                        @if(count($sparkPoints) > 1)
                            <polyline points="{{ $sparkPath }}" fill="none" stroke="#34d399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach($recentMomentum as $idx => $md)
                                @php
                                    $sx = $idx * 12 + 6;
                                    $sy = 10 - ($md['score'] / 15) * 8;
                                    $sy = max(2, min(18, $sy));
                                    $dotColor = $md['score'] >= 0 ? '#10b981' : '#3b82f6';
                                    if ($md['wickets'] > 0) $dotColor = '#ef4444'; // Red for wickets!
                                @endphp
                                <circle cx="{{ $sx }}" cy="{{ $sy }}" r="2.5" fill="{{ $dotColor }}" />
                            @endforeach
                        @endif
                    </svg>
                </span>
            @endif
        </div>
        <div class="sticky-right" id="sticky_equation_text">
            @if(strcasecmp($match['status'], 'Live') === 0)
                @if($rrr !== '-')
                    Need {{ $runsNeeded }} off {{ $ballsRemaining }} (REQ {{ $rrr }})
                @else
                    CRR {{ $crr }}
                @endif
            @else
                {{ $equationText }}
            @endif
        </div>
    </div>
</div>

<section class="match-page">
    <header class="match-hero-broadcast">
        <div class="broadcast-overlay-glow"></div>
        <div class="match-hero__inner container" style="position: relative; z-index: 2;">
            <!-- Top Broadcast Line: League & Actions -->
            <div class="broadcast-topline">
                <div class="broadcast-league">
                    <span class="live-indicator-pulse"></span>
                    <span class="league-text">Andhra County League • Season 2026</span>
                </div>
                <div class="broadcast-venue">
                    📍 Rajiv Gandhi International Stadium, Hyderabad
                </div>
                <div class="match-hero__actions">
                    <a href="{{ route('site.gamehub.actionboard') }}" class="btn-broadcast-nav">Haran Live</a>
                    <a href="{{ route('site.gamehub') }}" class="btn-broadcast-nav">GameHub</a>
                    @auth
                        @if($match['user_id'] === auth()->id())
                            <a href="{{ route('site.gamehub.actionboard.control', ['id' => $match['id']]) }}" class="btn-broadcast-control">Control Room</a>
                        @endif
                    @else
                        <button onclick="document.getElementById('loginBtn').click();" class="btn-broadcast-control" style="cursor:pointer;">Control Room</button>
                    @endauth
                </div>
            </div>

            <!-- Centered Broadcast Scoreboard Card -->
            @php
                $latestEvent = $timeline[0] ?? null;
                $lastBallCode = '';
                if ($latestEvent) {
                    $lText = strtoupper($latestEvent['text'] ?? '');
                    if (strpos($lText, 'FOUR') !== false || $lText === '4') $lastBallCode = '4';
                    elseif (strpos($lText, 'SIX') !== false || $lText === '6') $lastBallCode = '6';
                    elseif (strpos($lText, 'WICKET') !== false || strpos($lText, 'OUT') !== false || $lText === 'W') $lastBallCode = 'W';
                    elseif (strpos($lText, 'WD') !== false || strpos($lText, 'WIDE') !== false) $lastBallCode = 'WD';
                    elseif (strpos($lText, 'NB') !== false || strpos($lText, 'NO BALL') !== false) $lastBallCode = 'NB';
                    elseif (strpos($lText, 'DOT') !== false || $lText === '0' || strpos($lText, 'DOT BALL') !== false) $lastBallCode = '0';
                    elseif (preg_match('/(\d+)\s*RUN/', $lText, $matchRuns)) $lastBallCode = $matchRuns[1];
                    else $lastBallCode = substr($latestEvent['text'] ?? '', 0, 3);
                }

                $initAlertClass = '';
                if ($latestEvent) {
                    $latestText = strtoupper($latestEvent['text'] ?? '');
                    if (strpos($latestText, 'WICKET') !== false || strpos($latestText, 'OUT') !== false || $latestText === 'W') {
                        $initAlertClass = 'pulse-wicket-alert';
                    } elseif (strpos($latestText, 'SIX') !== false || $latestText === '6') {
                        $initAlertClass = 'glow-six-alert';
                    }
                }
            @endphp
            <div class="broadcast-scoreboard-card {{ $initAlertClass }}">
                <!-- Live animation overlay container -->
                <div id="live-animation-overlay" class="live-animation-overlay"></div>

                <!-- Top Row: League & LIVE indicator -->
                <div class="scoreboard-header-row">
                    <span class="league-title-main">Andhra County League</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div class="broadcast-center-ball" id="broadcast_center_ball">
                            @if($lastBallCode !== '')
                                <div id="broadcast_last_ball_badge" class="broadcast-big-ball-glow event-{{ strtolower($lastBallCode) === 'w' ? 'wicket' : (is_numeric($lastBallCode) && (int)$lastBallCode === 4 ? 'four' : (is_numeric($lastBallCode) && (int)$lastBallCode === 6 ? 'six' : 'run')) }}">{{ $lastBallCode }}</div>
                            @endif
                        </div>
                        <span class="live-indicator-broadcast">
                            <span class="live-pulse-dot"></span>
                            LIVE
                        </span>
                    </div>
                </div>

                <!-- Main Scorebody: Batting Team & Big score -->
                <div class="scoreboard-body-vertical">
                    <!-- Teams info header -->
                    <div class="teams-versus-line">
                        <span class="versus-team-lbl {{ $battingTeam === 'home' ? 'is-batting' : '' }}" id="home_team_block">
                            <img class="team-badge-mini" src="https://ui-avatars.com/api/?name={{ urlencode($homeTeam) }}&background=00d68f&color=fff&size=24&bold=true" alt="Home Logo">
                            <span>{{ $homeTeam }}</span>
                        </span>
                        <span class="versus-sep">VS</span>
                        <span class="versus-team-lbl {{ $battingTeam === 'away' ? 'is-batting' : '' }}" id="away_team_block">
                            <span>{{ $awayTeam }}</span>
                            <img class="team-badge-mini" src="https://ui-avatars.com/api/?name={{ urlencode($awayTeam) }}&background=3b82f6&color=fff&size=24&bold=true" alt="Away Logo">
                        </span>
                    </div>

                    <div class="active-score-block">
                        <div class="score-num-glowing" id="hero_score_num">
                            {{ $battingTeam === 'home' ? $homeScoreVal : $awayScoreVal }}-{{ $battingTeam === 'home' ? $homeWkts : $awayWkts }}
                            <span class="overs-parentheses" id="hero_overs_parentheses">({{ $battingTeam === 'home' ? $homeOversVal : $awayOversVal }} ov)</span>
                        </div>
                    </div>

                    <!-- Non-batting team score (Smaller underneath) -->
                    <div class="inactive-score-block" id="hero_sub_info">
                        @if($battingTeam === 'home')
                            {{ $awayTeam }} <span class="inactive-score-val">{{ $awayScoreVal > 0 ? $awayScoreVal . '-' . $awayWkts : 'yet to bat' }}</span>
                        @else
                            {{ $homeTeam }} <span class="inactive-score-val">{{ $homeScoreVal > 0 ? $homeScoreVal . '-' . $homeWkts : 'yet to bat' }}</span>
                        @endif
                    </div>
                </div>

                <!-- Recent Balls of Current Over -->
                <div class="scoreboard-balls-strip">
                    <span class="balls-strip-lbl">This Over:</span>
                    <div class="balls-strip-row">
                        @if(!empty($overSummary) && isset($overSummary[0]['balls']))
                            @foreach($overSummary[0]['balls'] as $ball)
                                @php
                                    $ballStr = trim((string)$ball);
                                    $ballLower = strtolower($ballStr);
                                    $bClass = 'mini-ball-badge';
                                    if ($ballStr === '0') $bClass .= ' dot';
                                    elseif ($ballLower === 'w') $bClass .= ' wicket';
                                    elseif ($ballStr === '4') $bClass .= ' four';
                                    elseif ($ballStr === '6') $bClass .= ' six';
                                    elseif (is_numeric($ballStr)) $bClass .= ' run';
                                    else $bClass .= ' extra';
                                @endphp
                                <span class="{{ $bClass }}">{{ $ballStr }}</span>
                            @endforeach
                        @else
                            <span class="no-balls-lbl">Over starting...</span>
                        @endif
                    </div>
                </div>

                <!-- Active Batters & Bowler Mini strip -->
                @php
                    $liveBatters = $match['batters'] ?? [];
                    $liveBowler = $match['bowler'] ?? [];
                    
                    $resolvePlayerNameLayout = function($nameOrId) use ($match) {
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
                @endphp
                @if(!empty($liveBatters))
                    <div class="scoreboard-players-strip">
                        <div class="batters-strip-col">
                            @foreach($liveBatters as $idx => $b)
                                <div class="strip-player-item {{ $idx === 0 ? 'is-striker' : '' }}">
                                    <span class="player-indicator-dot"></span>
                                    <span class="player-name-txt">{{ $resolvePlayerNameLayout($b['name']) }}</span>
                                    <strong class="player-score-txt">{{ $b['runs'] }}<span class="balls-sub">({{ $b['balls'] }})</span></strong>
                                </div>
                            @endforeach
                        </div>
                        <div class="bowler-strip-col">
                            @if(!empty($liveBowler) && isset($liveBowler['name']))
                                <div class="strip-player-item bowler">
                                    <span class="bowler-indicator-icon">🥎</span>
                                    <span class="player-name-txt">{{ $resolvePlayerNameLayout($liveBowler['name']) }}</span>
                                    <strong class="player-score-txt">{{ $liveBowler['figures'] ?? '0-0' }} <span class="balls-sub">({{ $liveBowler['overs'] ?? '0.0' }} ov)</span></strong>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Divider -->
                <div class="scoreboard-divider"></div>

                <!-- Bottom Row: Equation and Run Rates -->
                <div class="scoreboard-footer-row">
                    <div class="equation-container" id="broadcast_runs_required_text">
                        {{ $equationText }}
                    </div>
                    <div class="rates-container">
                        <span class="rate-item">CRR <strong id="broadcast_crr_val">{{ $crr }}</strong></span>
                        @if($rrr !== '-')
                            <span class="rate-divider">•</span>
                            <span class="rate-item req-rate">REQ <strong id="broadcast_rrr_val">{{ $rrr }}</strong></span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Toss Detail (Centered below card) -->
            <div class="broadcast-toss-row">
                <div id="broadcast_toss_detail_text" class="toss-detail-text-bottom">
                    <strong>Toss:</strong> {{ $decision ?: ($homeTeam . ' won the toss') }}
                </div>
            </div>

            <!-- Tabs Section (Tightened) -->
            <nav class="broadcast-tabs" aria-label="Match sections">
                <a class="{{ $activeTab === 'info' ? 'is-active' : '' }}" href="{{ route('site.gamehub.actionboard.match.info', ['id' => $match['id']]) }}">Match Info</a>
                <a class="{{ $activeTab === 'live' ? 'is-active' : '' }}" href="{{ route('site.gamehub.actionboard.match', ['id' => $match['id']]) }}">Live Dashboard</a>
                <a class="{{ $activeTab === 'scorecard' ? 'is-active' : '' }}" href="{{ route('site.gamehub.actionboard.match.scorecard', ['id' => $match['id']]) }}">Full Scorecard</a>
            </nav>
        </div>
    </header>

    <main class="match-page-container match-content">
        @yield('match_content')
    </main>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Oswald:wght@500;700&family=Rajdhani:wght@600;700;800&display=swap');

/* Clean Typography Overrides */
* {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
}

/* Animation overlay container scoped inside the scoreboard card */
.live-animation-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: transparent;
    z-index: 10;
    pointer-events: none;
    display: none;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 20px;
}
.live-animation-overlay.is-active {
    display: flex;
}
/* Subtle gradients based on events */
.live-animation-overlay.event-wicket {
    background: radial-gradient(circle, rgba(255, 77, 109, 0.25) 0%, rgba(255, 77, 109, 0.05) 50%, transparent 100%);
}
.live-animation-overlay.event-six {
    background: radial-gradient(circle, rgba(168, 85, 247, 0.25) 0%, rgba(168, 85, 247, 0.05) 50%, transparent 100%);
}
.live-animation-overlay.event-four {
    background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, rgba(59, 130, 246, 0.05) 50%, transparent 100%);
}
.live-animation-overlay.event-run {
    background: radial-gradient(circle, rgba(0, 210, 106, 0.2) 0%, rgba(0, 210, 106, 0.03) 50%, transparent 100%);
}
.live-animation-overlay.event-extra {
    background: radial-gradient(circle, rgba(251, 191, 36, 0.2) 0%, rgba(251, 191, 36, 0.03) 50%, transparent 100%);
}

.live-animation-overlay .event-text {
    font-family: 'Oswald', sans-serif;
    font-weight: 900;
    font-size: 40px;
    text-transform: uppercase;
    color: #ffffff;
    z-index: 11;
    transform: scale(0.5);
    opacity: 0;
    animation: textZoomFade 2.2s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

@keyframes textZoomFade {
    0% { transform: scale(0.5); opacity: 0; }
    15% { transform: scale(1.1); opacity: 1; text-shadow: 0 0 20px currentColor; }
    25% { transform: scale(1); opacity: 1; text-shadow: 0 0 15px currentColor; }
    80% { transform: scale(1); opacity: 1; text-shadow: 0 0 15px currentColor; }
    100% { transform: scale(1.2); opacity: 0; filter: blur(3px); }
}

.live-animation-overlay.event-wicket .event-text { color: #ff4d6d; }
.live-animation-overlay.event-six .event-text { color: #a855f7; }
.live-animation-overlay.event-four .event-text { color: #3b82f6; }
.live-animation-overlay.event-run .event-text { color: #00D26A; }
.live-animation-overlay.event-extra .event-text { color: #fbbf24; }
.live-animation-overlay.event-dot .event-text { color: #94a3b8; }

.live-animation-overlay .particle {
    position: absolute;
    border-radius: 50%;
    background: currentColor;
    opacity: 0;
    pointer-events: none;
    z-index: 12;
    animation: particleExplode 1.0s cubic-bezier(0.1, 0.8, 0.3, 1) forwards;
}

@keyframes particleExplode {
    0% {
        transform: translate(0, 0) scale(1);
        opacity: 1;
    }
    100% {
        transform: translate(var(--dx), var(--dy)) scale(0);
        opacity: 0;
    }
}

/* Style global topbar for sports broadcast - DO NOT CHANGE */
header.topbar {
    background: #091320 !important;
    border-bottom: 1px solid rgba(0, 210, 106, 0.15) !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
    padding: 8px 0 !important;
}
header.topbar .brand__text strong {
    color: #00D26A !important; /* accent emerald */
    font-family: 'Rajdhani', sans-serif !important;
    font-size: 20px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}
header.topbar .brand__text small {
    color: #94a3b8 !important;
}
header.topbar .topnav__link {
    color: #94a3b8 !important;
    font-family: 'Inter', sans-serif !important;
    font-weight: 700 !important;
    font-size: 13px !important;
}
header.topbar .topnav__link:hover, header.topbar .topnav__link.is-active {
    color: #00D26A !important;
}
header.topbar .topnav__link.is-active::after {
    background: #00D26A !important;
    box-shadow: 0 0 10px rgba(0, 210, 106, 0.6) !important;
}
header.topbar .topbar__search, header.topbar .location-pill {
    display: none !important;
}

/* Body and Theme backgrounds */
html, body, main[role="main"] {
    background-color: #F3F4F6 !important;
    background: #F3F4F6 !important;
    color: #1E293B !important;
    overflow-x: hidden;
    min-height: 100vh;
}
.match-page-body {
    background: #f6f9fb !important;
    color: #1E293B;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    overflow-y: scroll;
}

/* Sticky Broadcast Scorebar */
.sticky-broadcast-scorebar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid #dfe8ef;
    z-index: 9999;
    padding: 8px 0;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
    transform: translateY(-100%);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
body.scrolled-down .sticky-broadcast-scorebar {
    transform: translateY(0);
}
.sticky-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    font-family: 'Inter Tight', sans-serif;
}
.sticky-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.sticky-league-txt {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #059669;
}
.sticky-center {
    font-weight: 900;
    font-size: 14.5px;
    color: #0f172a;
}
.sticky-right {
    font-size: 12px;
    color: #dc2626; /* Wicket red */
    font-weight: 800;
    text-transform: uppercase;
}

/* Broadcast Header Section */
.match-hero-broadcast {
    position: relative;
    background: #f6f9fb;
    color: #1E293B;
    padding: 10px 20px 0;
    overflow: hidden;
    border-bottom: 1px solid #E2E8F0;
}
.broadcast-overlay-glow {
    position: absolute;
    top: -40%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(0, 210, 106, 0.03) 0%, transparent 70%);
    pointer-events: none;
    z-index: 1;
}

/* Topline: Compact spacing */
.broadcast-topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #F1F5F9;
    flex-wrap: wrap;
    font-size: 12px;
    z-index: 2;
    position: relative;
}
.broadcast-league {
    display: flex;
    align-items: center;
}
.league-text {
    font-family: 'Rajdhani', sans-serif;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #059669;
}
.broadcast-venue {
    color: #64748B;
    font-weight: 600;
}
.match-hero__actions {
    display: flex;
    gap: 8px;
}
.btn-broadcast-nav {
    color: #334155;
    text-decoration: none;
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid #E2E8F0;
    padding: 4px 10px;
    border-radius: 5px;
    transition: all 0.2s;
    background: #FFFFFF;
}
.btn-broadcast-nav:hover {
    background: #F8FAFC;
    color: #0F172A;
}
.btn-broadcast-control {
    color: #059669;
    text-decoration: none;
    font-weight: 800;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid #059669;
    padding: 4px 10px;
    border-radius: 5px;
    transition: all 0.2s;
    background: rgba(5, 150, 105, 0.08);
}
.btn-broadcast-control:hover {
    background: #059669;
    color: #000000;
}

/* Centered Broadcast Scoreboard Card - Redesigned */
.broadcast-scoreboard-card {
    background: #FFFFFF;
    border: 1px solid #d9e5eb;
    border-radius: 20px;
    padding: 20px;
    margin: 16px auto;
    max-width: none;
    text-align: center;
    position: relative;
    z-index: 2;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.scoreboard-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 8px;
}

.league-title-main {
    font-family: 'Rajdhani', sans-serif;
    font-size: 14px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #0F172A;
}

.live-indicator-broadcast {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: 'Rajdhani', sans-serif;
    font-size: 13px;
    font-weight: 900;
    color: #dc2626;
    background: rgba(220, 38, 38, 0.08);
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid rgba(220, 38, 38, 0.18);
    letter-spacing: 0.5px;
}

.live-pulse-dot {
    width: 8px;
    height: 8px;
    background-color: #dc2626;
    border-radius: 50%;
    box-shadow: 0 0 8px #dc2626;
    animation: livePulseBreathing 1.2s infinite alternate;
}

@keyframes livePulseBreathing {
    0% { transform: scale(0.8); opacity: 0.5; }
    100% { transform: scale(1.2); opacity: 1; box-shadow: 0 0 12px #dc2626; }
}

.scoreboard-body-vertical {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    margin: 12px 0;
}

.teams-versus-line {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    width: 100%;
}

.versus-team-lbl {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Rajdhani', sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
}

.versus-team-lbl.is-batting {
    color: #0F172A;
    font-weight: 800;
}

.team-badge-mini {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 1px solid #E2E8F0;
    background: #F1F5F9;
    object-fit: cover;
}

.versus-sep {
    font-family: 'Rajdhani', sans-serif;
    font-size: 12px;
    font-weight: 800;
    color: #94A3B8;
}

.active-score-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.score-num-glowing {
    font-family: 'Oswald', sans-serif;
    font-size: 56px;
    font-weight: 700;
    color: #0F172A;
    line-height: 1;
    letter-spacing: -1px;
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-top: 4px;
    transition: all 0.3s ease;
}

.overs-parentheses {
    font-family: 'Rajdhani', sans-serif;
    font-size: 20px;
    color: #059669; /* Accent emerald */
    font-weight: 700;
    letter-spacing: 0.5px;
}

.inactive-score-block {
    font-family: 'Rajdhani', sans-serif;
    font-size: 14px;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.inactive-score-val {
    color: #0F172A;
    font-weight: 700;
}

/* Redesigned Current Over Timeline Strip */
.scoreboard-balls-strip {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin: 12px 0 4px;
    padding: 8px 12px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
}

.balls-strip-lbl {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748B;
    letter-spacing: 0.5px;
}

.balls-strip-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.mini-ball-badge {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 10px;
    font-weight: 900;
    user-select: none;
}

.mini-ball-badge.dot {
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    color: #64748B;
}

.mini-ball-badge.run {
    background: #059669;
    color: #000000;
}

.mini-ball-badge.four {
    background: #2563EB;
    color: #ffffff;
    box-shadow: 0 0 6px rgba(37, 99, 235, 0.2);
}

.mini-ball-badge.six {
    background: #9333EA;
    color: #ffffff;
    box-shadow: 0 0 6px rgba(147, 51, 234, 0.2);
}

.mini-ball-badge.wicket {
    background: #EF4444;
    color: #ffffff;
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.3);
    animation: miniWicketScale 1.5s infinite alternate;
}

@keyframes miniWicketScale {
    0% { transform: scale(1); }
    100% { transform: scale(1.1); }
}

.mini-ball-badge.extra {
    background: #F59E0B;
    color: #ffffff;
}

.no-balls-lbl {
    font-size: 11px;
    color: #64748B;
    font-style: italic;
}

/* Batters and Bowlers integrated strip */
.scoreboard-players-strip {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 16px;
    margin: 12px 0 0;
    padding: 8px 12px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    text-align: left;
}

.batters-strip-col {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bowler-strip-col {
    display: flex;
    align-items: center;
    border-left: 1px solid #E2E8F0;
    padding-left: 12px;
}

.strip-player-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748B;
    width: 100%;
}

.strip-player-item.is-striker {
    color: #0F172A;
}

.player-indicator-dot {
    width: 5px;
    height: 5px;
    background-color: transparent;
    border-radius: 50%;
}

.is-striker .player-indicator-dot {
    background-color: #059669;
    box-shadow: 0 0 6px #059669;
}

.player-name-txt {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 110px;
    font-weight: 600;
}

.is-striker .player-name-txt {
    font-weight: 700;
}

.player-score-txt {
    margin-left: auto;
    color: #0F172A;
}

.balls-sub {
    font-size: 10px;
    font-weight: 500;
    color: #64748B;
    margin-left: 2px;
}

.bowler-indicator-icon {
    font-size: 11px;
}

.scoreboard-divider {
    height: 1px;
    background: #E2E8F0;
    margin: 12px 0;
}

.scoreboard-footer-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.equation-container {
    font-family: 'Rajdhani', sans-serif;
    font-size: 15px;
    font-weight: 800;
    color: #dc2626;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rates-container {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Rajdhani', sans-serif;
    font-size: 13.5px;
    color: #64748B;
    font-weight: 700;
}

.rates-container strong {
    color: #0F172A;
}

.rate-divider {
    color: #E2E8F0;
}

.req-rate strong {
    color: #dc2626;
}

.toss-detail-text-bottom {
    font-size: 12px;
    color: #64748B;
    text-align: center;
    margin-top: 6px;
    font-weight: 600;
}

/* Wicket Red Pulse Alert Animation */
.pulse-wicket-alert {
    animation: wicketRedAlertKey 1.5s ease-in-out infinite alternate;
}
@keyframes wicketRedAlertKey {
    0% {
        border-color: #ff4d6d;
        box-shadow: 0 0 15px rgba(255, 77, 109, 0.15), inset 0 0 10px rgba(255, 77, 109, 0.05);
    }
    100% {
        border-color: #ff4d6d;
        box-shadow: 0 0 30px rgba(255, 77, 109, 0.3), inset 0 0 20px rgba(255, 77, 109, 0.15);
        transform: translateY(-1px);
    }
}

/* SIX Purple Glow Expand Animation */
.glow-six-alert {
    animation: sixPurpleAlertKey 1.5s ease-in-out infinite alternate;
}
@keyframes sixPurpleAlertKey {
    0% {
        border-color: #9333EA;
        box-shadow: 0 0 15px rgba(147, 51, 234, 0.15), inset 0 0 10px rgba(147, 51, 234, 0.05);
    }
    100% {
        border-color: #9333EA;
        box-shadow: 0 0 30px rgba(147, 51, 234, 0.3), inset 0 0 20px rgba(147, 51, 234, 0.15);
        transform: translateY(-1px);
    }
}

/* Score Flash Green Animation */
.score-flash-green {
    animation: scoreFlashGreenKey 0.8s ease;
}
@keyframes scoreFlashGreenKey {
    0% { color: #00D26A; text-shadow: 0 0 25px #00D26A; }
    100% { color: #0f172a; }
}

/* Big Ball Status Badge */
.broadcast-center-ball {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-shrink: 0;
}
.broadcast-big-ball-glow {
    font-size: 13px;
    font-weight: 900;
    line-height: 1.2;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    animation: broadcastGlowPulse 1.8s infinite;
}
.broadcast-big-ball-glow.event-wicket {
    color: #ffffff;
    background: #EF4444;
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.4);
}
.broadcast-big-ball-glow.event-four {
    color: #ffffff;
    background: #2563EB;
    box-shadow: 0 0 12px rgba(37, 99, 235, 0.4);
}
.broadcast-big-ball-glow.event-six {
    color: #ffffff;
    background: #9333EA;
    box-shadow: 0 0 12px rgba(147, 51, 234, 0.4);
}
.broadcast-big-ball-glow.event-run {
    color: #ffffff;
    background: #00D26A;
    box-shadow: 0 0 12px rgba(0, 210, 106, 0.4);
}
@keyframes broadcastGlowPulse {
    0% { transform: scale(0.96); opacity: 0.95; }
    50% { transform: scale(1.03); opacity: 1; }
    100% { transform: scale(0.96); opacity: 0.95; }
}

/* Tabs Navigation - Premium Sports Look */
.broadcast-tabs {
    display: flex;
    gap: 24px;
    margin-top: 8px;
    border-top: 1px solid #E2E8F0;
    z-index: 2;
    position: relative;
}
.broadcast-tabs a {
    color: #64748B;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 0;
    position: relative;
    transition: all 0.2s;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}
.broadcast-tabs a:hover {
    color: #0F172A;
}
.broadcast-tabs a.is-active {
    color: #059669;
}
.broadcast-tabs a.is-active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 3px;
    background: #059669;
    border-radius: 3px 3px 0 0;
    box-shadow: 0 0 10px rgba(5, 150, 105, 0.5);
}

.match-page-container {
    padding-top: 8px;
}

.match-page-container .container {
    max-width: 1280px;
}

/* Mobile responsive broadcast views */
@media (max-width: 768px) {
    header.topbar {
        padding: 4px 0 !important;
    }
    .broadcast-venue {
        display: none;
    }
    .broadcast-scoreboard-card {
        padding: 14px !important;
        margin: 8px auto !important;
        border-radius: 16px !important;
    }
    .score-num-glowing {
        font-size: 44px !important;
    }
    .overs-parentheses {
        font-size: 16px !important;
    }
    .versus-team-lbl {
        font-size: 15px !important;
    }
    .team-badge-mini {
        width: 18px !important;
        height: 18px !important;
    }
    .broadcast-big-ball-glow {
        font-size: 11px !important;
        padding: 2px 6px !important;
    }
    .broadcast-tabs {
        justify-content: center;
        gap: 16px;
    }
    .broadcast-tabs a {
        font-size: 11px;
        padding: 8px 0;
    }
    .scoreboard-players-strip {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .bowler-strip-col {
        border-left: none;
        padding-left: 0;
        border-top: 1px solid #e2e8f0;
        padding-top: 8px;
    }
    /* Stack the two-column match layout on mobile — otherwise the 320px
       side column squeezes .match-main down to ~39px and the innings
       selector bleeds off the left edge. */
    .match-grid {
        grid-template-columns: 1fr !important;
    }
}

.meta-line{font-size:14px;color:#475569;margin-bottom:8px}
.meta-note{font-size:16px;color:#D97706;font-weight:800}
.match-content{padding-top:16px}
.match-grid{display:grid;grid-template-columns:1fr minmax(0, 320px);gap:16px;align-items:start}
.match-main{display:grid;gap:16px;min-width:0}
.match-side{display:grid;gap:16px;min-width:0}

/* White glassmorphic cards override - Global Dark Sports Cards */
.match-card {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 20px !important;
    padding: 16px 20px !important;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03) !important;
    position: relative;
    overflow: hidden;
    min-width: 0;
    transition: transform 0.25s ease, box-shadow 0.25s ease !important;
}
.match-card:hover {
    transform: translateY(-2px);
    border-color: rgba(0, 210, 106, 0.25) !important;
    box-shadow: 0 12px 40px rgba(0, 210, 106, 0.08) !important;
}
</style>
<!-- Premium Live ActionBoard score auto-update & neon animation system -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const matchId = "{{ $match['id'] }}";
    const homeTeamName = @json($homeTeam);
    const awayTeamName = @json($awayTeam);
    const totalMatchOvers = {{ $totalMatchOvers }};
    let lastScore = "{{ $scoreLine }}";
    let lastOvers = "{{ $overs }}";
    let isTransitioning = false;

    // Helper: classify event text into { code, cssClass }
    function classifyBall(text) {
        const t = (text || '').toUpperCase();
        if (t.includes('FOUR') || t.includes('BOUNDAR') || t === '4') return { code: '4', cls: 'ball-pill-crex--four' };
        if (t.includes('SIX') || t === '6') return { code: '6', cls: 'ball-pill-crex--six' };
        if (t.includes('WICKET') || t.includes('OUT') || t === 'W') return { code: 'W', cls: 'ball-pill-crex--wicket' };
        if (t.includes('WD') || t.includes('WIDE')) return { code: 'WD', cls: 'ball-pill-crex--extra' };
        if (t.includes('NB') || t.includes('NO BALL')) return { code: 'NB', cls: 'ball-pill-crex--extra' };
        if (t.includes('DOT') || t === '0' || t.includes('DOT BALL')) return { code: '0', cls: 'ball-pill-crex--dot' };
        const m = t.match(/(\d+)\s*RUN/);
        if (m) return { code: m[1], cls: 'ball-pill-crex--run' };
        return { code: (text || '').substring(0, 3), cls: 'ball-pill-crex--run' };
    }

    // Trigger overlay neon animation
    function triggerLiveAnimation(eventText) {
        const text = (eventText || '').toUpperCase();
        const overlay = document.getElementById('live-animation-overlay');
        if (!overlay) return;

        overlay.className = 'live-animation-overlay';
        overlay.innerHTML = '';

        let type = 'run';
        let label = eventText;

        if (text.includes('FOUR') || text.includes('BOUNDAR') || text === '4') { type = 'four'; label = 'FOUR!'; }
        else if (text.includes('SIX') || text === '6') { type = 'six'; label = 'SIX!'; }
        else if (text.includes('WICKET') || text.includes('OUT') || text === 'W') { type = 'wicket'; label = 'WICKET!'; }
        else if (text.includes('WD') || text.includes('WIDE')) { type = 'extra'; label = 'WIDE!'; }
        else if (text.includes('NB') || text.includes('NO BALL')) { type = 'extra'; label = 'NO BALL!'; }
        else if (text.includes('DOT') || text === '0') { type = 'dot'; label = 'DOT BALL'; }
        else {
            const matchRuns = text.match(/(\d+)\s*RUN/);
            if (matchRuns) { type = 'run'; label = '+' + matchRuns[1] + ' RUNS'; }
        }

        overlay.classList.add('is-active', `event-${type}`);

        const textEl = document.createElement('div');
        textEl.className = 'event-text';
        textEl.innerText = label;
        overlay.appendChild(textEl);

        const numParticles = type === 'wicket' ? 45 : (type === 'six' ? 35 : (type === 'four' ? 25 : 12));
        for (let i = 0; i < numParticles; i++) {
            const particle = document.createElement('span');
            particle.className = 'particle';
            const angle = Math.random() * 360;
            const distance = 40 + Math.random() * 140;
            const size = 3 + Math.random() * 6;
            const delay = Math.random() * 0.15;
            const duration = 0.6 + Math.random() * 0.6;
            const angleRad = (angle * Math.PI) / 180;
            const dx = Math.cos(angleRad) * distance;
            const dy = Math.sin(angleRad) * distance;
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.setProperty('--dx', `${dx.toFixed(2)}px`);
            particle.style.setProperty('--dy', `${dy.toFixed(2)}px`);
            particle.style.animationDelay = `${delay}s`;
            particle.style.animationDuration = `${duration}s`;
            overlay.appendChild(particle);
        }

        setTimeout(() => { overlay.classList.remove('is-active'); }, 3200);
    }

    // Dynamic auto-polling function
    async function pollLiveMatch() {
        if (isTransitioning) return;
        try {
            const res = await fetch(`/gamehub/actionboard/match/${matchId}/json`);
            if (!res.ok) return;
            const data = await res.json();

            const newScore = data.score_text || `${data.home_score}-${data.away_score}`;
            const newOvers = data.overs;

            // Only update on actual changes
            if (newScore !== lastScore || newOvers !== lastOvers) {
                isTransitioning = true;
                lastScore = newScore;
                lastOvers = newOvers;

                const timeline = Array.isArray(data.timeline) ? data.timeline : JSON.parse(data.timeline || '[]');
                const overSummary = Array.isArray(data.over_summary) ? data.over_summary : JSON.parse(data.over_summary || '[]');
                const latestEvent = timeline[0];

                // ── Recompute team scores & overs from over_summary ──
                const homeScore = parseInt(data.home_score) || 0;
                const awayScore = parseInt(data.away_score) || 0;
                let battingTeam = 'home';
                let homeWkts = 0, awayWkts = 0;
                let inn1Overs = 0, inn2Overs = 0;

                if (overSummary.length) {
                    const lastOvr = overSummary[overSummary.length - 1];
                    battingTeam = lastOvr.batting || 'home';
                    overSummary.forEach(o => {
                        const bat = o.batting || 'home';
                        if (bat === 'home') {
                            inn1Overs++;
                            if (Array.isArray(o.balls)) o.balls.forEach(b => { if (('' + b).toUpperCase().trim() === 'W') homeWkts++; });
                        } else {
                            inn2Overs++;
                            if (Array.isArray(o.balls)) o.balls.forEach(b => { if (('' + b).toUpperCase().trim() === 'W') awayWkts++; });
                        }
                    });
                }

                // Parse wickets from score_text if available
                const scoreWktMatch = (data.score_text || '').match(/-(\d+)/);
                if (scoreWktMatch) {
                    if (battingTeam === 'home') homeWkts = parseInt(scoreWktMatch[1]);
                    else awayWkts = parseInt(scoreWktMatch[1]);
                }

                const homeOversVal = battingTeam === 'home' ? newOvers : (inn1Overs + '.0');
                const awayOversVal = battingTeam === 'away' ? newOvers : (inn2Overs + '.0');

                // Calculate required runs and RRR
                let rrrVal = '-';
                let eqText = '';
                if ((data.status || '').toLowerCase() === 'live') {
                    if (battingTeam === 'away' && homeScore > 0) {
                        const target = homeScore + 1;
                        const runsNeeded = target - awayScore;
                        const oParts = ('' + newOvers).split('.');
                        const ballsBowled = (parseInt(oParts[0]) || 0) * 6 + (parseInt(oParts[1]) || 0);
                        const ballsRemaining = Math.max(0, totalMatchOvers * 6 - ballsBowled);
                        eqText = runsNeeded > 0 ? awayTeamName + ' need ' + runsNeeded + ' runs in ' + ballsRemaining + ' balls' : awayTeamName + ' won the match';
                        if (ballsRemaining > 0) {
                            rrrVal = ((runsNeeded / ballsRemaining) * 6).toFixed(2);
                        }
                    } else if (battingTeam === 'home' && awayScore > 0) {
                        const target = awayScore + 1;
                        const runsNeeded = target - homeScore;
                        const oParts = ('' + newOvers).split('.');
                        const ballsBowled = (parseInt(oParts[0]) || 0) * 6 + (parseInt(oParts[1]) || 0);
                        const ballsRemaining = Math.max(0, totalMatchOvers * 6 - ballsBowled);
                        eqText = runsNeeded > 0 ? homeTeamName + ' need ' + runsNeeded + ' runs in ' + ballsRemaining + ' balls' : homeTeamName + ' won the match';
                        if (ballsRemaining > 0) {
                            rrrVal = ((runsNeeded / ballsRemaining) * 6).toFixed(2);
                        }
                    } else {
                        eqText = data.decision || 'Match is Live';
                    }
                } else if ((data.status || '').toLowerCase() === 'completed') {
                    if (homeScore > awayScore) {
                        eqText = homeTeamName + ' won by ' + (homeScore - awayScore) + ' runs';
                    } else if (awayScore > homeScore) {
                        eqText = awayTeamName + ' won by ' + (awayScore - homeScore) + ' runs';
                    } else {
                        eqText = 'Match Tied';
                    }
                } else {
                    eqText = 'Match Scheduled';
                }

                // ── Update Broadcast Header Score Elements ──
                const heroScoreEl = document.getElementById('hero_score_num');
                const heroSubInfoEl = document.getElementById('hero_sub_info');

                const battingRuns = battingTeam === 'home' ? homeScore : awayScore;
                const battingWickets = battingTeam === 'home' ? homeWkts : awayWkts;
                const battingOvers = newOvers;

                if (heroScoreEl) {
                    heroScoreEl.innerHTML = `${battingRuns}-${battingWickets} <span class="overs-parentheses" id="hero_overs_parentheses">(${battingOvers} ov)</span>`;
                    heroScoreEl.classList.add('score-flash-green');
                    setTimeout(() => { heroScoreEl.classList.remove('score-flash-green'); }, 850);
                }

                if (heroSubInfoEl) {
                    if (battingTeam === 'home') {
                        heroSubInfoEl.innerHTML = `${awayTeamName} <span class="inactive-score-val">${awayScore > 0 ? awayScore + '-' + awayWkts : 'yet to bat'}</span>`;
                    } else {
                        heroSubInfoEl.innerHTML = `${homeTeamName} <span class="inactive-score-val">${homeScore > 0 ? homeScore + '-' + homeWkts : 'yet to bat'}</span>`;
                    }
                }
                
                // Update active versus team highlighting
                const homeTeamBlock = document.getElementById('home_team_block');
                const awayTeamBlock = document.getElementById('away_team_block');
                if (homeTeamBlock && awayTeamBlock) {
                    if (battingTeam === 'home') {
                        homeTeamBlock.classList.add('is-batting');
                        awayTeamBlock.classList.remove('is-batting');
                    } else {
                        awayTeamBlock.classList.add('is-batting');
                        homeTeamBlock.classList.remove('is-batting');
                    }
                }

                // ── Update Pressure and Stats ──
                const runsReqEl = document.getElementById('broadcast_runs_required_text');
                const crrValEl = document.getElementById('broadcast_crr_val');
                const rrrValEl = document.getElementById('broadcast_rrr_val');
                const tossDetailEl = document.getElementById('broadcast_toss_detail_text');

                if (runsReqEl) {
                    runsReqEl.innerText = eqText;
                    if (rrrVal !== '-') {
                        runsReqEl.style.color = ''; // reset
                    } else {
                        runsReqEl.style.color = '#34d399'; // green for innings 1
                    }
                }

                if (crrValEl) crrValEl.innerText = data.crr || '0.00';
                if (rrrValEl && rrrVal !== '-') rrrValEl.innerText = rrrVal;

                if (tossDetailEl) {
                    tossDetailEl.innerHTML = `<strong>Toss:</strong> ${data.decision || activeTeamName + ' won the toss'}`;
                }

                // ── Update Sticky Broadcast Scorebar ──
                const stickyScoreEl = document.getElementById('sticky_score_text');
                const stickyEqEl = document.getElementById('sticky_equation_text');

                const homeText = `${homeTeamName} ${homeScore}-${homeWkts} (${homeOversVal} ov)`;
                const awayText = `${awayTeamName} ${awayScore > 0 ? awayScore + '-' + awayWkts : 'yet to bat'}`;
                if (stickyScoreEl) stickyScoreEl.innerText = `${homeText} vs ${awayText}`;

                let stickyEq = '';
                if ((data.status || '').toLowerCase() === 'live') {
                    if (rrrVal !== '-') {
                        const target = homeScore > 0 ? homeScore + 1 : awayScore + 1;
                        const activeScore = battingTeam === 'home' ? homeScore : awayScore;
                        const runsNeeded = target - activeScore;
                        const oParts = ('' + newOvers).split('.');
                        const ballsBowled = (parseInt(oParts[0]) || 0) * 6 + (parseInt(oParts[1]) || 0);
                        const ballsRemaining = Math.max(0, totalMatchOvers * 6 - ballsBowled);
                        stickyEq = `Need ${runsNeeded} off ${ballsRemaining} (REQ ${rrrVal})`;
                    } else {
                        stickyEq = `CRR ${data.crr || '0.00'}`;
                    }
                } else {
                    stickyEq = eqText;
                }
                if (stickyEqEl) stickyEqEl.innerText = stickyEq;

                // ── Update Last Ball Badge ──
                if (latestEvent && latestEvent.text) {
                    const badgeEl = document.getElementById('broadcast_last_ball_badge');
                    const centerBallDiv = document.getElementById('broadcast_center_ball');
                    const { code, cls } = classifyBall(latestEvent.text);

                    const eventType = code.toLowerCase() === 'w' ? 'wicket' : (parseInt(code) === 4 ? 'four' : (parseInt(code) === 6 ? 'six' : 'run'));

                    if (badgeEl) {
                        badgeEl.className = `broadcast-big-ball-glow event-${eventType}`;
                        badgeEl.innerText = code;
                    } else if (centerBallDiv) {
                        centerBallDiv.innerHTML = `<div id="broadcast_last_ball_badge" class="broadcast-big-ball-glow event-${eventType}">${code}</div>`;
                    }

                    triggerLiveAnimation(latestEvent.text);
                }

                // Smoothly reload remaining page components after animation completes
                setTimeout(() => {
                    window.location.reload();
                }, 3100);
            }
        } catch (e) {
            console.error('Error fetching live score', e);
        }
    }

    // Poll every 1.5 seconds for instant response
    setInterval(pollLiveMatch, 1500);

    // Scroll listener for mobile sticky scorebar
    window.addEventListener('scroll', () => {
        if (window.scrollY > 120) {
            document.body.classList.add('scrolled-down');
        } else {
            document.body.classList.remove('scrolled-down');
        }
    });

    // Dynamic top bar sports rebranding overrides
    const brandTextStrong = document.querySelector('header.topbar .brand__text strong');
    const brandTextSmall = document.querySelector('header.topbar .brand__text small');
    if (brandTextStrong) {
        brandTextStrong.innerHTML = 'Haran Live';
    }
    if (brandTextSmall) {
        brandTextSmall.innerHTML = 'Andhra County League';
    }

    const topnavLinks = document.querySelectorAll('header.topbar .topnav__link');
    if (topnavLinks.length >= 3) {
        topnavLinks[0].innerHTML = 'Home';
        topnavLinks[1].innerHTML = 'Schedule';
        topnavLinks[2].innerHTML = 'GameHub <span class="nav-live-indicator" style="display:inline-block;width:6px;height:6px;background:#ef4444;border-radius:50%;margin-left:4px;box-shadow:0 0 6px #ef4444;vertical-align:middle;animation:activeDotBlink 1s infinite alternate;"></span>';
    }
});
</script>
@endsection
