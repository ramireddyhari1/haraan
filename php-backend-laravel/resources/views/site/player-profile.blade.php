@extends('site.layout')

@section('content')
@php
    // Career calculations
    $runs = $player->career_runs ?? 0;
    $balls = $player->career_balls ?? 0;
    $matches = $player->career_matches ?? 0;
    $wickets = $player->career_wickets ?? 0;
    $runsConceded = $player->career_runs_conceded ?? 0;

    $oversParts = explode('.', $player->career_overs_bowled ?? '0.0');
    $completedOvers = isset($oversParts[0]) ? (int)$oversParts[0] : 0;
    $ballsBowled = isset($oversParts[1]) ? (int)$oversParts[1] : 0;
    $totalBallsBowled = ($completedOvers * 6) + $ballsBowled;

    $battingSR = $balls > 0 ? number_format(($runs / $balls) * 100, 1) : '0.0';
    $battingAvg = $matches > 0 ? number_format($runs / $matches, 1) : '0.0';
    $bowlingEcon = $totalBallsBowled > 0 ? number_format(($runsConceded / $totalBallsBowled) * 6, 2) : '0.00';

    // Recent form scores
    $recentStats = \App\Models\PlayerMatchStat::where('player_id', $player->player_id)
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get();

    // Calculate dynamic SVG performance chart coordinates (Oldest to Newest)
    $inningsRuns = [];
    foreach ($recentStats as $st) {
        $inningsRuns[] = $st->runs ?? 0;
    }
    // Pad to 5 values if player has played fewer matches
    while (count($inningsRuns) < 5) {
        array_unshift($inningsRuns, 0);
    }
    $inningsRuns = array_reverse($inningsRuns); // Show oldest to newest
    
    $width = 460;
    $height = 130;
    $padding = 20;
    $maxVal = max(max($inningsRuns), 50); // Scale up to at least 50 runs
    $points = [];
    $areaPoints = [];
    
    // First point for closed area path
    $areaPoints[] = "$padding," . ($height - $padding);
    
    for ($i = 0; $i < 5; $i++) {
        $x = $padding + ($i * ($width - 2 * $padding) / 4);
        $y = $height - $padding - (($inningsRuns[$i] / $maxVal) * ($height - 2 * $padding));
        $points[] = "$x,$y";
        $areaPoints[] = "$x,$y";
    }
    $areaPoints[] = ($width - $padding) . "," . ($height - $padding);
    
    $pointsString = implode(' ', $points);
    $areaPointsString = implode(' ', $areaPoints);

    // Default Avatar seed
    $avatarSeed = urlencode($player->name);
    $avatarUrl = $player->avatar 
        ? asset('storage/' . $player->avatar) 
        : "https://api.dicebear.com/7.x/avataaars/svg?seed={$avatarSeed}&backgroundColor=0f172a";
@endphp

<div class="actionboard-root-theme">
    <!-- Custom Top Navigation Header (Desktop) -->
    <header class="actionboard-desktop-header">
        <div class="actionboard-header-container">
            <a href="{{ route('site.gamehub.actionboard') }}" class="actionboard-logo">
                <span class="logo-pulse"></span>
                ACTIONBOARD
            </a>
            
            <nav class="desktop-menu">
                <a href="{{ route('site.gamehub.actionboard') }}#home" class="menu-link">Home</a>
                <a href="{{ route('site.gamehub.actionboard') }}#live-center" class="menu-link">Live Center</a>
                <a href="{{ route('site.gamehub.actionboard') }}#series-center" class="menu-link">Series</a>
                <a href="{{ route('site.gamehub.actionboard') }}#teams-center" class="menu-link">Teams</a>
                <a href="{{ route('site.gamehub.actionboard') }}#players-center" class="menu-link active">Players</a>
                <a href="{{ route('site.gamehub.actionboard') }}#rankings-center" class="menu-link">Rankings</a>
                <a href="{{ route('site.gamehub.actionboard') }}#news-center" class="menu-link">News</a>
                <a href="{{ route('site.gamehub.actionboard') }}#stats-center" class="menu-link font-accent">Stats Corner</a>
            </nav>
            
            <div class="header-actions">
                <a href="{{ route('site.gamehub') }}" class="action-btn-secondary">Game Hub</a>
                @auth
                    <span class="player-badge">Player ID: #{{ auth()->user()->player_id }}</span>
                    <a href="{{ route('site.gamehub.actionboard.create') }}" class="btn-create-match">Create Match</a>
                @else
                    <button onclick="document.getElementById('loginBtn').click();" class="btn-create-match" style="border:none; cursor:pointer;">Create Match</button>
                @endauth
            </div>
        </div>
    </header>

    <!-- Custom Bottom Navigation (Mobile Only) -->
    <div class="actionboard-mobile-nav">
        <a class="nav-tab" href="{{ route('site.gamehub.actionboard') }}#home">
            <svg class="tab-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>Home</span>
        </a>
        <a class="nav-tab" href="{{ route('site.gamehub.actionboard') }}#live-center">
            <svg class="tab-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
            <span>Live</span>
        </a>
        <a class="nav-tab" href="{{ route('site.gamehub.actionboard') }}#series-center">
            <svg class="tab-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.67 4.41 4.96-.1.82-.16 1.66-.16 2.54 0 .32.02.63.04.94L3.18 19.3c-.39.39-.39 1.02 0 1.41.39.39 1.02.39 1.41 0L7.1 18.2c.86.53 1.83.8 2.9.8v3h4v-3c1.07 0 2.04-.27 2.9-.8l2.51 2.51c.39.39 1.02.39 1.41 0 .39-.39.39-1.02 0-1.41l-4.11-4.9c.02-.31.04-.62.04-.94 0-.88-.06-1.72-.16-2.54C19.08 12.67 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>
            <span>Series</span>
        </a>
        <a class="nav-tab active" href="{{ route('site.gamehub.actionboard') }}#players-center">
            <svg class="tab-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            <span>Players</span>
        </a>
        <a class="nav-tab" href="{{ route('site.gamehub.actionboard') }}#stats-center">
            <svg class="tab-icon" viewBox="0 0 24 24"><circle cx="6" cy="12" r="2" fill="currentColor"/><circle cx="12" cy="12" r="2" fill="currentColor"/><circle cx="18" cy="12" r="2" fill="currentColor"/></svg>
            <span>More</span>
        </a>
    </div>

    <!-- Profile Page Container -->
    <div class="actionboard-profile-main-container">
        <!-- Hero Header section -->
        <section class="player-hero-card">
            <div class="hero-left-col">
                <div class="avatar-holder">
                    <img src="{{ $avatarUrl }}" alt="{{ $player->name }}">
                    @if($player->career_runs > 500 || $player->career_wickets > 25)
                        <div class="verified-badge-star" title="ActionBoard Verified Legend">✓</div>
                    @endif
                </div>
                <div class="player-vital-meta">
                    <div class="title-row">
                        <h1>{{ $player->name }}</h1>
                        <span class="role-pill">{{ $player->player_role ?? 'All-Rounder' }}</span>
                    </div>
                    <p class="id-str">🏏 ID: <strong>{{ $player->player_id }}</strong> • 📍 {{ $player->district ?? 'Kadapa' }} District, {{ $player->state ?? 'Andhra Pradesh' }}</p>
                    
                    <!-- Ranks row -->
                    <div class="rank-badges-row">
                        <div class="rank-badge">
                            <span class="sub">District Rank</span>
                            <strong class="val val-emerald">#{{ $player->rank_district ?? '2' }}</strong>
                        </div>
                        <div class="rank-badge">
                            <span class="sub">State Rank</span>
                            <strong class="val val-blue">#{{ $player->rank_state ?? '12' }}</strong>
                        </div>
                        <div class="rank-badge">
                            <span class="sub">India Rank</span>
                            <strong class="val val-amber">#{{ $player->rank_country ?? '248' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="hero-right-col">
                <button class="btn-follow active" onclick="toggleFollow()">Following</button>
                <button class="btn-share" onclick="navigator.clipboard.writeText(window.location.href); alert('Player profile link copied!');">
                    <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92c0-1.61-1.31-2.92-2.92-2.92z"/></svg>
                    Share
                </button>
            </div>
        </section>

        <!-- Stats details responsive breakdown -->
        <div class="profile-two-column-layout">
            
            <!-- LEFT MAIN COL: Stats summary & performances -->
            <div class="profile-left-col">
                
                <!-- Career summaries cards grid -->
                <div class="stats-panel">
                    <h3>Career Stats Summary</h3>
                    
                    <div class="stats-counter-grid">
                        <div class="counter-card">
                            <span class="kicker">Matches</span>
                            <strong>{{ $matches }}</strong>
                        </div>
                        <div class="counter-card highlight-runs">
                            <span class="kicker">Runs Scored</span>
                            <strong>{{ $runs }}</strong>
                        </div>
                        <div class="counter-card highlight-sr">
                            <span class="kicker">Batting SR</span>
                            <strong>{{ $battingSR }}</strong>
                        </div>
                        <div class="counter-card">
                            <span class="kicker">Batting Avg</span>
                            <strong>{{ $battingAvg }}</strong>
                        </div>
                        <div class="counter-card highlight-wkts">
                            <span class="kicker">Wickets</span>
                            <strong>{{ $wickets }}</strong>
                        </div>
                        <div class="counter-card">
                            <span class="kicker">Bowling Economy</span>
                            <strong>{{ $bowlingEcon }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Player specifications table card -->
                <div class="stats-panel">
                    <h3>Player Specifications</h3>
                    <div class="specs-grid">
                        <div class="spec-cell">
                            <span class="tag">Batting Style</span>
                            <strong>{{ $player->batting_style ?? $player->playing_style ?? 'Right-hand bat' }}</strong>
                        </div>
                        <div class="spec-cell">
                            <span class="tag">Bowling Style</span>
                            <strong>{{ $player->bowling_style ?? 'Right-arm medium' }}</strong>
                        </div>
                        <div class="spec-cell">
                            <span class="tag">Primary Role</span>
                            <strong>{{ $player->player_role ?? 'All-Rounder' }}</strong>
                        </div>
                        <div class="spec-cell">
                            <span class="tag">Overs Bowled</span>
                            <strong>{{ $player->career_overs_bowled ?? '0.0' }} ov</strong>
                        </div>
                    </div>
                </div>

                <!-- Recent match performances list -->
                <div class="stats-panel">
                    <h3>Recent Match Performances</h3>
                    
                    @if(count($recentMatches) > 0)
                        <div class="performances-stack">
                            @foreach($recentMatches as $match)
                                <div class="perf-card">
                                    <div class="perf-header">
                                        <span class="comp">{{ $match->competition ?: 'Local Match' }}</span>
                                        <span class="status {{ strtolower($match->status) }}">{{ $match->status }}</span>
                                    </div>
                                    <div class="perf-body">
                                        <div class="teams">
                                            <h4>{{ $match->home }} <span class="vs">vs</span> {{ $match->away }}</h4>
                                            <p>Overs: {{ $match->overs }} | Venue: {{ $match->venue }}</p>
                                        </div>
                                        <div class="score">
                                            <strong>{{ $match->score_text ?? (($match->home_score ?? 0) . '-' . ($match->away_score ?? 0)) }}</strong>
                                            <a href="{{ route('site.gamehub.actionboard.match', ['id' => $match->id]) }}" class="link-match-center">View Match Center →</a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="no-stats-placeholder">
                            <p>No matches registered for this player yet.</p>
                        </div>
                    @endif
                </div>

            </div>

            <!-- RIGHT SIDE COL: Innings charts & Form -->
            <div class="profile-right-col">
                
                <!-- dynamic SVG innings runs trend chart -->
                <div class="stats-panel">
                    <h3>Performance Innings Trend</h3>
                    <p class="chart-desc">Runs scored across last 5 matches</p>
                    
                    <div class="chart-container-box">
                        <svg class="performance-chart-svg" viewBox="0 0 {{ $width }} {{ $height }}">
                            <defs>
                                <linearGradient id="chartGlow" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#00D26A" stop-opacity="0.3"/>
                                    <stop offset="100%" stop-color="#00D26A" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>
                            
                            <!-- Grid lines -->
                            <line x1="{{ $padding }}" y1="{{ $padding }}" x2="{{ $width - $padding }}" y2="{{ $padding }}" stroke="rgba(0,0,0,0.05)" stroke-width="1" />
                            <line x1="{{ $padding }}" y1="{{ ($height / 2) }}" x2="{{ $width - $padding }}" y2="{{ ($height / 2) }}" stroke="rgba(0,0,0,0.05)" stroke-width="1" />
                            <line x1="{{ $padding }}" y1="{{ $height - $padding }}" x2="{{ $width - $padding }}" y2="{{ $height - $padding }}" stroke="rgba(0,0,0,0.1)" stroke-width="1" />
                            
                            <!-- Filled Area Path -->
                            <polygon points="{{ $areaPointsString }}" fill="url(#chartGlow)" />
                            
                            <!-- Line Path -->
                            <polyline points="{{ $pointsString }}" fill="none" stroke="#00D26A" stroke-width="2.5" stroke-linecap="round" />
                            
                            <!-- Value marker dots & labels -->
                            @for($i = 0; $i < 5; $i++)
                                @php
                                    $x = $padding + ($i * ($width - 2 * $padding) / 4);
                                    $y = $height - $padding - (($inningsRuns[$i] / $maxVal) * ($height - 2 * $padding));
                                @endphp
                                <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#FFFFFF" stroke="#00D26A" stroke-width="2" />
                                @if($inningsRuns[$i] > 0)
                                    <text x="{{ $x }}" y="{{ $y - 8 }}" fill="#00D26A" font-size="9" font-weight="900" text-anchor="middle">{{ $inningsRuns[$i] }}</text>
                                @endif
                            @endfor
                        </svg>
                        <div class="chart-xaxis">
                            <span>M-5</span>
                            <span>M-4</span>
                            <span>M-3</span>
                            <span>M-2</span>
                            <span>M-1</span>
                        </div>
                    </div>
                </div>

                <!-- Form stats checklist (Last 5 Innings) -->
                <div class="stats-panel">
                    <h3>Recent Form (Last 5 Match Details)</h3>
                    
                    @if($recentStats->count() > 0)
                        <div class="form-rows-stack">
                            @foreach($recentStats as $st)
                                <div class="form-row">
                                    <div class="m-meta">
                                        <span class="m-id">Match #{{ $st->match_id }}</span>
                                        <strong>{{ $st->runs }} runs <small>({{ $st->balls }} balls)</small></strong>
                                    </div>
                                    <div class="m-wkts">
                                        @if($st->wickets > 0)
                                            <span class="wkt-pill">{{ $st->wickets }} Wkt</span>
                                        @else
                                            <span class="dot-pill">No Wkts</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="no-form-placeholder">
                            <p>Recent innings statistics logs appear once a match ends.</p>
                        </div>
                    @endif
                </div>

            </div>

        </div>
    </div>
</div>

<style>
/* -----------------------------------------------------------------------------
   ACTIONBOARD PROFILE OVERRIDES
   ----------------------------------------------------------------------------- */
header.topbar {
    display: none !important;
}
main.container {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Base Styles */
.actionboard-root-theme {
    background-color: #F3F4F6 !important;
    color: #1E293B !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    min-height: 100vh;
    padding-bottom: 80px;
    overflow-x: hidden;
}

/* Desktop Header Navigation (DO NOT CHANGE - REMAINS DARK) */
.actionboard-desktop-header {
    position: sticky;
    top: 0;
    left: 0;
    right: 0;
    background: #0F172A;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
.actionboard-header-container {
    max-width: 1280px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    padding: 0 24px;
}
.actionboard-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 20px;
    font-weight: 900;
    letter-spacing: 1px;
    color: #FFFFFF;
    text-decoration: none;
}
.logo-pulse {
    width: 10px;
    height: 10px;
    background: #00D26A;
    border-radius: 50%;
    box-shadow: 0 0 0 3px rgba(0, 210, 106, 0.25);
    animation: livePulseDot 2s infinite ease-in-out;
}
@keyframes livePulseDot {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 210, 106, 0.5); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(0, 210, 106, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 210, 106, 0); }
}

.desktop-menu {
    display: flex;
    gap: 20px;
}
.menu-link {
    color: #94A3B8;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
}
.menu-link:hover, .menu-link.active {
    color: #FFFFFF;
    background: rgba(255, 255, 255, 0.05);
}
.menu-link.font-accent {
    color: #00D26A;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}
.action-btn-secondary {
    background: rgba(255, 255, 255, 0.06);
    color: #FFFFFF;
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}
.action-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
}
.btn-create-match {
    background: linear-gradient(135deg, #00D26A 0%, #22C55E 100%);
    color: #000000;
    font-weight: 800;
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 13px;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0, 210, 106, 0.2);
    transition: all 0.25s ease;
}
.btn-create-match:hover {
    transform: translateY(-1.5px);
    box-shadow: 0 6px 16px rgba(0, 210, 106, 0.35);
}
.player-badge {
    background: rgba(0, 210, 106, 0.08);
    border: 1px solid rgba(0, 210, 106, 0.18);
    color: #00D26A;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

/* Custom Bottom Navigation (Mobile Only - DO NOT CHANGE - REMAINS DARK) */
.actionboard-mobile-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 64px;
    background: #0F172A;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    z-index: 1001;
    justify-content: space-around;
    align-items: center;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
}
.nav-tab {
    background: none;
    border: none;
    outline: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    color: #94A3B8;
    cursor: pointer;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    width: 20%;
    text-decoration: none;
    transition: all 0.2s ease;
}
.nav-tab.active {
    color: #00D26A;
}
.tab-icon {
    width: 22px;
    height: 22px;
}

/* Main Profile Layout wrapper */
.actionboard-profile-main-container {
    max-width: 960px;
    margin: 24px auto;
    padding: 0 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Player Hero Section details */
.player-hero-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}
.hero-left-col {
    display: flex;
    align-items: center;
    gap: 24px;
}
.avatar-holder {
    position: relative;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #F1F5F9;
    border: 3px solid #FFFFFF;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.avatar-holder img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.verified-badge-star {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #3B82F6;
    border: 2px solid #FFFFFF;
    color: #FFFFFF;
    font-size: 10px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

.player-vital-meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.title-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.title-row h1 {
    font-size: 24px;
    font-weight: 900;
    color: #0F172A;
    margin: 0;
}
.role-pill {
    background: rgba(0, 210, 106, 0.08);
    border: 1px solid rgba(0, 210, 106, 0.25);
    color: #00D26A;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: uppercase;
}
.id-str {
    font-size: 12px;
    color: #64748B;
    margin: 0;
}
.id-str strong {
    color: #0F172A;
}

.rank-badges-row {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}
.rank-badge {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 6px 12px;
    display: flex;
    flex-direction: column;
}
.rank-badge .sub {
    font-size: 8px;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
}
.rank-badge .val {
    font-size: 14px;
    font-weight: 900;
}
.val-emerald { color: #00D26A; }
.val-blue { color: #2563EB; }
.val-amber { color: #D97706; }

.hero-right-col {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.btn-follow {
    background: #00D26A;
    color: #000000;
    border: none;
    font-weight: 800;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s ease;
}
.btn-follow.active {
    background: #F1F5F9;
    color: #0f172a;
    border: 1px solid #E2E8F0;
}
.btn-share {
    background: none;
    border: 1px solid #E2E8F0;
    color: #0F172A;
    font-weight: 700;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.btn-share:hover {
    background: #F8FAFC;
}

/* Two-column splits below hero */
.profile-two-column-layout {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 24px;
}
.profile-left-col, .profile-right-col {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.stats-panel {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.stats-panel h3 {
    font-size: 14px;
    font-weight: 900;
    text-transform: uppercase;
    color: #0F172A;
    margin: 0 0 16px;
    letter-spacing: 0.5px;
    border-left: 3px solid #00D26A;
    padding-left: 8px;
}

.stats-counter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
}
.counter-card {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}
.counter-card .kicker {
    display: block;
    font-size: 10px;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.counter-card strong {
    font-size: 20px;
    font-weight: 900;
    color: #0F172A;
}
.counter-card.highlight-runs strong { color: #00D26A; }
.counter-card.highlight-sr strong { color: #2563EB; }
.counter-card.highlight-wkts strong { color: #FF4D6D; }

.specs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.spec-cell {
    display: flex;
    flex-direction: column;
}
.spec-cell .tag {
    font-size: 10px;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.spec-cell strong {
    font-size: 13px;
    color: #0F172A;
    font-weight: 750;
}

.performances-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.perf-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.perf-header {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.perf-header .comp { color: #64748B; }
.perf-header .status.live { color: #00D26A; }
.perf-header .status.scheduled { color: #2563EB; }
.perf-header .status { color: #64748B; }

.perf-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.perf-body .teams h4 {
    font-size: 14px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 4px;
}
.perf-body .teams .vs {
    color: #94A3B8;
}
.perf-body .teams p {
    font-size: 11px;
    color: #64748B;
    margin: 0;
}
.perf-body .score {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
.perf-body .score strong {
    font-size: 14px;
    font-weight: 900;
    color: #00D26A;
}
.link-match-center {
    font-size: 11px;
    font-weight: 700;
    color: #2563EB;
    text-decoration: none;
    margin-top: 4px;
}

/* SVG Chart Container details */
.chart-desc {
    font-size: 11px;
    color: #64748B;
    margin: -8px 0 16px 0;
}
.chart-container-box {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 16px;
}
.performance-chart-svg {
    width: 100%;
    height: auto;
    overflow: visible;
}
.chart-xaxis {
    display: flex;
    justify-content: space-between;
    padding: 8px 10px 0 10px;
    font-size: 10px;
    color: #64748B;
    font-weight: 700;
}

.form-rows-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.form-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 8px;
}
.form-row .m-meta {
    display: flex;
    flex-direction: column;
}
.form-row .m-meta .m-id {
    font-size: 9px;
    color: #64748B;
    font-weight: 700;
}
.form-row .m-meta strong {
    font-size: 12px;
    color: #0F172A;
}
.form-row .m-meta strong small {
    color: #64748B;
    font-weight: 500;
}
.wkt-pill {
    background: rgba(255, 77, 109, 0.08);
    border: 1px solid rgba(255, 77, 109, 0.18);
    color: #FF4D6D;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 20px;
}
.dot-pill {
    font-size: 11px;
    color: #64748B;
}

.no-stats-placeholder, .no-form-placeholder {
    text-align: center;
    padding: 24px;
    border: 1px dashed #E2E8F0;
    border-radius: 8px;
    color: #64748B;
    font-size: 12px;
}

/* -----------------------------------------------------------------------------
   RESPONSIVE LAYOUT SCALING FOR PROFILE
   ----------------------------------------------------------------------------- */
@media (max-width: 1024px) {
    .actionboard-desktop-header {
        display: none !important;
    }
    .actionboard-mobile-nav {
        display: flex;
    }
    .actionboard-profile-main-container {
        padding: 0 16px;
        margin: 16px auto;
    }
}

@media (max-width: 768px) {
    .player-hero-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .hero-left-col {
        flex-direction: column;
        align-items: center;
    }
    .title-row {
        justify-content: center;
    }
    .rank-badges-row {
        justify-content: center;
    }
    .hero-right-col {
        flex-direction: row;
        width: 100%;
        justify-content: center;
    }
    .profile-two-column-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Client toggle logic -->
<script>
    function toggleFollow() {
        const btn = document.querySelector('.btn-follow');
        if (btn.innerText === 'Following') {
            btn.innerText = 'Follow';
            btn.classList.remove('active');
        } else {
            btn.innerText = 'Following';
            btn.classList.add('active');
        }
    }
</script>
