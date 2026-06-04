@extends('site.layout')

@section('content')
@php
    $featuredMatch = $activeMatch ?? ($matches[0] ?? null);
    $liveMatches = array_values(array_filter($matches, fn ($match) => strcasecmp($match['status'], 'Live') === 0));
    $upcomingMatches = array_values(array_filter($matches, fn ($match) => strcasecmp($match['status'], 'Scheduled') === 0));
    $recentMatches = array_values(array_filter($matches, fn ($match) => strcasecmp($match['status'], 'Live') !== 0 && strcasecmp($match['status'], 'Scheduled') !== 0));
    
    $liveCount = count($liveMatches);
    $upcomingCount = count($upcomingMatches);
    $recentCount = count($recentMatches);
    $headlineMatch = $matches[0] ?? null;

    // Custom stats parser to extract live wickets/overs from summary & text
    $getMatchStats = function($m) {
        if (!$m) return null;
        $homeScore = $m['home_score'] ?? 0;
        $awayScore = $m['away_score'] ?? 0;
        $overs = $m['overs'] ?? '0.0';
        $scoreText = $m['score_text'] ?? '';
        
        $overSummary = isset($m['over_summary']) 
            ? (is_array($m['over_summary']) ? $m['over_summary'] : (json_decode($m['over_summary'], true) ?: []))
            : [];
            
        $lastOver = !empty($overSummary) ? end($overSummary) : null;
        $battingTeam = isset($lastOver['batting']) ? $lastOver['batting'] : 'home';
        
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
        
        $wicketsCount = 0;
        if (preg_match('/-(\d+)/', $scoreText, $matchWkts)) {
            $wicketsCount = (int)$matchWkts[1];
        } else {
            $wicketsCount = ($battingTeam === 'home') ? $homeSummaryWickets : $awaySummaryWickets;
        }
        
        $homeWkts = ($battingTeam === 'home') ? $wicketsCount : $homeSummaryWickets;
        $awayWkts = ($battingTeam === 'away') ? $wicketsCount : $awaySummaryWickets;
        
        $homeOversVal = ($battingTeam === 'home') ? $overs : ($innings1OversCount . '.0');
        $awayOversVal = ($battingTeam === 'away') ? $overs : ($innings2OversCount . '.0');
        
        $isLive = strcasecmp($m['status'], 'Live') === 0;
        $isScheduled = strcasecmp($m['status'], 'Scheduled') === 0;
        
        if ($isScheduled) {
            return [
                'home_runs' => '--',
                'home_wickets' => '',
                'home_overs' => '',
                'away_runs' => '--',
                'away_wickets' => '',
                'away_overs' => '',
                'batting_team' => 'none',
                'is_live' => false,
                'is_scheduled' => true,
                'home_display' => '--',
                'away_display' => '--',
                'home_score_only' => '--',
                'away_score_only' => '--'
            ];
        }
        
        return [
            'home_runs' => $homeScore,
            'home_wickets' => '/' . $homeWkts,
            'home_overs' => '(' . $homeOversVal . ' ov)',
            'away_runs' => $awayScore,
            'away_wickets' => '/' . $awayWkts,
            'away_overs' => '(' . $awayOversVal . ' ov)',
            'batting_team' => $battingTeam,
            'is_live' => $isLive,
            'is_scheduled' => $isScheduled,
            'home_display' => "$homeScore/$homeWkts ($homeOversVal ov)",
            'away_display' => "$awayScore/$awayWkts ($awayOversVal ov)",
            'home_score_only' => "$homeScore/$homeWkts",
            'away_score_only' => "$awayScore/$awayWkts"
        ];
    };
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
                <a href="#home" class="menu-link active" onclick="navigateSection('home')">Home</a>
                <a href="#live-center" class="menu-link" onclick="navigateSection('live')">Live Center</a>
                <a href="#series-center" class="menu-link" onclick="navigateSection('series')">Series</a>
                <a href="#teams-center" class="menu-link" onclick="navigateSection('teams')">Teams</a>
                <a href="#players-center" class="menu-link" onclick="navigateSection('players')">Players</a>
                <a href="#rankings-center" class="menu-link" onclick="navigateSection('rankings')">Rankings</a>
                <a href="#news-center" class="menu-link" onclick="navigateSection('news')">News</a>
                <a href="#stats-center" class="menu-link font-accent" onclick="navigateSection('stats')">Stats Corner</a>
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
        <button class="nav-tab active" id="tab-btn-home" onclick="switchMobileTab('home')">
            <svg class="tab-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            <span>Home</span>
        </button>
        <button class="nav-tab" id="tab-btn-live" onclick="switchMobileTab('live')">
            <div style="position: relative; display: inline-block;">
                <svg class="tab-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                @if($liveCount > 0)
                    <span class="mobile-live-badge-dot"></span>
                @endif
            </div>
            <span>Live</span>
        </button>
        <button class="nav-tab" id="tab-btn-series" onclick="switchMobileTab('series')">
            <svg class="tab-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.67 4.41 4.96-.1.82-.16 1.66-.16 2.54 0 .32.02.63.04.94L3.18 19.3c-.39.39-.39 1.02 0 1.41.39.39 1.02.39 1.41 0L7.1 18.2c.86.53 1.83.8 2.9.8v3h4v-3c1.07 0 2.04-.27 2.9-.8l2.51 2.51c.39.39 1.02.39 1.41 0 .39-.39.39-1.02 0-1.41l-4.11-4.9c.02-.31.04-.62.04-.94 0-.88-.06-1.72-.16-2.54C19.08 12.67 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>
            <span>Series</span>
        </button>
        <button class="nav-tab" id="tab-btn-players" onclick="switchMobileTab('players')">
            <svg class="tab-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            <span>Players</span>
        </button>
        <button class="nav-tab" id="tab-btn-more" onclick="switchMobileTab('more')">
            <svg class="tab-icon" viewBox="0 0 24 24"><circle cx="6" cy="12" r="2" fill="currentColor"/><circle cx="12" cy="12" r="2" fill="currentColor"/><circle cx="18" cy="12" r="2" fill="currentColor"/></svg>
            <span>More</span>
        </button>
    </div>

    <!-- Main Dynamic View Container -->
    <div class="actionboard-dashboard-container">
        <!-- 3-Column Responsive Grid Layout -->
        <div class="actionboard-three-col-layout">
            
            <!-- LEFT SIDEBAR: Match Selector (Desktop Only) -->
            <aside class="actionboard-col-left">
                <div class="sidebar-sticky-panel">
                    <div class="sidebar-header">
                        <h2>Matches Center</h2>
                        <div class="sidebar-status-toggles">
                            <button class="status-tab active" onclick="filterLeftMatches('all')">All</button>
                            <button class="status-tab" onclick="filterLeftMatches('live')">Live <span class="badge-live-dot"></span></button>
                            <button class="status-tab" onclick="filterLeftMatches('scheduled')">Upcoming</button>
                        </div>
                    </div>

                    <div class="sidebar-matches-list scrollbar-custom">
                        @if(count($matches) > 0)
                            @foreach ($matches as $match)
                                @php $stats = $getMatchStats($match); @endphp
                                <a href="{{ route('site.gamehub.actionboard.match', ['id' => $match['id']]) }}" class="sidebar-match-card {{ strtolower($match['status']) }}" data-status="{{ strtolower($match['status']) }}">
                                    <div class="card-meta">
                                        <span class="tournament-name">{{ $match['competition'] ?: 'Local Friendly Match' }}</span>
                                        @if(strcasecmp($match['status'], 'Live') === 0)
                                            <span class="live-tag">LIVE <span class="live-pulse-dot"></span></span>
                                        @else
                                            <span class="status-badge">{{ $match['status'] }}</span>
                                        @endif
                                    </div>
                                    <div class="card-teams">
                                        <div class="team-row {{ $stats['batting_team'] === 'home' && $stats['is_live'] ? 'batting-active' : '' }}">
                                            <span class="team-lbl">
                                                {{ $match['home'] }}
                                                @if($stats['batting_team'] === 'home' && $stats['is_live'])
                                                    <span class="live-bat-dot">🏏</span>
                                                @endif
                                            </span>
                                            <strong class="team-score">
                                                <span class="runs-wkts">{{ $stats['home_runs'] }}{{ $stats['home_wickets'] }}</span>
                                                @if($stats['is_live'] && !$stats['is_scheduled'])
                                                    <small class="overs-str">{{ $stats['home_overs'] }}</small>
                                                @endif
                                            </strong>
                                        </div>
                                        <div class="team-row {{ $stats['batting_team'] === 'away' && $stats['is_live'] ? 'batting-active' : '' }}">
                                            <span class="team-lbl">
                                                {{ $match['away'] }}
                                                @if($stats['batting_team'] === 'away' && $stats['is_live'])
                                                    <span class="live-bat-dot">🏏</span>
                                                @endif
                                            </span>
                                            <strong class="team-score">
                                                <span class="runs-wkts">{{ $stats['away_runs'] }}{{ $stats['away_wickets'] }}</span>
                                                @if($stats['is_live'] && !$stats['is_scheduled'])
                                                    <small class="overs-str">{{ $stats['away_overs'] }}</small>
                                                @endif
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <span class="equation">{{ $match['score_text'] ?: 'Click to view scorecard' }}</span>
                                    </div>
                                </a>
                            @endforeach
                        @else
                            <div class="no-matches-found">
                                <p>No matches registered currently.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </aside>

            <!-- CENTER CONTENT: Main feed / Carousels / News / District Module -->
            <main class="actionboard-col-center">
                <!-- Mobile Only Tab Sections Wrapper -->
                <div class="mobile-sections-wrapper">
                    
                    <!-- TAB SECTION 1: HOME & MATCH CENTER -->
                    <div class="mobile-tab-section active" id="section-home">
                        <!-- Live Match Carousel -->
                        <section class="actionboard-carousel-sec">
                            <div class="section-title-row">
                                <h2>Trending Matches</h2>
                                <span class="carousel-indicators-lbl">Swipe for more</span>
                            </div>
                            <div class="carousel-track-wrapper scrollbar-custom">
                                @if(count($matches) > 0)
                                    @foreach($matches as $match)
                                        @php $stats = $getMatchStats($match); @endphp
                                        <a href="{{ route('site.gamehub.actionboard.match', ['id' => $match['id']]) }}" class="carousel-match-card">
                                            <div class="carousel-card-header">
                                                <span class="comp-badge">{{ $match['competition'] ?: 'Cricket Match' }}</span>
                                                @if(strcasecmp($match['status'], 'Live') === 0)
                                                    <span class="live-pill"><span class="pulse"></span> LIVE</span>
                                                @else
                                                    <span class="upcoming-pill">{{ $match['status'] }}</span>
                                                @endif
                                            </div>
                                            <div class="carousel-card-body">
                                                <div class="carousel-team {{ $stats['batting_team'] === 'home' && $stats['is_live'] ? 'batting-active' : '' }}">
                                                    <span class="team-name">
                                                        {{ $match['home'] }}
                                                        @if($stats['batting_team'] === 'home' && $stats['is_live'])
                                                            <span class="live-bat-dot">🏏</span>
                                                        @endif
                                                    </span>
                                                    <span class="team-score">
                                                        <span class="runs-wkts">{{ $stats['home_runs'] }}{{ $stats['home_wickets'] }}</span>
                                                        @if($stats['is_live'] && !$stats['is_scheduled'])
                                                            <small class="overs-str">{{ $stats['home_overs'] }}</small>
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="vs-divider">VS</div>
                                                <div class="carousel-team {{ $stats['batting_team'] === 'away' && $stats['is_live'] ? 'batting-active' : '' }}">
                                                    <span class="team-name">
                                                        {{ $match['away'] }}
                                                        @if($stats['batting_team'] === 'away' && $stats['is_live'])
                                                            <span class="live-bat-dot">🏏</span>
                                                        @endif
                                                    </span>
                                                    <span class="team-score">
                                                        <span class="runs-wkts">{{ $stats['away_runs'] }}{{ $stats['away_wickets'] }}</span>
                                                        @if($stats['is_live'] && !$stats['is_scheduled'])
                                                            <small class="overs-str">{{ $stats['away_overs'] }}</small>
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="carousel-card-footer">
                                                <p>{{ $match['score_text'] ?: 'Tap to view live analysis' }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                @else
                                    <div class="carousel-placeholder-card">
                                        <p>No matches available today.</p>
                                    </div>
                                @endif
                            </div>
                        </section>

                        <!-- Featured Match Large Banner (CREX Rhythm) -->
                        @if($featuredMatch)
                            @php $heroStats = $getMatchStats($featuredMatch); @endphp
                            <section class="featured-match-hero">
                                <div class="hero-header">
                                    <span class="hero-kicker">FEATURED MATCH CENTER</span>
                                    @if(strcasecmp($featuredMatch['status'], 'Live') === 0)
                                        <span class="live-indicator-glow">LIVE ANALYSIS</span>
                                    @endif
                                </div>
                                <div class="hero-match-box">
                                    <div class="hero-team-details {{ $heroStats['batting_team'] === 'home' && $heroStats['is_live'] ? 'batting-active' : '' }}">
                                        <h3>
                                            {{ $featuredMatch['home'] }}
                                            @if($heroStats['batting_team'] === 'home' && $heroStats['is_live'])
                                                <span class="live-bat-dot">🏏</span>
                                            @endif
                                        </h3>
                                        <span class="score-display">
                                            <span class="runs-wkts">{{ $heroStats['home_runs'] }}{{ $heroStats['home_wickets'] }}</span>
                                            @if($heroStats['is_live'] && !$heroStats['is_scheduled'])
                                                <small class="overs-str">{{ $heroStats['home_overs'] }}</small>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="hero-vs">VS</div>
                                    <div class="hero-team-details {{ $heroStats['batting_team'] === 'away' && $heroStats['is_live'] ? 'batting-active' : '' }}">
                                        <h3>
                                            {{ $featuredMatch['away'] }}
                                            @if($heroStats['batting_team'] === 'away' && $heroStats['is_live'])
                                                <span class="live-bat-dot">🏏</span>
                                            @endif
                                        </h3>
                                        <span class="score-display">
                                            <span class="runs-wkts">{{ $heroStats['away_runs'] }}{{ $heroStats['away_wickets'] }}</span>
                                            @if($heroStats['is_live'] && !$heroStats['is_scheduled'])
                                                <small class="overs-str">{{ $heroStats['away_overs'] }}</small>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="hero-summary-row">
                                    <span class="meta"><i class="icon">🏆</i> {{ $featuredMatch['competition'] ?: 'Actionboard Premier League' }}</span>
                                    <span class="meta"><i class="icon">📍</i> {{ $featuredMatch['venue'] }}</span>
                                </div>
                                <div class="hero-equations-strip">
                                    <span class="equation-txt">{{ $featuredMatch['score_text'] ?: 'Interactive live scoring and wagon wheel loading' }}</span>
                                    <a href="{{ route('site.gamehub.actionboard.match', ['id' => $featuredMatch['id']]) }}" class="btn-open-match">Open Live Center →</a>
                                </div>
                            </section>
                        @endif

                        <!-- Unique Feature: District Cricket Module Dashboard -->
                        <section class="district-cricket-module">
                            <div class="district-header">
                                <div class="icon-brand-box">
                                    <svg class="district-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/></svg>
                                    <h3>District Cricket Center</h3>
                                </div>
                                <span class="badge-district-accent">ACTIONBOARD HYPERLOCAL</span>
                            </div>
                            
                            <div class="district-tabs">
                                <button class="dist-tab-btn active" onclick="switchDistrictTab('dist-leaders')">Leaderboard</button>
                                <button class="dist-tab-btn" onclick="switchDistrictTab('dist-tournaments')">Tournaments</button>
                                <button class="dist-tab-btn" onclick="switchDistrictTab('dist-players')">Local Talents</button>
                                <button class="dist-tab-btn" onclick="switchDistrictTab('dist-stats')">District Stats</button>
                            </div>

                            <div class="district-content-panels">
                                <!-- Panel 1: Leaderboard -->
                                <div class="dist-panel active" id="dist-panel-dist-leaders">
                                    <div class="dist-table-responsive">
                                        <table class="dist-data-table">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Player</th>
                                                    <th>District</th>
                                                    <th>Runs</th>
                                                    <th>Wickets</th>
                                                    <th>Avg</th>
                                                    <th>Econ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><span class="rank-pos num-1">1</span></td>
                                                    <td><strong>R. Hari</strong></td>
                                                    <td>Kadapa</td>
                                                    <td class="text-green">482</td>
                                                    <td>12</td>
                                                    <td>68.8</td>
                                                    <td>6.4</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="rank-pos num-2">2</span></td>
                                                    <td><strong>P. Naidu</strong></td>
                                                    <td>Chittoor</td>
                                                    <td class="text-green">410</td>
                                                    <td>8</td>
                                                    <td>51.2</td>
                                                    <td>7.2</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="rank-pos num-3">3</span></td>
                                                    <td><strong>K. Reddy</strong></td>
                                                    <td>Kurnool</td>
                                                    <td class="text-green">385</td>
                                                    <td>19</td>
                                                    <td>42.7</td>
                                                    <td>5.8</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="rank-pos">4</span></td>
                                                    <td><strong>S. Khan</strong></td>
                                                    <td>Anantapur</td>
                                                    <td class="text-green">340</td>
                                                    <td>15</td>
                                                    <td>37.8</td>
                                                    <td>6.1</td>
                                                </tr>
                                                <tr>
                                                    <td><span class="rank-pos">5</span></td>
                                                    <td><strong>M. Prasad</strong></td>
                                                    <td>Nellore</td>
                                                    <td class="text-green">298</td>
                                                    <td>22</td>
                                                    <td>29.8</td>
                                                    <td>4.9</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Panel 2: Tournaments -->
                                <div class="dist-panel" id="dist-panel-dist-tournaments">
                                    <div class="local-tournaments-list">
                                        <div class="tournament-item">
                                            <div class="t-badge active">ONGOING</div>
                                            <div class="t-details">
                                                <h4>Kadapa District T20 Championship</h4>
                                                <p>12 Local Clubs • Matches played daily at District Stadium</p>
                                            </div>
                                            <span class="t-date">May - June</span>
                                        </div>
                                        <div class="tournament-item">
                                            <div class="t-badge active">ONGOING</div>
                                            <div class="t-details">
                                                <h4>Rayalaseema State Selection Cup</h4>
                                                <p>Knockout stages underway • Dynamic Live stats tracking</p>
                                            </div>
                                            <span class="t-date">May 28 - Jun 10</span>
                                        </div>
                                        <div class="tournament-item">
                                            <div class="t-badge upcoming">UPCOMING</div>
                                            <div class="t-details">
                                                <h4>Nellore Inter-District Invitation League</h4>
                                                <p>Registrations open for certified players ID</p>
                                            </div>
                                            <span class="t-date">Starts Jun 15</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Panel 3: Local Talents -->
                                <div class="dist-panel" id="dist-panel-dist-players">
                                    <div class="local-talents-grid">
                                        <div class="talent-card">
                                            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Hari&backgroundColor=0f172a" alt="Player">
                                            <div class="talent-info">
                                                <h4>R. Hari</h4>
                                                <span>All-Rounder (Kadapa)</span>
                                                <div class="form-stars">Form: ★★★★★</div>
                                            </div>
                                        </div>
                                        <div class="talent-card">
                                            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Kiran&backgroundColor=0f172a" alt="Player">
                                            <div class="talent-info">
                                                <h4>K. Reddy</h4>
                                                <span>Bowler (Kurnool)</span>
                                                <div class="form-stars">Form: ★★★★☆</div>
                                            </div>
                                        </div>
                                        <div class="talent-card">
                                            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Naidu&backgroundColor=0f172a" alt="Player">
                                            <div class="talent-info">
                                                <h4>P. Naidu</h4>
                                                <span>Batter (Chittoor)</span>
                                                <div class="form-stars">Form: ★★★★☆</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Panel 4: District Stats -->
                                <div class="dist-panel" id="dist-panel-dist-stats">
                                    <div class="dist-stats-snapshot">
                                        <div class="stat-box">
                                            <span class="lbl">Registered Players</span>
                                            <strong class="val">1,248</strong>
                                        </div>
                                        <div class="stat-box">
                                            <span class="lbl">Active Tournaments</span>
                                            <strong class="val">14</strong>
                                        </div>
                                        <div class="stat-box">
                                            <span class="lbl">Matches Logged</span>
                                            <strong class="val">3,892</strong>
                                        </div>
                                        <div class="stat-box">
                                            <span class="lbl">Total Boundary Fours</span>
                                            <strong class="val">8,410</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Cricket Editorials & Trending News -->
                        <section class="news-list-section" id="news-center">
                            <h3 class="section-title">Cricket Editorial & Highlights</h3>
                            <div class="news-masonry">
                                <article class="lead-news-card">
                                    <div class="news-banner" style="background-image: url('https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=800&q=80');">
                                        <span class="tag-badge">Match Report</span>
                                    </div>
                                    <div class="news-content">
                                        <h3>How Hyperlocal Scoring is transforming Grassroots Cricket</h3>
                                        <p>Operators across municipal stadiums are synchronizing wickets and boundaries live using the Actionboard mobile console. Real-time graphs are updated automatically for every local cup.</p>
                                        <div class="news-meta">
                                            <span>Actionboard Media</span>
                                            <span>•</span>
                                            <span>Active Live</span>
                                        </div>
                                    </div>
                                </article>

                                <div class="news-cards-stack">
                                    <article class="stack-news-item">
                                        <span class="news-category text-accent">Pitch Report</span>
                                        <h4>District Stadium Pitch conditions for evening clash</h4>
                                        <p>Dry surface with high spinner assistance. Average first innings score is 154 runs.</p>
                                    </article>
                                    <article class="stack-news-item">
                                        <span class="news-category text-accent">Tournament News</span>
                                        <h4>Actionboard Premier Cup registrations close next Friday</h4>
                                        <p>All teams must submit verified Player IDs and state registration credentials.</p>
                                    </article>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- TAB SECTION 2: LIVE CENTER -->
                    <div class="mobile-tab-section" id="section-live">
                        <section class="matches-list-section">
                            <h3 class="section-title">Live Score Center</h3>
                            <div class="list-container">
                                @if($liveCount > 0)
                                    @foreach($liveMatches as $match)
                                        <a href="{{ route('site.gamehub.actionboard.match', ['id' => $match['id']]) }}" class="match-list-item live">
                                            <div class="item-header">
                                                <span class="league">{{ $match['competition'] }}</span>
                                                <span class="badge-live"><span class="pulse"></span> LIVE</span>
                                            </div>
                                            <div class="item-body">
                                                @php $stats = $getMatchStats($match); @endphp
                                                <div class="team-line {{ $stats['batting_team'] === 'home' && $stats['is_live'] ? 'batting-active' : '' }}">
                                                    <span>
                                                        {{ $match['home'] }}
                                                        @if($stats['batting_team'] === 'home' && $stats['is_live'])
                                                            <span class="live-bat-dot">🏏</span>
                                                        @endif
                                                    </span>
                                                    <strong class="team-score">
                                                        <span class="runs-wkts">{{ $stats['home_runs'] }}{{ $stats['home_wickets'] }}</span>
                                                        @if($stats['is_live'] && !$stats['is_scheduled'])
                                                            <small class="overs-str">{{ $stats['home_overs'] }}</small>
                                                        @endif
                                                    </strong>
                                                </div>
                                                <div class="team-line {{ $stats['batting_team'] === 'away' && $stats['is_live'] ? 'batting-active' : '' }}">
                                                    <span>
                                                        {{ $match['away'] }}
                                                        @if($stats['batting_team'] === 'away' && $stats['is_live'])
                                                            <span class="live-bat-dot">🏏</span>
                                                        @endif
                                                    </span>
                                                    <strong class="team-score">
                                                        <span class="runs-wkts">{{ $stats['away_runs'] }}{{ $stats['away_wickets'] }}</span>
                                                        @if($stats['is_live'] && !$stats['is_scheduled'])
                                                            <small class="overs-str">{{ $stats['away_overs'] }}</small>
                                                        @endif
                                                    </strong>
                                                </div>
                                            </div>
                                            <div class="item-footer">
                                                <span class="eq">{{ $match['score_text'] }}</span>
                                            </div>
                                        </a>
                                    @endforeach
                                @else
                                    <div class="no-data-card">
                                        <p>No active live matches found at this moment.</p>
                                    </div>
                                @endif
                            </div>
                        </section>
                    </div>

                    <!-- TAB SECTION 3: SERIES & STANDINGS -->
                    <div class="mobile-tab-section" id="section-series">
                        <section class="series-section" id="series-center">
                            <h3 class="section-title">Active Tournaments & Cup Series</h3>
                            <div class="series-cards-grid">
                                <div class="series-card">
                                    <div class="card-lead">
                                        <h4>Rayalaseema Premier Selection Cup</h4>
                                        <span class="active-badge">ACTIVE</span>
                                    </div>
                                    <p>Fixtures: 24 Matches | Venues: 3 District Stadiums</p>
                                    <div class="quick-stats-row">
                                        <div>Top Batsman: <strong>R. Hari (254 runs)</strong></div>
                                        <div>Top Bowler: <strong>K. Reddy (11 wkts)</strong></div>
                                    </div>
                                </div>
                                <div class="series-card">
                                    <div class="card-lead">
                                        <h4>Kadapa Division A Championship</h4>
                                        <span class="active-badge">ACTIVE</span>
                                    </div>
                                    <p>Fixtures: 18 Matches | Venues: Central Arena Turf</p>
                                    <div class="quick-stats-row">
                                        <div>Top Batsman: <strong>P. Naidu (198 runs)</strong></div>
                                        <div>Top Bowler: <strong>M. Prasad (9 wkts)</strong></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- TAB SECTION 4: PLAYERS & RANKINGS -->
                    <div class="mobile-tab-section" id="section-players">
                        <section class="players-rankings-section" id="players-center">
                            <h3 class="section-title">Actionboard Local Rankings</h3>
                            <div class="rankings-box">
                                <div class="rankings-header-tabs">
                                    <button class="rank-tab-btn active" onclick="switchRankingsTab('batting')">Batting</button>
                                    <button class="rank-tab-btn" onclick="switchRankingsTab('bowling')">Bowling</button>
                                    <button class="rank-tab-btn" onclick="switchRankingsTab('allrounder')">All-Rounders</button>
                                </div>

                                <div class="rankings-lists">
                                    <div class="rank-sub-panel active" id="rank-panel-batting">
                                        <div class="rank-item-row">
                                            <span class="num">1</span>
                                            <div class="p-info">
                                                <strong>R. Hari</strong>
                                                <span>Kadapa • 824 pts</span>
                                            </div>
                                            <span class="rating-val">CRR 78.4</span>
                                        </div>
                                        <div class="rank-item-row">
                                            <span class="num">2</span>
                                            <div class="p-info">
                                                <strong>P. Naidu</strong>
                                                <span>Chittoor • 780 pts</span>
                                            </div>
                                            <span class="rating-val">CRR 69.2</span>
                                        </div>
                                        <div class="rank-item-row">
                                            <span class="num">3</span>
                                            <div class="p-info">
                                                <strong>S. Khan</strong>
                                                <span>Anantapur • 712 pts</span>
                                            </div>
                                            <span class="rating-val">CRR 61.5</span>
                                        </div>
                                    </div>
                                    <div class="rank-sub-panel" id="rank-panel-bowling">
                                        <div class="rank-item-row">
                                            <span class="num">1</span>
                                            <div class="p-info">
                                                <strong>K. Reddy</strong>
                                                <span>Kurnool • 840 pts</span>
                                            </div>
                                            <span class="rating-val">Econ 5.80</span>
                                        </div>
                                        <div class="rank-item-row">
                                            <span class="num">2</span>
                                            <div class="p-info">
                                                <strong>M. Prasad</strong>
                                                <span>Nellore • 795 pts</span>
                                            </div>
                                            <span class="rating-val">Econ 4.90</span>
                                        </div>
                                    </div>
                                    <div class="rank-sub-panel" id="rank-panel-allrounder">
                                        <div class="rank-item-row">
                                            <span class="num">1</span>
                                            <div class="p-info">
                                                <strong>R. Hari</strong>
                                                <span>Kadapa • 420 pts</span>
                                            </div>
                                            <span class="rating-val">Form 9.4</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- TAB SECTION 5: MORE & STATS -->
                    <div class="mobile-tab-section" id="section-more">
                        <section class="more-options-section">
                            <h3 class="section-title">Actionboard Scoring Utilities</h3>
                            <div class="utilities-grid">
                                @auth
                                    <div class="util-card">
                                        <h4>Operator Actions</h4>
                                        <p>Create matches, update statistics, and verify referee logs.</p>
                                        <a href="{{ route('site.gamehub.actionboard.create') }}" class="btn-create-match-block">Create A New Match Room</a>
                                    </div>
                                @else
                                    <div class="util-card">
                                        <h4>Login Required</h4>
                                        <p>Please login to register matches and manage real-time updates.</p>
                                        <button onclick="document.getElementById('loginBtn').click();" class="btn-create-match-block" style="border:none; cursor:pointer;">Login Now</button>
                                    </div>
                                @endauth

                                <div class="util-card" id="stats-center">
                                    <h4>Stats Corner Summary</h4>
                                    <div class="stat-rows-stack">
                                        <div class="s-row">
                                            <span>Average Powerplay Runs</span>
                                            <strong>54.2 runs</strong>
                                        </div>
                                        <div class="s-row">
                                            <span>Wicket Fall Percentages</span>
                                            <strong>Caught: 62% • Bowled: 18%</strong>
                                        </div>
                                        <div class="s-row">
                                            <span>Toss Impact (Win %)</span>
                                            <strong>Bat First Win: 54%</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                </div>
            </main>

            <!-- RIGHT SIDEBAR: Rankings / Tickers / Advertisements (Desktop Only) -->
            <aside class="actionboard-col-right">
                <!-- Live Stats Snapshot -->
                <section class="side-panel">
                    <div class="side-panel-header">
                        <h3>Stats Corner</h3>
                    </div>
                    <div class="side-stats-list">
                        <div class="s-row">
                            <span>Current Run Rate (CRR)</span>
                            <strong class="text-green">7.64 rpo</strong>
                        </div>
                        <div class="s-row">
                            <span>Avg Required Run Rate</span>
                            <strong class="text-accent">8.95 rpo</strong>
                        </div>
                        <div class="s-row">
                            <span>Boundaries Logged (4s/6s)</span>
                            <strong>14 fours • 6 sixes</strong>
                        </div>
                        <div class="s-row">
                            <span>Wickets Conceded</span>
                            <strong class="text-red">12 wkts (This tournament)</strong>
                        </div>
                    </div>
                </section>

                <!-- Desktop Rankings Widget -->
                <section class="side-panel" id="rankings-center">
                    <div class="side-panel-header">
                        <h3>ICC & Local Rankings</h3>
                    </div>
                    <div class="rankings-box">
                        <div class="rankings-header-tabs">
                            <button class="rank-tab-btn active" id="desk-r-tab-batting" onclick="switchRankingsTab('batting')">Batting</button>
                            <button class="rank-tab-btn" id="desk-r-tab-bowling" onclick="switchRankingsTab('bowling')">Bowling</button>
                        </div>
                        <div class="rankings-lists" style="margin-top: 12px;">
                            <div class="rank-sub-panel active" id="desk-rank-panel-batting">
                                <div class="rank-item-row">
                                    <span class="num">1</span>
                                    <div class="p-info">
                                        <strong>R. Hari</strong>
                                        <span>Kadapa • 824 pts</span>
                                    </div>
                                </div>
                                <div class="rank-item-row">
                                    <span class="num">2</span>
                                    <div class="p-info">
                                        <strong>P. Naidu</strong>
                                        <span>Chittoor • 780 pts</span>
                                    </div>
                                </div>
                                <div class="rank-item-row">
                                    <span class="num">3</span>
                                    <div class="p-info">
                                        <strong>S. Khan</strong>
                                        <span>Anantapur • 712 pts</span>
                                    </div>
                                </div>
                            </div>
                            <div class="rank-sub-panel" id="desk-rank-panel-bowling">
                                <div class="rank-item-row">
                                    <span class="num">1</span>
                                    <div class="p-info">
                                        <strong>K. Reddy</strong>
                                        <span>Kurnool • 840 pts</span>
                                    </div>
                                </div>
                                <div class="rank-item-row">
                                    <span class="num">2</span>
                                    <div class="p-info">
                                        <strong>M. Prasad</strong>
                                        <span>Nellore • 795 pts</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Advertisements placeholder (CREX rhythm) -->
                <section class="sponsor-ad-card">
                    <span class="ad-tag">SPONSOR</span>
                    <div class="ad-content">
                        <h4>ACTIONBOARD PREMIER CUP</h4>
                        <p>Powered by Haran Sports-tech platform. Experience real-time live scoring like never before.</p>
                        <div class="ad-mock-btn">Register Team</div>
                    </div>
                </section>
            </aside>

        </div>
    </div>
</div>

<style>
/* -----------------------------------------------------------------------------
   ACTIONBOARD CUSTOM DESIGN SYSTEM OVERRIDES (CREX MOBILE & DESKTOP ALIGNMENT)
   ----------------------------------------------------------------------------- */

/* Immediate parent overrides to go full-screen */
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

/* Custom Scrollbars */
.scrollbar-custom::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
.scrollbar-custom::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.02);
}
.scrollbar-custom::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.12);
    border-radius: 4px;
}
.scrollbar-custom::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 210, 106, 0.5);
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
    transition: all 0.2s ease;
}
.nav-tab.active {
    color: #00D26A;
}
.tab-icon {
    width: 22px;
    height: 22px;
}
.mobile-live-badge-dot {
    position: absolute;
    top: 0;
    right: 0;
    width: 8px;
    height: 8px;
    background: #FF4D6D;
    border-radius: 50%;
    box-shadow: 0 0 0 2px #0F172A;
    animation: livePulseDot 1.5s infinite ease-in-out;
}
@keyframes livePulseDot {
    0% { transform: scale(0.9); opacity: 0.6; }
    50% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(0.9); opacity: 0.6; }
}

/* 3-Column Layout Container */
.actionboard-dashboard-container {
    max-width: 1280px;
    margin: 24px auto;
    padding: 0 24px;
}
.actionboard-three-col-layout {
    display: grid;
    grid-template-columns: 290px 1fr 290px;
    gap: 20px;
    align-items: start;
}

/* Left Sidebar Styles */
.actionboard-col-left {
    position: sticky;
    top: 88px;
    min-width: 0;
}
.sidebar-sticky-panel {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}
.sidebar-header h2 {
    font-size: 16px;
    font-weight: 900;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #0F172A;
    margin: 0 0 16px;
}
.sidebar-status-toggles {
    display: flex;
    gap: 8px;
    background: #F1F5F9;
    padding: 4px;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
}
.status-tab {
    background: none;
    border: none;
    color: #64748B;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    flex: 1;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.status-tab.active {
    background: #FFFFFF;
    color: #00D26A;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.badge-live-dot {
    width: 6px;
    height: 6px;
    background: #FF4D6D;
    border-radius: 50%;
}

.sidebar-matches-list {
    max-height: calc(100vh - 250px);
    overflow-y: auto;
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-right: 4px;
}
.sidebar-match-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.sidebar-match-card:hover {
    background: #F8FAFC;
    border-color: rgba(0, 210, 106, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.sidebar-match-card.live {
    border-left: 3px solid #00D26A;
}
.card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
}
.tournament-name {
    color: #64748B;
    font-weight: 700;
    text-transform: uppercase;
}
.live-tag {
    color: #00D26A;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 4px;
}
.live-pulse-dot {
    width: 6px;
    height: 6px;
    background: #00D26A;
    border-radius: 50%;
    animation: livePulseDot 1s infinite ease-in-out;
}
.status-badge {
    background: #F1F5F9;
    color: #64748B;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
}

.card-teams {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.team-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.team-lbl {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    display: flex;
    align-items: center;
}
.batting-active .team-lbl {
    color: #0F172A !important;
}
.team-score {
    font-size: 13px;
    font-weight: 800;
    display: flex;
    align-items: center;
}
.runs-wkts {
    color: #0F172A;
    font-weight: 800;
}
.batting-active .runs-wkts {
    color: #00D26A !important; /* Active batting team score in emerald green */
}
.overs-str {
    color: #64748B !important; /* Muted grey for overs */
    font-size: 10px;
    font-weight: 600;
    margin-left: 4px;
}
.live-bat-dot {
    font-size: 10px;
    margin-left: 4px;
}

.card-footer {
    border-top: 1px dashed #E2E8F0;
    padding-top: 8px;
    font-size: 11px;
}
.equation {
    color: #475569;
    font-weight: 600;
}

/* Center Feed Column */
.actionboard-col-center {
    display: flex;
    flex-direction: column;
    gap: 24px;
    min-width: 0;
}

/* Live Carousel Track Section */
.actionboard-carousel-sec {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}
.section-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.section-title-row h2 {
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #0F172A;
}
.carousel-indicators-lbl {
    font-size: 11px;
    color: #64748B;
    font-weight: 600;
}
.carousel-track-wrapper {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding-bottom: 8px;
}
.carousel-match-card {
    min-width: 280px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 12px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 12px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.carousel-match-card:hover {
    background: #F8FAFC;
    border-color: #00D26A;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.carousel-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
}
.comp-badge {
    color: #64748B;
    font-weight: 800;
    text-transform: uppercase;
}
.live-pill {
    background: rgba(0, 210, 106, 0.08);
    color: #00D26A;
    border: 1px solid rgba(0, 210, 106, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 4px;
}
.upcoming-pill {
    background: #F1F5F9;
    color: #64748B;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
}

.carousel-card-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.carousel-team {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.carousel-team .team-name {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    display: flex;
    align-items: center;
}
.carousel-team.batting-active .team-name {
    color: #0F172A !important;
}
.carousel-team .team-score {
    font-size: 13px;
    font-weight: 800;
    display: flex;
    align-items: center;
}
.vs-divider {
    font-size: 10px;
    font-weight: 800;
    color: #94A3B8;
    text-align: center;
}

.carousel-card-footer {
    border-top: 1px solid #F1F5F9;
    padding-top: 8px;
    font-size: 11px;
    color: #64748B;
}

/* Featured Hero Match Card */
.featured-match-hero {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}
.hero-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 12px;
    margin-bottom: 18px;
}
.hero-kicker {
    font-size: 11px;
    font-weight: 900;
    color: #00D26A;
    letter-spacing: 1px;
}
.live-indicator-glow {
    background: rgba(255, 77, 109, 0.08);
    color: #FF4D6D;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid rgba(255, 77, 109, 0.18);
}

.hero-match-box {
    display: flex;
    justify-content: space-around;
    align-items: center;
    margin-bottom: 20px;
}
.hero-team-details {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.hero-team-details h3 {
    font-size: 18px;
    font-weight: 900;
    color: #475569;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.hero-team-details.batting-active h3 {
    color: #0F172A !important;
}
.score-display {
    font-size: 24px;
    font-weight: 900;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.score-display .runs-wkts {
    font-size: 24px;
    font-weight: 900;
    color: #0F172A;
}
.hero-team-details.batting-active .score-display .runs-wkts {
    color: #00D26A !important; /* Active batting team score in emerald green */
}
.score-display .overs-str {
    font-size: 12px;
    color: #64748B !important; /* Muted grey for overs */
    font-weight: 600;
    display: block;
    margin-top: 4px;
    margin-left: 0;
}
.hero-vs {
    font-size: 16px;
    font-weight: 900;
    color: #94A3B8;
}

.hero-summary-row {
    display: flex;
    justify-content: center;
    gap: 24px;
    background: #F8FAFC;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
    margin-bottom: 18px;
}
.hero-summary-row .meta {
    font-size: 12px;
    color: #475569;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
}

.hero-equations-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.equation-txt {
    font-size: 13px;
    font-weight: 700;
    color: #00D26A;
}
.btn-open-match {
    background: #F1F5F9;
    color: #0F172A;
    border: 1px solid #E2E8F0;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    transition: all 0.2s ease;
}
.btn-open-match:hover {
    background: #00D26A;
    color: #000000;
    border-color: #00D26A;
}

/* Unique Feature: District Cricket Module Dashboard */
.district-cricket-module {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}
.district-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 12px;
    margin-bottom: 14px;
}
.icon-brand-box {
    display: flex;
    align-items: center;
    gap: 8px;
}
.district-icon {
    width: 20px;
    height: 20px;
    color: #00D26A;
}
.district-header h3 {
    font-size: 15px;
    font-weight: 900;
    color: #0F172A;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-district-accent {
    background: rgba(0, 210, 106, 0.08);
    border: 1px solid rgba(0, 210, 106, 0.18);
    color: #00D26A;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 4px;
}

.district-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    background: #F1F5F9;
    padding: 4px;
    border-radius: 8px;
    margin-bottom: 16px;
    border: 1px solid #E2E8F0;
}
.dist-tab-btn {
    background: none;
    border: none;
    color: #64748B;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
}
.dist-tab-btn.active {
    background: #FFFFFF;
    color: #00D26A;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.dist-panel {
    display: none;
}
.dist-panel.active {
    display: block;
}

.dist-table-responsive {
    overflow-x: auto;
}
.dist-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.dist-data-table th {
    background: #F8FAFC;
    color: #64748B;
    font-weight: 700;
    text-align: left;
    padding: 8px 12px;
    border-bottom: 1px solid #E2E8F0;
    text-transform: uppercase;
    font-size: 11px;
}
.dist-data-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #F1F5F9;
    color: #334155;
}
.rank-pos {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 900;
    background: #F1F5F9;
    color: #64748B;
}
.rank-pos.num-1 { background: rgba(0, 210, 106, 0.15); color: #00D26a; }
.rank-pos.num-2 { background: rgba(59, 130, 246, 0.15); color: #2563EB; }
.rank-pos.num-3 { background: rgba(245, 158, 11, 0.15); color: #D97706; }
.text-green { color: #00D26A !important; font-weight: 750; }

.local-tournaments-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.tournament-item {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.t-badge {
    font-size: 9px;
    font-weight: 800;
    padding: 3px 6px;
    border-radius: 4px;
}
.t-badge.active { background: rgba(0, 210, 106, 0.08); color: #00D26A; }
.t-badge.upcoming { background: #F1F5F9; color: #64748B; }
.t-details h4 {
    font-size: 13px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 4px;
}
.t-details p {
    font-size: 11px;
    color: #64748B;
    margin: 0;
}
.t-date {
    font-size: 11px;
    font-weight: 700;
    color: #334155;
    white-space: nowrap;
}

.local-talents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}
.talent-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.talent-card img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 1.5px solid rgba(0, 210, 106, 0.3);
}
.talent-info h4 {
    font-size: 13px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 2px;
}
.talent-info span {
    font-size: 10px;
    color: #64748B;
    display: block;
}
.form-stars {
    font-size: 9px;
    color: #F59E0B;
    margin-top: 4px;
}

.dist-stats-snapshot {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
}
.stat-box {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}
.stat-box .lbl {
    display: block;
    font-size: 10px;
    font-weight: 800;
    color: #64748B;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.stat-box .val {
    font-size: 18px;
    font-weight: 900;
    color: #00D26A;
}

/* Editorial Highlights News Section */
.news-list-section {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}
.section-title {
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #0F172A;
    margin: 0 0 16px;
    border-left: 3px solid #00D26A;
    padding-left: 8px;
}
.news-masonry {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 16px;
}
.lead-news-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.news-banner {
    height: 180px;
    background-size: cover;
    background-position: center;
    position: relative;
}
.tag-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #00D26A;
    color: #000000;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    padding: 4px 8px;
    border-radius: 4px;
}
.news-content {
    padding: 14px;
}
.news-content h3 {
    font-size: 15px;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.4;
    margin: 0 0 8px;
}
.news-content p {
    font-size: 12px;
    color: #475569;
    line-height: 1.5;
    margin: 0 0 12px;
}
.news-meta {
    font-size: 10px;
    color: #64748B;
    font-weight: 600;
    display: flex;
    gap: 6px;
}

.news-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.stack-news-item {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
}
.news-category {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 6px;
    display: block;
}
.text-accent { color: #00D26A; }
.stack-news-item h4 {
    font-size: 13px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 4px;
    line-height: 1.3;
}
.stack-news-item p {
    font-size: 11px;
    color: #475569;
    margin: 0;
}

/* Right Sidebar Column */
.actionboard-col-right {
    position: sticky;
    top: 88px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    min-width: 0;
}
.side-panel {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}
.side-panel-header {
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 10px;
    margin-bottom: 12px;
}
.side-panel-header h3 {
    font-size: 14px;
    font-weight: 900;
    text-transform: uppercase;
    color: #0F172A;
    margin: 0;
    letter-spacing: 0.5px;
}
.side-stats-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.s-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px dashed #F1F5F9;
    padding-bottom: 8px;
    font-size: 12px;
}
.s-row span {
    color: #64748B;
    font-weight: 600;
}
.s-row strong {
    color: #0F172A;
    font-weight: 800;
}
.text-red { color: #FF4D6D !important; }

/* Rankings Styles */
.rankings-header-tabs {
    display: flex;
    gap: 6px;
    background: #F1F5F9;
    padding: 3px;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
}
.rank-tab-btn {
    background: none;
    border: none;
    color: #64748B;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    flex: 1;
    text-align: center;
    transition: all 0.2s ease;
}
.rank-tab-btn.active {
    background: #FFFFFF;
    color: #00D26A;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.rank-sub-panel {
    display: none;
}
.rank-sub-panel.active {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.rank-item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 0;
    border-bottom: 1px solid #F1F5F9;
}
.rank-item-row .num {
    width: 18px;
    height: 18px;
    font-size: 10px;
    font-weight: 900;
    color: #00D26A;
    background: rgba(0, 210, 106, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.rank-item-row .p-info {
    display: flex;
    flex-direction: column;
}
.rank-item-row .p-info strong {
    font-size: 12px;
    color: #0F172A;
}
.rank-item-row .p-info span {
    font-size: 10px;
    color: #64748B;
}
.rating-val {
    margin-left: auto;
    font-size: 11px;
    font-weight: 800;
    color: #00D26A;
}

/* Sponsor ad banner */
.sponsor-ad-card {
    background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.ad-tag {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #F1F5F9;
    color: #64748B;
    font-size: 8px;
    font-weight: 800;
    padding: 2px 4px;
    border-radius: 2px;
    letter-spacing: 0.5px;
}
.ad-content h4 {
    font-size: 13px;
    font-weight: 900;
    color: #00D26A;
    margin: 0 0 6px;
    letter-spacing: 0.5px;
}
.ad-content p {
    font-size: 11px;
    color: #64748B;
    line-height: 1.4;
    margin: 0 0 12px;
}
.ad-mock-btn {
    background: #0F172A;
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 800;
    text-align: center;
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.ad-mock-btn:hover {
    background: #00D26A;
    color: #000000;
}

/* Mobile Live & List Specifics */
.match-list-item {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 14px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.match-list-item.live {
    border-left: 3px solid #00D26A;
    background: #FAFEFA;
}
.item-header {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #64748B;
    font-weight: 800;
    text-transform: uppercase;
}
.badge-live {
    color: #FF4D6D;
    display: flex;
    align-items: center;
    gap: 4px;
}
.badge-live .pulse {
    width: 6px;
    height: 6px;
    background: #FF4D6D;
    border-radius: 50%;
    animation: livePulseDot 1.2s infinite ease-in-out;
}
.item-body {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.team-line {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #64748B;
    font-weight: 700;
}
.team-line.batting-active {
    color: #0F172A !important;
}
.item-footer {
    border-top: 1px dashed #E2E8F0;
    padding-top: 6px;
    font-size: 11px;
}
.item-footer .eq {
    color: #334155;
    font-weight: 600;
}

.series-cards-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.series-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.card-lead {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.card-lead h4 {
    font-size: 14px;
    font-weight: 800;
    color: #0F172A;
    margin: 0;
}
.active-badge {
    background: rgba(0, 210, 106, 0.08);
    color: #00D26A;
    font-size: 9px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
}
.series-card p {
    font-size: 12px;
    color: #64748B;
    margin: 0 0 10px;
}
.quick-stats-row {
    border-top: 1px solid #F1F5F9;
    padding-top: 8px;
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #334155;
}

.util-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.util-card h4 {
    font-size: 13px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 6px;
}
.util-card p {
    font-size: 11px;
    color: #64748B;
    line-height: 1.4;
    margin: 0 0 12px;
}
.btn-create-match-block {
    display: block;
    background: #00D26A;
    color: #000000;
    font-weight: 850;
    text-align: center;
    padding: 10px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 12px;
}
.stat-rows-stack {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Dynamic Display Logic Toggles */
.mobile-tab-section {
    display: block;
}

/* -----------------------------------------------------------------------------
   RESPONSIVE LAYOUT SCALING
   ----------------------------------------------------------------------------- */
@media (min-width: 1025px) {
    /* Ensure left column is filtered in JS without hiding normal list items */
    .sidebar-match-card.hidden {
        display: none !important;
    }
}

@media (max-width: 1024px) {
    .actionboard-three-col-layout {
        grid-template-columns: 1fr;
    }
    .actionboard-col-left, .actionboard-col-right {
        display: none !important;
    }
    .actionboard-desktop-header {
        display: none !important;
    }
    .actionboard-mobile-nav {
        display: flex;
    }
    .actionboard-dashboard-container {
        padding: 0 16px;
        margin: 16px auto;
    }
    .news-masonry {
        grid-template-columns: 1fr;
    }
    
    /* Toggle-based display for tabs */
    .mobile-tab-section {
        display: none;
    }
    .mobile-tab-section.active {
        display: block;
    }
}
</style>

<!-- Custom Actionboard Client-side Logic (Tabs, Filtering, and Carousels) -->
<script>
    // Tab switching for Mobile Viewports
    function switchMobileTab(tabName) {
        // Select all tab buttons and panels
        const tabButtons = document.querySelectorAll('.actionboard-mobile-nav .nav-tab');
        const sections = document.querySelectorAll('.mobile-tab-section');

        // Deactivate all
        tabButtons.forEach(btn => btn.classList.remove('active'));
        sections.forEach(sec => sec.classList.remove('active'));

        // Activate targeted
        const targetBtn = document.getElementById(`tab-btn-${tabName}`);
        const targetSec = document.getElementById(`section-${tabName}`);

        if (targetBtn) targetBtn.classList.add('active');
        if (targetSec) targetSec.classList.add('active');

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Navigation trigger for Desktop link lists
    function navigateSection(tabName) {
        // If we are in mobile viewport, sync tabs
        if (window.innerWidth <= 1024) {
            switchMobileTab(tabName);
            return;
        }

        // Handle active indicators in menu-links
        const links = document.querySelectorAll('.desktop-menu .menu-link');
        links.forEach(l => l.classList.remove('active'));

        event.target.classList.add('active');

        // Scroll into view
        const element = document.getElementById(`${tabName}-center`);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Sidebar match filter logic
    function filterLeftMatches(status) {
        const tabs = document.querySelectorAll('.sidebar-status-toggles .status-tab');
        tabs.forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');

        const cards = document.querySelectorAll('.sidebar-matches-list .sidebar-match-card');
        cards.forEach(card => {
            const cardStatus = card.getAttribute('data-status');
            if (status === 'all') {
                card.classList.remove('hidden');
            } else if (status === 'live' && cardStatus === 'live') {
                card.classList.remove('hidden');
            } else if (status === 'scheduled' && cardStatus === 'scheduled') {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    // District Cricket Module Toggle Tabs
    function switchDistrictTab(tabId) {
        const buttons = document.querySelectorAll('.district-tabs .dist-tab-btn');
        const panels = document.querySelectorAll('.district-content-panels .dist-panel');

        buttons.forEach(btn => btn.classList.remove('active'));
        panels.forEach(p => p.classList.remove('active'));

        event.target.classList.add('active');
        const activePanel = document.getElementById(`dist-panel-${tabId}`);
        if (activePanel) activePanel.classList.add('active');
    }

    // Rankings lists tabs switcher
    function switchRankingsTab(rankType) {
        // Sidebar rankings triggers
        const deskButtons = document.querySelectorAll('#rankings-center .rank-tab-btn');
        const deskPanels = document.querySelectorAll('#rankings-center .rank-sub-panel');

        if (deskButtons.length > 0) {
            deskButtons.forEach(btn => btn.classList.remove('active'));
            deskPanels.forEach(p => p.classList.remove('active'));
            
            const btn = document.getElementById(`desk-r-tab-${rankType}`);
            const panel = document.getElementById(`desk-rank-panel-${rankType}`);
            if (btn) btn.classList.add('active');
            if (panel) panel.classList.add('active');
        }

        // Mobile rankings triggers
        const mobButtons = document.querySelectorAll('#players-center .rank-tab-btn');
        const mobPanels = document.querySelectorAll('#players-center .rank-sub-panel');
        if (mobButtons.length > 0) {
            mobButtons.forEach(btn => {
                if (btn.innerText.toLowerCase().includes(rankType.substring(0, 3))) {
                    mobButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                }
            });

            mobPanels.forEach(p => p.classList.remove('active'));
            const mobPanel = document.getElementById(`rank-panel-${rankType}`);
            if (mobPanel) mobPanel.classList.add('active');
        }
    }
</script>
@endsection
