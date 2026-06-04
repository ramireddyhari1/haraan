@extends('site.layout')

@section('content')
@php
    $resolvePlayerName = function($nameOrId) use ($match) {
        if (empty($nameOrId)) return '';
        $squads = array_merge($match->home_squad ?? [], $match->away_squad ?? []);
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
<div class="actionboard-page">
    <div class="container">
        <header class="actionboard-shell">
            <div class="actionboard-shell__top">
                <div class="actionboard-brand">⚡ Match Control Panel</div>
                <div class="actionboard-shell__actions">
                    <span style="font-weight: 700; color: #fbbf24; background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 4px; font-size: 13px;">{{ $match->home }} vs {{ $match->away }}</span>
                    <a href="{{ route('site.gamehub.actionboard.match', ['id' => $match->id]) }}" class="actionboard-link">Back to Match</a>
                </div>
            </div>
        </header>

        <div class="actionboard-layout">
            <main class="actionboard-main">
                <div class="actionboard-shell crex-operator-card" style="padding: 28px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px;">
                        <h2 style="margin: 0; font-size: 16px; font-weight: 850; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">Match Settings Engine</h2>
                        <div id="save_indicator" style="display: none; background: #fbbf24; color: #000; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 900; letter-spacing: 0.5px; animation: pulseGlow 1.5s infinite;">AUTO-SAVING...</div>
                    </div>
                    
                    <form id="match_control_form" action="{{ route('site.gamehub.actionboard.update', ['id' => $match->id], false) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <!-- Quick Scoring Engine -->
                        <div class="quick-scoring-box">
                            <h3 class="scoring-box-title">⚡ Quick Scoring Grid</h3>
                            
                            <div class="batting-team-selector">
                                <span>Batting Side:</span>
                                <div class="side-radios">
                                    <label class="side-radio-label">
                                        <input type="radio" name="batting_team" value="home" checked onchange="setBattingTeam('home')">
                                        <span>{{ $match->home }}</span>
                                    </label>
                                    <label class="side-radio-label">
                                        <input type="radio" name="batting_team" value="away" onchange="setBattingTeam('away')">
                                        <span>{{ $match->away }}</span>
                                    </label>
                                </div>
                                
                                <label class="nb-checkbox-glow">
                                    <input type="checkbox" id="nb_modifier">
                                    <span>+ No Ball Run</span>
                                </label>
                            </div>
                            
                            <div class="score-buttons-grid">
                                <button type="button" class="score-btn score-btn--dot" onclick="addScore(0)">0</button>
                                <button type="button" class="score-btn" onclick="addScore(1)">1</button>
                                <button type="button" class="score-btn" onclick="addScore(2)">2</button>
                                <button type="button" class="score-btn" onclick="addScore(3)">3</button>
                                <button type="button" class="score-btn score-btn--four" onclick="addScore(4)">4</button>
                                <button type="button" class="score-btn score-btn--six" onclick="addScore(6)">6</button>
                                <button type="button" class="score-btn score-btn--wide" onclick="addExtra('wd')">WD</button>
                                <button type="button" class="score-btn score-btn--nb" onclick="addExtra('nb')">NB</button>
                                <button type="button" class="score-btn score-btn--wicket" onclick="addWicket()">W</button>
                            </div>
                            <p class="autosave-caption">* Click any action key to record scores and trigger auto-saving instantly.</p>
                        </div>
                        
                        <!-- Hidden Raw Data Fields -->
                        <div style="display: none;">
                            <input type="number" name="home_score" value="{{ $match->home_score }}">
                            <input type="number" name="away_score" value="{{ $match->away_score }}">
                            <input type="hidden" name="score_text" id="score_text_hidden" value="{{ $match->score_text }}">
                            <input type="text" name="overs" value="{{ $match->overs }}">
                            <input type="text" name="crr" value="{{ $match->crr }}">
                            <input type="text" name="decision" value="{{ $match->decision }}">
                            <input type="text" name="timeline_event" value="">
                            <input type="hidden" name="over_summary" id="over_summary_hidden" value="{{ json_encode($match->over_summary ?? []) }}">
                        </div>

                        <!-- Match Status & Projections -->
                        <div class="operator-meta-box">
                            <label class="operator-box-title">Match Status & Win Chance</label>
                            <div class="meta-inputs-grid">
                                <div>
                                    <span class="input-subtitle">Live State</span>
                                    <select name="status" onchange="autoSaveMatch()" class="operator-select">
                                        <option value="Scheduled" {{ $match->status == 'Scheduled' ? 'selected' : '' }}>Scheduled</option>
                                        <option value="Live" {{ $match->status == 'Live' ? 'selected' : '' }}>Live</option>
                                        <option value="Completed" {{ $match->status == 'Completed' ? 'selected' : '' }}>Completed</option>
                                    </select>
                                </div>
                                <div>
                                    <span class="input-subtitle">Win Probability (Home - Away)</span>
                                    <div class="probability-input-wrapper">
                                        @php $prob = is_array($match->probability) ? $match->probability : ['home' => 50, 'away' => 50]; @endphp
                                        <input type="number" name="prob_home" onchange="autoSaveMatch()" value="{{ $prob['home'] ?? 50 }}" placeholder="Home" class="operator-num-input">
                                        <span>%</span>
                                        <input type="number" name="prob_away" onchange="autoSaveMatch()" value="{{ $prob['away'] ?? 50 }}" placeholder="Away" class="operator-num-input">
                                        <span>%</span>
                                    </div>
                                </div>
                                <div>
                                    <span class="input-subtitle">Projected Score Range</span>
                                    @php $proj = is_array($match->projected_score) ? $match->projected_score : ['range' => '']; @endphp
                                    <input type="text" name="proj_range" onchange="autoSaveMatch()" value="{{ $proj['range'] ?? '' }}" placeholder="e.g. 175-185" class="operator-text-input">
                                </div>
                            </div>
                        </div>

                        <!-- Current Players on Field -->
                        <h3 class="engine-section-title">🏏 Players currently on field</h3>
                        <div class="onfield-players-grid">
                            <!-- Striker -->
                            <div class="player-field-card striker-card">
                                <div class="player-field-header">
                                    <span class="icon">🏏</span>
                                    <strong>STRIKER</strong>
                                </div>
                                <select name="striker_name" id="striker_name" onchange="resetPlayerStats('striker')" class="player-selector-dropdown"></select>
                                <div class="player-stat-inputs">
                                    <div>
                                        <input type="number" name="striker_runs" value="{{ $match->batters[0]['runs'] ?? 0 }}" readonly class="readonly-stat-input">
                                        <span>RUNS</span>
                                    </div>
                                    <div>
                                        <input type="number" name="striker_balls" value="{{ $match->batters[0]['balls'] ?? 0 }}" readonly class="readonly-stat-input">
                                        <span>BALLS</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Non-Striker -->
                            <div class="player-field-card nonstriker-card">
                                <div class="player-field-header">
                                    <span class="icon">🏏</span>
                                    <strong>NON-STRIKER</strong>
                                </div>
                                <select name="non_striker_name" id="non_striker_name" onchange="resetPlayerStats('non_striker')" class="player-selector-dropdown"></select>
                                <div class="player-stat-inputs">
                                    <div>
                                        <input type="number" name="non_striker_runs" value="{{ $match->batters[1]['runs'] ?? 0 }}" readonly class="readonly-stat-input">
                                        <span>RUNS</span>
                                    </div>
                                    <div>
                                        <input type="number" name="non_striker_balls" value="{{ $match->batters[1]['balls'] ?? 0 }}" readonly class="readonly-stat-input">
                                        <span>BALLS</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Bowler -->
                            <div class="player-field-card bowler-card">
                                <div class="player-field-header">
                                    <span class="icon">🥎</span>
                                    <strong>ACTIVE BOWLER</strong>
                                </div>
                                <select name="bowler_name" id="bowler_name" onchange="resetPlayerStats('bowler')" class="player-selector-dropdown"></select>
                                <div class="player-stat-inputs">
                                    <div>
                                        <input type="text" name="bowler_figures" value="{{ $match->bowler['figures'] ?? '0-0' }}" readonly class="readonly-stat-input">
                                        <span>W-R</span>
                                    </div>
                                    <div>
                                        <input type="text" name="bowler_overs" value="{{ $match->bowler['overs'] ?? '0.0' }}" readonly class="readonly-stat-input">
                                        <span>OVERS</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Setup (Squads) -->
                        <details class="advanced-squads-details">
                            <summary class="advanced-summary">Advanced Settings (Squad Configuration)</summary>
                            <div style="margin-top: 20px;">
                                @php
                                    $homeSquadString = '';
                                    if(isset($match->home_squad) && is_array($match->home_squad)) {
                                        $homeSquadString = implode(', ', array_map(function($player) {
                                            if (is_array($player)) { return $player['id'] ?? $player['name']; }
                                            return $player;
                                        }, $match->home_squad));
                                    }
                                    $awaySquadString = '';
                                    if(isset($match->away_squad) && is_array($match->away_squad)) {
                                        $awaySquadString = implode(', ', array_map(function($player) {
                                            if (is_array($player)) { return $player['id'] ?? $player['name']; }
                                            return $player;
                                        }, $match->away_squad));
                                    }
                                @endphp
                                <div class="squads-config-grid">
                                    <!-- Home Squad -->
                                    <div class="squad-config-col">
                                        <label class="squad-config-label">{{ $match->home }} Playing XI</label>
                                        <div class="squad-config-input-group" style="position: relative;">
                                            <input type="text" id="home_player_input" placeholder="Search Player ID or Name..." class="squad-text-input" autocomplete="off" oninput="debounceSearch('home')">
                                            <button type="button" onclick="addPlayerById('home')" class="squad-add-btn">Add</button>
                                            <div id="home_player_suggestions" style="display: none; position: absolute; left: 0; right: 0; top: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; z-index: 1000; max-height: 220px; overflow-y: auto; box-shadow: 0 10px 20px rgba(0,0,0,0.1); margin-top: 4px;"></div>
                                        </div>
                                        <div id="home_player_list" class="squad-config-list-box"></div>
                                        <input type="hidden" name="home_squad" id="home_squad_hidden" value="{{ $homeSquadString }}">
                                    </div>

                                    <!-- Away Squad -->
                                    <div class="squad-config-col">
                                        <label class="squad-config-label">{{ $match->away }} Playing XI</label>
                                        <div class="squad-config-input-group" style="position: relative;">
                                            <input type="text" id="away_player_input" placeholder="Search Player ID or Name..." class="squad-text-input" autocomplete="off" oninput="debounceSearch('away')">
                                            <button type="button" onclick="addPlayerById('away')" class="squad-add-btn">Add</button>
                                            <div id="away_player_suggestions" style="display: none; position: absolute; left: 0; right: 0; top: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; z-index: 1000; max-height: 220px; overflow-y: auto; box-shadow: 0 10px 20px rgba(0,0,0,0.1); margin-top: 4px;"></div>
                                        </div>
                                        <div id="away_player_list" class="squad-config-list-box"></div>
                                        <input type="hidden" name="away_squad" id="away_squad_hidden" value="{{ $awaySquadString }}">
                                    </div>
                                </div>
                            </div>
                        </details>

                        <button type="button" onclick="autoSaveMatch()" class="submit-save-btn">
                            Save Control Settings
                        </button>
                    </form>
                </div>
            </main>

            <aside class="actionboard-side">
                <div class="actionboard-shell state-hub-card">
                    <h3 class="state-hub-title">LIVE STATE HUB</h3>
                    <div id="sidebar_score_display" class="state-score-box">
                        <span id="sidebar_score_text">{{ $match->score_text ?: $match->home_score . '-0' }}</span>
                    </div>
                    <div class="state-overs-box">OVERS: <span id="sidebar_overs">{{ $match->overs }}</span></div>
                    
                    <strong class="state-recent-title">RECENT DELIVERIES</strong>
                    <ul id="sidebar_events" class="state-events-list">
                        @if($match->timeline)
                            @foreach(array_slice($match->timeline, 0, 5) as $event)
                                <li>
                                    <span class="time">{{ $event['time'] }}</span>
                                    <span class="text">{{ $event['text'] }}</span>
                                </li>
                            @endforeach
                        @else
                            <li id="no_events_li" class="empty">Waiting for scored actions...</li>
                        @endif
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</div>

<style>
    body { 
        background-color: #f4f6f8; 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    
    /* Animation Overlay Styles */
    #score_animation_overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(240, 255, 240, 0.95); /* greenish white background */
        z-index: 9999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        backdrop-filter: blur(8px);
    }
    
    #score_animation_text {
        font-size: 10vw;
        font-weight: 900;
        text-transform: uppercase;
        color: #0d543c;
        text-shadow: 0 10px 30px rgba(13, 84, 60, 0.2);
        transform: scale(0.3);
        opacity: 0;
        transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .actionboard-page {
        padding: 24px 0 42px;
    }
    .actionboard-shell {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .actionboard-shell__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 24px;
        background: linear-gradient(135deg, #093727 0%, #0d543c 100%);
        color: #fff;
    }
    .actionboard-brand {
        font-size: 16px;
        font-weight: 850;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .actionboard-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        padding: 8px 16px;
        text-decoration: none;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.15);
        transition: all 0.2s;
    }
    .actionboard-link:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* Layout Grid */
    .actionboard-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 24px;
        align-items: start;
    }

    /* Quick Scoring Engine Styles */
    .quick-scoring-box {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 28px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);
    }
    .quick-scoring-box:focus-within {
        border-color: #0d543c;
    }
    .scoring-box-title {
        margin-top: 0;
        color: #0d543c;
        font-size: 15px;
        font-weight: 850;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 20px;
    }
    .batting-team-selector {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        align-items: center;
        font-weight: 700;
        font-size: 14px;
        color: #334155;
    }
    .side-radios {
        display: flex;
        gap: 16px;
    }
    .side-radio-label {
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 800;
        color: #1e293b;
        background: #fff;
        border: 1px solid #cbd5e1;
        padding: 6px 12px;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .side-radio-label:has(input:checked) {
        border-color: #0d543c;
        background: #eefdf6;
        color: #093727;
    }
    .nb-checkbox-glow {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 6px;
        background: #fef3c7;
        border: 1px solid #fde68a;
        color: #b45309;
        font-weight: 800;
        padding: 6px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .nb-checkbox-glow:hover {
        background: #fef08a;
    }

    .score-buttons-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
        gap: 12px;
    }
    .score-btn {
        padding: 14px;
        font-size: 18px;
        font-weight: 850;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #ffffff;
        color: #1e293b;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        transition: all 0.2s ease;
    }
    .score-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    .score-btn--four {
        background: #eff6ff;
        border-color: #3b82f6;
        color: #1d4ed8;
    }
    .score-btn--four:hover {
        background: #dbeafe;
    }
    .score-btn--six {
        background: #f3e8ff;
        border-color: #7c3aed;
        color: #6b21a8;
    }
    .score-btn--six:hover {
        background: #e9d5ff;
    }
    .score-btn--wide, .score-btn--nb {
        background: #fffbeb;
        border-color: #f59e0b;
        color: #b45309;
    }
    .score-btn--wide:hover, .score-btn--nb:hover {
        background: #fef3c7;
    }
    .score-btn--wicket {
        background: #ffe4e6;
        border-color: #ef4444;
        color: #b91c1c;
    }
    .score-btn--wicket:hover {
        background: #fee2e2;
    }
    .autosave-caption {
        margin: 14px 0 0 0;
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }

    /* Meta projections rows */
    .operator-meta-box {
        margin-bottom: 28px;
        padding: 20px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
    }
    .operator-box-title {
        display: block;
        font-size: 13px;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 16px;
    }
    .meta-inputs-grid {
        display: grid;
        grid-template-columns: 1fr 1.2fr 1fr;
        gap: 20px;
    }
    .input-subtitle {
        display: block;
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .operator-select, .operator-text-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        background: #fff;
        outline: 0;
    }
    .operator-select:focus, .operator-text-input:focus {
        border-color: #0d543c;
    }
    .probability-input-wrapper {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .operator-num-input {
        width: 65px;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        text-align: center;
        font-weight: 800;
        outline: 0;
    }
    .operator-num-input:focus {
        border-color: #0d543c;
    }

    /* Onfield players selector cards */
    .engine-section-title {
        margin: 28px 0 16px;
        font-size: 14px;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .onfield-players-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }
    .player-field-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);
    }
    .player-field-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }
    .player-field-header .icon {
        font-size: 16px;
    }
    .player-field-header strong {
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        letter-spacing: 0.5px;
    }
    .player-selector-dropdown {
        width: 100%;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-weight: 800;
        font-size: 13px;
        color: #1e293b;
        background: #f8fafc;
        outline: 0;
        margin-bottom: 14px;
    }
    .player-selector-dropdown:focus {
        border-color: #0d543c;
    }
    .player-stat-inputs {
        display: flex;
        gap: 12px;
        justify-content: center;
    }
    .player-stat-inputs div {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    .readonly-stat-input {
        width: 65px;
        padding: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f1f5f9;
        text-align: center;
        font-weight: 850;
        color: #0f172a;
        font-size: 14px;
    }
    .player-stat-inputs span {
        font-size: 9px;
        font-weight: 800;
        color: #94a3b8;
        letter-spacing: 0.5px;
    }

    /* Advanced squad expansion */
    .advanced-squads-details {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 18px;
        margin-bottom: 28px;
    }
    .advanced-summary {
        font-weight: 800;
        font-size: 13px;
        color: #475569;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        outline: 0;
    }
    .squads-config-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }
    .squad-config-label {
        display: block;
        font-weight: 800;
        font-size: 12px;
        color: #1e293b;
        margin-bottom: 10px;
    }
    .squad-config-input-group {
        display: flex;
        gap: 8px;
        margin-bottom: 14px;
    }
    .squad-text-input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        outline: 0;
        font-size: 13px;
        font-weight: 600;
    }
    .squad-text-input:focus {
        border-color: #0d543c;
    }
    .squad-add-btn {
        background: #0d543c;
        color: #fff;
        padding: 0 16px;
        border: 0;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 800;
        font-size: 12px;
        text-transform: uppercase;
    }
    .squad-add-btn:hover {
        background: #093727;
    }
    .squad-config-list-box {
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: #fff;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        min-height: 90px;
        max-height: 280px;
        overflow-y: auto;
    }

    .submit-save-btn {
        background: #0d543c;
        color: white;
        border: none;
        padding: 14px 28px;
        font-size: 15px;
        cursor: pointer;
        border-radius: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        width: 100%;
        box-shadow: 0 10px 15px -3px rgba(13, 84, 60, 0.15);
        transition: all 0.2s;
    }
    .submit-save-btn:hover {
        background: #093727;
        transform: translateY(-1px);
        box-shadow: 0 12px 20px -3px rgba(13, 84, 60, 0.25);
    }

    /* State Hub Sidebar Card */
    .state-hub-card {
        padding: 24px;
        border-radius: 16px;
        border-color: #e2e8f0;
    }
    .state-hub-title {
        margin-top: 0;
        font-size: 13px;
        font-weight: 850;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .state-score-box {
        font-size: 26px;
        font-weight: 900;
        color: #0d543c;
        text-align: center;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }
    .state-overs-box {
        text-align: center;
        color: #64748b;
        font-weight: 700;
        font-size: 13px;
        margin-bottom: 24px;
    }
    .state-recent-title {
        display: block;
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 12px;
    }
    .state-events-list {
        padding: 0;
        margin: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .state-events-list li {
        display: flex;
        justify-content: space-between;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
        font-size: 13px;
    }
    .state-events-list li .time {
        font-weight: 800;
        color: #64748b;
    }
    .state-events-list li .text {
        font-weight: 700;
        color: #1e293b;
        text-align: right;
    }
    .state-events-list li.empty {
        padding: 16px;
        text-align: center;
        color: #94a3b8;
        font-weight: 500;
        background: transparent;
        border: 1px dashed #cbd5e1;
    }

    @media (max-width: 1024px) {
        .actionboard-layout {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .meta-inputs-grid, .onfield-players-grid, .squads-config-grid {
            grid-template-columns: 1fr;
        }
        .nb-checkbox-glow {
            margin-left: 0;
        }
        .batting-team-selector {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>div>
</div>

<style>
    body { background-color: #f2f5f9; }
    
    /* Animation Overlay Styles */
    #score_animation_overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(240, 255, 240, 0.9); /* greenish white background */
        z-index: 9999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        backdrop-filter: blur(5px);
    }
    
    #score_animation_text {
        font-size: 12vw;
        font-weight: 900;
        text-transform: uppercase;
        text-shadow: 0 10px 30px rgba(0,0,0,0.15);
        transform: scale(0);
        opacity: 0;
        transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .actionboard-shell{background:#fff;border-radius:12px;overflow:hidden;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,.05)}
    .actionboard-shell__top{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 20px;background:#099F53;color:#fff}
    .actionboard-brand{font-size:20px;font-weight:800;letter-spacing:.02em;text-transform:uppercase}
    .actionboard-link{display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:9px 16px;text-decoration:none;font-weight:700;transition:all 0.2s}
    .actionboard-link{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.2)}
    .actionboard-link:hover{background:rgba(255,255,255,.25)}
    .score-btn { padding: 12px; font-size: 18px; font-weight: 800; border: none; border-radius: 8px; background: #f1f5f9; cursor: pointer; transition: all 0.2s; }
    .score-btn:hover { background: #e2e8f0; }
    .score-btn-4 { background: #3b82f6; color: white; }
    .score-btn-6 { background: #8b5cf6; color: white; }
    .score-btn-wide, .score-btn-nb { background: #f59e0b; color: white; }
    .score-btn-w { background: #ef4444; color: white; }
</style>

<script>
    const totalOvers = {{ (int) filter_var($match->competition, FILTER_SANITIZE_NUMBER_INT) ?: 20 }};
    const squads = {
        home: [],
        away: []
    };

    let currentOverBalls = [];
    let currentOverRuns = 0;

    function initCurrentOverState() {
        let el = getScoreElements();
        let oversVal = el.overs.value || "0.0";
        let parsed = parseOvers(oversVal);
        
        currentOverBalls = [];
        currentOverRuns = 0;
        
        let fullTimeline = {!! json_encode($match->timeline ?? []) !!};
        if (!fullTimeline || fullTimeline.length === 0) return;
        
        let prefix = parsed.completed + ".";
        let activeEvents = [];
        
        // Timeline is newest first, so we collect and then reverse
        for (let i = 0; i < fullTimeline.length; i++) {
            let ev = fullTimeline[i];
            if (String(ev.time).startsWith(prefix)) {
                activeEvents.push(ev);
            } else if (parseFloat(ev.time) < parsed.completed) {
                // We've gone past the current over in history
                break;
            }
        }
        
        activeEvents.reverse();
        
        activeEvents.forEach(ev => {
            let text = ev.text;
            let ballCode = "0";
            let run = 0;
            
            if (text === "OUT! WICKET!") {
                ballCode = "W";
                run = 0;
            } else if (text.startsWith("NO BALL")) {
                let m = text.match(/NO BALL \+ (\d+) runs/);
                let r = m ? parseInt(m[1]) : 0;
                ballCode = r + "nb";
                run = r + 1;
            } else if (text.startsWith("WD") && text.includes("+")) {
                let m = text.match(/WD \+ (\d+) runs/);
                let extraRuns = m ? parseInt(m[1]) : 0;
                let totalRuns = extraRuns + 1;
                ballCode = totalRuns + "wd";
                run = totalRuns;
            } else if (text.startsWith("NB") && text.includes("+")) {
                let m = text.match(/NB \+ (\d+) runs/);
                let extraRuns = m ? parseInt(m[1]) : 0; 
                let totalRuns = extraRuns + 1;
                ballCode = totalRuns + "nb";
                run = totalRuns;
            } else if ((text.startsWith("LB") || text.startsWith("B")) && text.includes("+")) {
                let type = text.startsWith("LB") ? "lb" : "b";
                let m = text.match(/[A-Z]+ \+ (\d+) runs/);
                let extraRuns = m ? parseInt(m[1]) : 0;
                let totalRuns = extraRuns + 1;
                ballCode = totalRuns + type;
                run = totalRuns;
            } else if (text === "FOUR!") {
                ballCode = "4";
                run = 4;
            } else if (text === "SIX!") {
                ballCode = "6";
                run = 6;
            } else if (text === "Dot ball.") {
                ballCode = "0";
                run = 0;
            } else {
                let m = text.match(/^(\d+) runs/);
                if (m) {
                    ballCode = m[1];
                    run = parseInt(m[1]);
                } else if (text === "WD" || text === "NB" || text === "LB" || text === "B") {
                    ballCode = "1" + text.toLowerCase();
                    run = 1;
                }
            }
            
            currentOverBalls.push(ballCode);
            currentOverRuns += run;
        });
    }

    function initSquads() {
        squads.home = {!! json_encode($match->home_squad ?? []) !!};
        squads.away = {!! json_encode($match->away_squad ?? []) !!};
        
        updateHidden('home');
        updateHidden('away');

        renderList('home');
        renderList('away');
        updatePlayerDropdowns();
    }

    let searchDebounce = null;
    function debounceSearch(team) {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            searchPlayers(team);
        }, 250);
    }

    async function searchPlayers(team) {
        const input = document.getElementById(team + '_player_input');
        const query = input.value.trim();
        const suggestionsBox = document.getElementById(team + '_player_suggestions');
        
        if (query.length < 2) {
            suggestionsBox.innerHTML = '';
            suggestionsBox.style.display = 'none';
            return;
        }

        try {
            const res = await fetch(`/api/players/search?q=${encodeURIComponent(query)}`);
            const data = await res.json();
            
            suggestionsBox.innerHTML = '';
            
            if (data.length > 0) {
                data.forEach(p => {
                    const row = document.createElement('div');
                    row.style.cssText = "display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.15s; background: #fff;";
                    row.innerHTML = `
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; color: #475569;">
                            ${p.name.substring(0, 2).toUpperCase()}
                        </div>
                        <div style="flex: 1;">
                            <strong style="font-size: 13px; color: #1e293b; display: block;">${p.name}</strong>
                            <span style="font-size: 10px; color: #64748b; font-weight: 600;">${p.id} • ${p.role} • ${p.district}</span>
                        </div>
                    `;
                    row.onmousedown = (e) => {
                        e.preventDefault();
                        selectSuggestedPlayer(team, p);
                    };
                    suggestionsBox.appendChild(row);
                });
            }

            // Always add a quick-add guest player option at the bottom
            const guestRow = document.createElement('div');
            guestRow.style.cssText = "display: flex; align-items: center; gap: 8px; padding: 12px 14px; background: #f0fdf4; border-top: 1px solid #bbf7d0; cursor: pointer; color: #16a34a; font-weight: 800; font-size: 12px; text-transform: uppercase;";
            guestRow.innerHTML = `➕ Quick-Add Guest: "${query}"`;
            guestRow.onmousedown = (e) => {
                e.preventDefault();
                addGuestPlayer(team, query);
            };
            suggestionsBox.appendChild(guestRow);
            
            suggestionsBox.style.display = 'block';
        } catch (e) {
            console.error('Error searching players', e);
        }
    }

    function selectSuggestedPlayer(team, player) {
        if(squads[team].some(p => p.id === player.id || p === player.id)) {
            alert('Player already in squad');
            return;
        }
        squads[team].push({ id: player.id, name: player.name, role: player.role, style: player.style });
        
        const input = document.getElementById(team + '_player_input');
        input.value = '';
        
        const suggestionsBox = document.getElementById(team + '_player_suggestions');
        suggestionsBox.style.display = 'none';
        
        updateHidden(team);
        renderList(team);
        updatePlayerDropdowns();
    }

    async function addGuestPlayer(team, name) {
        const csrfToken = document.querySelector('input[name="_token"]').value;
        try {
            const res = await fetch('/api/players/guest', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ name: name })
            });
            const data = await res.json();
            
            if (data.success) {
                squads[team].push({ id: data.id, name: data.name, role: data.role, style: data.style });
                
                const input = document.getElementById(team + '_player_input');
                input.value = '';
                
                const suggestionsBox = document.getElementById(team + '_player_suggestions');
                suggestionsBox.style.display = 'none';
                
                updateHidden(team);
                renderList(team);
                updatePlayerDropdowns();
            } else {
                alert('Failed to add guest player');
            }
        } catch (e) {
            alert('Error adding guest player');
        }
    }

    async function addPlayerById(team) {
        const input = document.getElementById(team + '_player_input');
        const playerId = input.value.trim();
        if(!playerId) return;

        if(squads[team].some(p => p.id === playerId || p === playerId)) {
            alert('Player already in squad');
            return;
        }

        try {
            const res = await fetch(`/gamehub/actionboard/player/${playerId}`);
            const data = await res.json();
            
            if(data.success) {
                squads[team].push({ id: playerId, name: data.name, role: data.role, style: data.style });
                input.value = '';
                updateHidden(team);
                renderList(team);
                updatePlayerDropdowns();
            } else {
                // If not found, ask if they want to quick add as guest
                if(confirm(`Player ID "${playerId}" not found. Add as Guest Player instead?`)) {
                    addGuestPlayer(team, playerId);
                }
            }
        } catch(e) {
            alert('Error fetching player');
        }
    }

    function removePlayer(team, idx) {
        squads[team].splice(idx, 1);
        updateHidden(team);
        renderList(team);
        updatePlayerDropdowns();
    }

    function updateHidden(team) {
        document.getElementById(team + '_squad_hidden').value = squads[team].map(p => typeof p === 'object' ? p.id : p).join(', ');
    }

    function renderList(team) {
        let listDiv = document.getElementById(team + '_player_list');
        listDiv.innerHTML = '';
        if (squads[team].length === 0) {
            listDiv.innerHTML = '<div style="padding:12px;color:#94a3b8;font-size:13px;text-align:center;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:4px;">No players added yet.</div>';
            return;
        }
        squads[team].forEach((p, index) => {
            const isObj = typeof p === 'object';
            const id = isObj ? p.id : p;
            const title = isObj ? `<b>${p.name}</b> (${p.role})` : `Player ID: ${id}`;
            const sub = isObj ? `<div style="font-size:12px;color:#6b7280;">Style: ${p.style}</div>` : '';
            const html = `<div style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:8px 12px; border:1px solid #e2e8f0; border-radius:4px; font-size:14px;">
                <div><div><span style="color:#2563eb;font-weight:600;margin-right:8px;">#${id}</span> ${title}</div>${sub}</div>
                <button type="button" onclick="removePlayer('${team}', ${index})" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:16px;">&times;</button>
            </div>`;
            listDiv.insertAdjacentHTML('beforeend', html);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSquads();
        initCurrentOverState();

        document.getElementById('home_player_input').addEventListener('blur', () => {
            setTimeout(() => { document.getElementById('home_player_suggestions').style.display = 'none'; }, 200);
        });
        document.getElementById('away_player_input').addEventListener('blur', () => {
            setTimeout(() => { document.getElementById('away_player_suggestions').style.display = 'none'; }, 200);
        });
    });

    let currentBatting = 'home';
    function setBattingTeam(team) { 
        currentBatting = team; 
        updatePlayerDropdowns();
        
        // Recalculate score text
        let el = getScoreElements();
        let activeScore = currentBatting === 'home' ? el.homeScore.value : el.awayScore.value;
        
        let totalWickets = 0;
        let summaryHiddenForWickets = document.getElementById('over_summary_hidden');
        let overDataForWickets = JSON.parse(summaryHiddenForWickets.value || '[]');
        overDataForWickets.forEach(o => {
            if ((!o.batting || o.batting === currentBatting) && Array.isArray(o.balls)) {
                o.balls.forEach(b => {
                    if (String(b).toUpperCase() === 'W') {
                        totalWickets += 1;
                    }
                });
            }
        });
        currentOverBalls.forEach(b => {
            if (String(b).toUpperCase() === 'W') {
                totalWickets += 1;
            }
        });
        
        let scoreTextHidden = document.getElementById('score_text_hidden');
        if (scoreTextHidden) {
            scoreTextHidden.value = `${activeScore}-${totalWickets}`;
        }
        
        let sidebarScore = document.getElementById('sidebar_score_text');
        if (sidebarScore) {
            sidebarScore.innerText = `${activeScore}-${totalWickets}`;
        }
        
        autoSaveMatch();
    }

    function resetPlayerStats(type) {
        let el = getScoreElements();
        if (type === 'striker') {
            el.sRuns.value = 0; el.sBalls.value = 0;
        } else if (type === 'non_striker') {
            el.nsRuns.value = 0; el.nsBalls.value = 0;
        } else if (type === 'bowler') {
            const selectedBowler = el.bName.value;
            if (!selectedBowler) {
                el.bFigures.value = "0-0";
                el.bOvers.value = "0.0";
                return;
            }
            
            // Look up history from over_summary
            let summaryHidden = document.getElementById('over_summary_hidden');
            let overData = JSON.parse(summaryHidden.value || '[]');
            
            let totalOvers = 0;
            let totalRuns = 0;
            let totalWickets = 0;
            
            overData.forEach(o => {
                if (!o.batting || o.batting === currentBatting) {
                    if (!o.bowler || o.bowler === selectedBowler) {
                        totalOvers += 1;
                        totalRuns += parseInt(o.runs || 0);
                        
                        if (Array.isArray(o.balls)) {
                            o.balls.forEach(b => {
                                if (String(b).toUpperCase() === 'W') {
                                    totalWickets += 1;
                                }
                            });
                        }
                    }
                }
            });
            
            // Count wickets in current active over
            let activeWickets = 0;
            currentOverBalls.forEach(b => {
                if (String(b).toUpperCase() === 'W') {
                    activeWickets += 1;
                }
            });
            
            // Calculate active over balls count (excluding non-legal balls like wides/no-balls)
            let activeBalls = currentOverBalls.filter(b => {
                return !b.toLowerCase().includes('wd') && !b.toLowerCase().includes('nb');
            }).length;
            
            let finalWickets = totalWickets + activeWickets;
            let finalRuns = totalRuns + currentOverRuns;
            
            el.bFigures.value = `${finalWickets}-${finalRuns}`;
            el.bOvers.value = `${totalOvers}.${activeBalls}`;
        }
        autoSaveMatch();
    }

    function updatePlayerDropdowns() {
        const strikerSelect = document.getElementById('striker_name');
        const nonStrikerSelect = document.getElementById('non_striker_name');
        const bowlerSelect = document.getElementById('bowler_name');

        const battingSquad = currentBatting === 'home' ? squads.home : squads.away;
        const bowlingSquad = currentBatting === 'home' ? squads.away : squads.home;

        // Resolve current values to names if they are IDs
        const resolveToName = (val) => {
            if (!val) return '';
            const allPlayers = [...squads.home, ...squads.away];
            const found = allPlayers.find(p => typeof p === 'object' && ((String(p.id) === String(val)) || (String(p.name) === String(val))));
            return found ? found.name : val;
        };

        const currentS = resolveToName(strikerSelect.value);
        const currentNS = resolveToName(nonStrikerSelect.value);
        const currentB = resolveToName(bowlerSelect.value);

        let battingOptions = '<option value="">Select Batter...</option>';
        battingSquad.forEach(p => {
            const name = typeof p === 'object' ? p.name : p;
            battingOptions += `<option value="${name}">${name}</option>`;
        });

        let bowlingOptions = '<option value="">Select Bowler...</option>';
        bowlingSquad.forEach(p => {
            const name = typeof p === 'object' ? p.name : p;
            bowlingOptions += `<option value="${name}">${name}</option>`;
        });

        strikerSelect.innerHTML = battingOptions;
        nonStrikerSelect.innerHTML = battingOptions;
        bowlerSelect.innerHTML = bowlingOptions;

        if(currentS) strikerSelect.value = currentS;
        if(currentNS) nonStrikerSelect.value = currentNS;
        if(currentB) bowlerSelect.value = currentB;
    }

    function getScoreElements() {
        return {
            homeScore: document.querySelector('input[name="home_score"]'),
            awayScore: document.querySelector('input[name="away_score"]'),
            overs: document.querySelector('input[name="overs"]'),
            crr: document.querySelector('input[name="crr"]'),
            sName: document.querySelector('select[name="striker_name"]'),
            sRuns: document.querySelector('input[name="striker_runs"]'),
            sBalls: document.querySelector('input[name="striker_balls"]'),
            nsName: document.querySelector('select[name="non_striker_name"]'),
            nsRuns: document.querySelector('input[name="non_striker_runs"]'),
            nsBalls: document.querySelector('input[name="non_striker_balls"]'),
            bName: document.querySelector('select[name="bowler_name"]'),
            bFigures: document.querySelector('input[name="bowler_figures"]'),
            bOvers: document.querySelector('input[name="bowler_overs"]'),
            timeline: document.querySelector('input[name="timeline_event"]')
        };
    }

    function parseOvers(overStr) {
        let parts = String(overStr || "0.0").split('.');
        return { completed: parseInt(parts[0] || 0), balls: parseInt(parts[1] || 0) };
    }

    function formatOvers(completed, balls) {
        if (balls >= 6) { completed += Math.floor(balls / 6); balls = balls % 6; }
        return `${completed}.${balls}`;
    }

    function addOversStr(overStr, addBalls) {
        let o = parseOvers(overStr);
        o.balls += addBalls;
        return formatOvers(o.completed, o.balls);
    }

    function getBowlerStats(figStr) {
        let parts = String(figStr || "0-0").split('-');
        return { wickets: parseInt(parts[0] || 0), runs: parseInt(parts[1] || 0) };
    }

    function updateScoreBase(runs, balls, isExtra, extraType, isWicket) {
        let el = getScoreElements();
        
        let scoreInput = currentBatting === 'home' ? el.homeScore : el.awayScore;
        let oldOvers = el.overs.value;

        // No Ball Modifier Logic
        let nbMod = document.getElementById('nb_modifier');
        let nbModifierActive = (nbMod && nbMod.checked);
        let runAddedToTeam = runs;

        if (nbModifierActive) {
            isExtra = true;
            extraType = 'nb';
            balls = 0; // Not a legal delivery
            runAddedToTeam = runs + 1; // e.g. 4 off the bat + 1 penalty
            
            scoreInput.value = parseInt(scoreInput.value || 0) + runAddedToTeam;
            el.sRuns.value = parseInt(el.sRuns.value || 0) + runs;
            el.sBalls.value = parseInt(el.sBalls.value || 0) + 1; // batsman faced the ball
            
            nbMod.checked = false; // reset
        } else {
            scoreInput.value = parseInt(scoreInput.value || 0) + runs;
            if (!isExtra) {
                el.sRuns.value = parseInt(el.sRuns.value || 0) + runs;
                el.sBalls.value = parseInt(el.sBalls.value || 0) + balls;
            }
        }

        // Update Overs
        if (!isExtra) {
            el.overs.value = addOversStr(el.overs.value, balls);
            el.bOvers.value = addOversStr(el.bOvers.value, balls);
        }

        // Update CRR
        let totalScore = parseInt(scoreInput.value);
        let o = parseOvers(el.overs.value);
        let totalBalls = (o.completed * 6) + o.balls;
        if (totalBalls > 0) {
            let crrVal = (totalScore / totalBalls) * 6;
            el.crr.value = crrVal.toFixed(2);

            // Auto-update projected score
            let projected = Math.round(crrVal * totalOvers);
            let projRangeInput = document.querySelector('input[name="proj_range"]');
            if (projRangeInput) {
                projRangeInput.value = `${projected - 5}-${projected + 5}`;
            }

            // Auto-update win probability
            let crrDiff = crrVal - 7.5;
            let homeProb = 50;
            let awayProb = 50;
            if (currentBatting === 'home') {
                homeProb = Math.min(95, Math.max(5, Math.round(50 + crrDiff * 6)));
                awayProb = 100 - homeProb;
            } else {
                awayProb = Math.min(95, Math.max(5, Math.round(50 + crrDiff * 6)));
                homeProb = 100 - awayProb;
            }
            
            let probHomeInput = document.querySelector('input[name="prob_home"]');
            let probAwayInput = document.querySelector('input[name="prob_away"]');
            if (probHomeInput && probAwayInput) {
                probHomeInput.value = homeProb;
                probAwayInput.value = awayProb;
            }
        }

        // Update Bowler
        let bStats = getBowlerStats(el.bFigures.value);
        bStats.runs += runAddedToTeam;
        if (isWicket) bStats.wickets += 1;
        el.bFigures.value = `${bStats.wickets}-${bStats.runs}`;

        // Timeline Event text & ball code
        let ballCode = runs.toString();
        let eventText = runs + " runs";
        if (isWicket) { ballCode = 'W'; eventText = "OUT! WICKET!"; }
        else if (nbModifierActive) { ballCode = runs + 'nb'; eventText = `NO BALL + ${runs} runs`; }
        else if (isExtra) { ballCode = runs + extraType; eventText = extraType.toUpperCase() + (runs > 1 ? ` + ${runs-1} runs` : ''); }
        else if (runs === 4) { eventText = "FOUR!"; }
        else if (runs === 6) { eventText = "SIX!"; }
        else if (runs === 0 && !isExtra && !isWicket) { eventText = "Dot ball."; }
        
        // Timeline overwrite fix (Append to custom text)
        if (el.timeline.value.trim() === '') {
            el.timeline.value = eventText;
        } else {
            el.timeline.value = eventText + " - " + el.timeline.value;
        }

        // Over Summary Tracking
        currentOverBalls.push(ballCode);
        currentOverRuns += runAddedToTeam;

        // Calculate wickets
        let totalWickets = 0;
        let summaryHiddenForWickets = document.getElementById('over_summary_hidden');
        let overDataForWickets = JSON.parse(summaryHiddenForWickets.value || '[]');
        overDataForWickets.forEach(o => {
            if ((!o.batting || o.batting === currentBatting) && Array.isArray(o.balls)) {
                o.balls.forEach(b => {
                    if (String(b).toUpperCase() === 'W') {
                        totalWickets += 1;
                    }
                });
            }
        });
        currentOverBalls.forEach(b => {
            if (String(b).toUpperCase() === 'W') {
                totalWickets += 1;
            }
        });

        let activeScore = currentBatting === 'home' ? el.homeScore.value : el.awayScore.value;
        let scoreTextHidden = document.getElementById('score_text_hidden');
        if (scoreTextHidden) {
            scoreTextHidden.value = `${activeScore}-${totalWickets}`;
        }

        // --- UPDATE SIDEBAR DOM ---
        let sidebarScore = document.getElementById('sidebar_score_text');
        if (sidebarScore) {
            sidebarScore.innerText = `${activeScore}-${totalWickets}`;
        }
        document.getElementById('sidebar_overs').innerText = el.overs.value;
        
        let ul = document.getElementById('sidebar_events');
        let noEvents = document.getElementById('no_events_li');
        if (noEvents) noEvents.remove();
        
        let newLi = document.createElement('li');
        newLi.innerHTML = `<strong>${el.overs.value}</strong>: ${eventText}`;
        ul.insertBefore(newLi, ul.firstChild);
        while (ul.children.length > 5) ul.removeChild(ul.lastChild);
        // --------------------------

        // Wicket Logic
        if (isWicket) {
            el.sRuns.value = 0;
            el.sBalls.value = 0;
            el.sName.value = "";
        }

        // Swap Strike?
        let swap = false;
        if (!isExtra && (runs % 2 !== 0)) swap = true;
        
        // Over ended?
        let newO = parseOvers(el.overs.value);
        let oldO = parseOvers(oldOvers);
        let overEnded = (newO.completed > oldO.completed);
        
        if (overEnded) {
            swap = !swap; // Swap at end of over
            
            // Pack Over Summary
            let summaryHidden = document.getElementById('over_summary_hidden');
            let overData = JSON.parse(summaryHidden.value || '[]');
            overData.push({
                batting: currentBatting,
                over: newO.completed,
                runs: currentOverRuns,
                balls: [...currentOverBalls],
                bowler: el.bName.value
            });
            summaryHidden.value = JSON.stringify(overData);
            
            // Reset state
            currentOverBalls = [];
            currentOverRuns = 0;
            
            // Reset Bowler
            el.bName.value = "";
            el.bFigures.value = "0-0";
            el.bOvers.value = "0.0";
            setTimeout(() => alert("Over completed! Please select a new bowler."), 100);
        }
        
        if (swap) {
            let tr = el.sRuns.value; let tb = el.sBalls.value; let tn = el.sName.value;
            el.sRuns.value = el.nsRuns.value; el.sBalls.value = el.nsBalls.value; el.sName.value = el.nsName.value;
            el.nsRuns.value = tr; el.nsBalls.value = tb; el.nsName.value = tn;
        }

        // Trigger Auto-Save
        autoSaveMatch();

        // Immediately clear timeline to prevent appending duplicated text on fast clicks
        el.timeline.value = "";
    }

    let saveQueue = [];
    let isSaving = false;

    async function processQueue() {
        if (isSaving || saveQueue.length === 0) return;
        isSaving = true;

        let formData = saveQueue.shift();
        
        let ind = document.getElementById('save_indicator');
        if (ind) {
            ind.innerText = "Saving... (" + (saveQueue.length + 1) + " left)";
            ind.style.background = "#fbbf24";
            ind.style.display = 'block';
        }
        
        const form = document.getElementById('match_control_form');
        const url = form.getAttribute('action');
        
        try {
            const res = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if(res.ok) {
                if(ind) {
                    ind.innerText = "Saved!";
                    ind.style.background = "#099F53";
                    setTimeout(() => {
                        if (saveQueue.length === 0) {
                            ind.style.display = 'none';
                            ind.innerText = "Saving...";
                            ind.style.background = "#fbbf24";
                        }
                    }, 1000);
                }
            } else if (res.status === 422) {
                const data = await res.json();
                console.error("Validation Errors:", data.errors);
                alert("Validation Failed. Check console for details.");
                if (ind && saveQueue.length === 0) ind.style.display = 'none';
            } else {
                const text = await res.text();
                alert('Failed to auto-save. Status: ' + res.status + ' ' + res.statusText + '\nResponse: ' + text.substring(0, 100));
                if (ind && saveQueue.length === 0) ind.style.display = 'none';
            }
        } catch (e) {
            console.error(e);
            alert('Failed to auto-save. Network error.');
            if (ind && saveQueue.length === 0) ind.style.display = 'none';
        }
        
        isSaving = false;
        processQueue(); // process next in queue
    }

    function autoSaveMatch() {
        const form = document.getElementById('match_control_form');
        const formData = new FormData(form);
        saveQueue.push(formData);
        processQueue();
    }

    function triggerAnimation(type) {
        let overlay = document.getElementById('score_animation_overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'score_animation_overlay';
            overlay.innerHTML = '<div id="score_animation_text"></div>';
            document.body.appendChild(overlay);
        }
        let textDiv = document.getElementById('score_animation_text');
        
        let color = "#099F53"; // default green
        if (type === 'WICKET!') color = "#ef4444";
        else if (type === 'SIX!') color = "#8b5cf6";
        else if (type === 'FOUR!') color = "#3b82f6";
        else if (type === 'WIDE' || type === 'NO BALL') color = "#f59e0b";
        
        textDiv.innerText = type;
        textDiv.style.color = color;
        overlay.style.display = 'flex';
        
        // Reset animation state
        textDiv.style.transition = 'none';
        textDiv.style.transform = 'scale(0)';
        textDiv.style.opacity = '0';
        
        // Trigger reflow
        void textDiv.offsetWidth;
        
        textDiv.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        textDiv.style.transform = 'scale(1.2)';
        textDiv.style.opacity = '1';
        
        setTimeout(() => {
            textDiv.style.transform = 'scale(0)';
            textDiv.style.opacity = '0';
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 600);
        }, 1200);
    }

    function addScore(runs) { 
        updateScoreBase(runs, 1, false, '', false); 
        if (runs === 4) triggerAnimation('FOUR!');
        else if (runs === 6) triggerAnimation('SIX!');
        else triggerAnimation(runs + ' RUNS');
    }
    function addExtra(type) { 
        updateScoreBase(1, 0, true, type, false); 
        triggerAnimation(type === 'wd' ? 'WIDE' : type === 'nb' ? 'NO BALL' : 'EXTRA');
    }
    function addWicket() { 
        updateScoreBase(0, 1, false, '', true); 
        triggerAnimation('WICKET!');
    }

    // Event listeners are attached via onchange attributes directly in the HTML.

</script>
@endsection
