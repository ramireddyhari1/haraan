<div id="thanna-actionboard-modal" class="thanna-modal" style="display:none;">
    <div class="thanna-modal__backdrop"></div>
    <div class="thanna-modal__card">
        <button class="thanna-modal__close" aria-label="Close modal">✕</button>
        
        <div id="thanna-actionboard-app">
            <div v-cloak class="thanna-actionboard-container">
                <!-- Modal Top Brand -->
                <div class="modal-brand-header">
                    <h2>ActionBoard Live</h2>
                    <span class="pulse-indicator">
                        <span class="live-dot-pulse"></span> Live Center
                    </span>
                </div>

                <!-- Navigation Tabs -->
                <div class="tab-row">
                    <button :class="{ active: view === 'live' }" @click="view = 'live'">
                        🏏 Live Matches <span class="tab-badge" v-if="matches.length">@{{ matches.length }}</span>
                    </button>
                    <button :class="{ active: view === 'scores' }" @click="view = 'scores'" :disabled="!activeMatch">
                        📊 Live Scoreboard
                    </button>
                    <button :class="{ active: view === 'tourney' }" @click="view = 'tourney'">
                        🏆 Tournaments
                    </button>
                </div>

                <!-- Loading Spinner -->
                <div v-if="loading" class="spinner-container">
                    <div class="elegant-spinner"></div>
                    <p>Fetching real-time updates...</p>
                </div>

                <!-- View 1: Live Matches List -->
                <div v-if="view === 'live' && !loading" class="panel animate-fade-in">
                    <div class="panel-section-title">
                        <h3>Active Cricket Matches</h3>
                        <button class="refresh-action-btn" @click="fetchMatches">⟳ Refresh</button>
                    </div>

                    <div v-if="matches.length === 0" class="empty-matches-state">
                        <span class="empty-icon">📭</span>
                        <p>No active matches scheduled or live right now.</p>
                        <a href="/gamehub/actionboard" class="modal-premium-btn accent">Go to Match Center</a>
                    </div>

                    <div class="match-list" v-else>
                        <div class="match-item" v-for="m in matches" :key="m.id">
                            <div class="match-item__top">
                                <span :class="['modal-status-pill', m.status.toLowerCase() === 'live' ? 'live' : 'upcoming']">
                                    @{{ m.status }}
                                </span>
                                <span class="competition-tag">@{{ m.competition }}</span>
                                <span class="time-label">@{{ m.time }}</span>
                            </div>
                            
                            <div class="match-item__main">
                                <div class="team-block">
                                    <span class="team-label">@{{ m.home }}</span>
                                    <strong class="score-label" v-if="m.home_runs !== undefined">
                                        @{{ m.home_runs }}/@{{ m.home_wickets || 0 }}
                                        <small class="overs-text">(@{{ m.home_overs || 0 }} ov)</small>
                                    </strong>
                                    <strong class="score-label" v-else>0/0</strong>
                                </div>
                                
                                <div class="vs-divider">VS</div>
                                
                                <div class="team-block">
                                    <span class="team-label">@{{ m.away }}</span>
                                    <strong class="score-label" v-if="m.away_runs !== undefined">
                                        @{{ m.away_runs }}/@{{ m.away_wickets || 0 }}
                                        <small class="overs-text">(@{{ m.away_overs || 0 }} ov)</small>
                                    </strong>
                                    <strong class="score-label" v-else>0/0</strong>
                                </div>
                            </div>

                            <div class="match-item__footer">
                                <span class="venue-location">📍 @{{ m.venue }}</span>
                                <div class="action-buttons">
                                    <button class="modal-action-btn" @click="openScoreboard(m)">Open Score</button>
                                    <a :href="'/gamehub/actionboard/match/' + m.id" class="modal-link-btn">Match Room ➔</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- View 2: Live Detailed Scoreboard -->
                <div v-if="view === 'scores' && activeMatch && !loading" class="panel animate-fade-in">
                    <div class="scoreboard-header">
                        <button class="back-btn" @click="view = 'live'">← Back to Matches</button>
                        <span class="featured-comp">🏆 @{{ activeMatch.competition }} • @{{ activeMatch.venue }}</span>
                    </div>

                    <div class="scoreboard-main-card">
                        <!-- Hero Score display -->
                        <div class="scoreboard-hero">
                            <div class="hero-team">
                                <span class="hero-team-name">@{{ activeMatch.home }}</span>
                                <span class="hero-team-runs" v-if="activeMatch.home_runs !== undefined">
                                    @{{ activeMatch.home_runs }}/@{{ activeMatch.home_wickets || 0 }}
                                    <small class="hero-overs">(@{{ activeMatch.home_overs || 0 }} ov)</small>
                                </span>
                            </div>
                            
                            <div class="hero-versus">VS</div>
                            
                            <div class="hero-team">
                                <span class="hero-team-name">@{{ activeMatch.away }}</span>
                                <span class="hero-team-runs" v-if="activeMatch.away_runs !== undefined">
                                    @{{ activeMatch.away_runs }}/@{{ activeMatch.away_wickets || 0 }}
                                    <small class="hero-overs">(@{{ activeMatch.away_overs || 0 }} ov)</small>
                                </span>
                            </div>
                        </div>

                        <!-- Chasing Equation or result -->
                        <div class="equation-box" v-if="activeMatch.score_text">
                            🎯 @{{ activeMatch.score_text }}
                        </div>
                    </div>

                    <!-- Timeline & Ball commentary from database -->
                    <div class="timeline-container">
                        <h4>Ball-by-Ball Live Commentary</h4>
                        <div v-if="timeline.length === 0" class="empty-timeline">
                            <p>No ball deliveries logged in this session yet.</p>
                        </div>
                        <ul class="timeline-list" v-else>
                            <li class="timeline-item" v-for="e in timeline" :key="e.id">
                                <span class="time-badge">@{{ e.time }}</span>
                                <span class="timeline-commentary">@{{ e.text }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="modal-panel-actions">
                        <a :href="'/gamehub/actionboard/match/' + activeMatch.id" class="modal-premium-btn">
                            Enter Full Match Center
                        </a>
                    </div>
                </div>

                <!-- View 3: Tournaments Bracket Preview -->
                <div v-if="view === 'tourney' && !loading" class="panel animate-fade-in">
                    <div class="tourney-preview-box">
                        <span class="preview-trophy">🏆</span>
                        <h3>ActionBoard Premier Tournament</h3>
                        <p class="muted">Digital registration and dynamic bracket mapping will go online in the upcoming tournament phase.</p>
                        <div class="tourney-highlights">
                            <div class="highlight-pill">👥 16 Squads</div>
                            <div class="highlight-pill">🏏 Turf T20 Format</div>
                            <div class="highlight-pill">🏅 Cash Prize Pool</div>
                        </div>
                        <a href="/gamehub/actionboard" class="modal-premium-btn">Explore Live Fixtures</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* --------------------------------------------------
       CREX-inspired Premium Modal Styling for GameHub
    -------------------------------------------------- */
    .thanna-modal {
        position: fixed;
        inset: 0;
        z-index: 2500;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    
    .thanna-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(8, 18, 14, 0.65);
        backdrop-filter: blur(4px);
    }
    
    .thanna-modal__card {
        position: relative;
        z-index: 10;
        background: #ffffff;
        width: min(840px, 100%);
        max-height: 88vh;
        overflow-y: auto;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 25px 60px rgba(9, 55, 39, 0.25);
        border: 1px solid rgba(9, 55, 39, 0.1);
        display: flex;
        flex-direction: column;
    }
    
    .thanna-modal__close {
        position: absolute;
        right: 18px;
        top: 18px;
        border: 0;
        background: #f1f5f9;
        color: #64748b;
        font-size: 14px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .thanna-modal__close:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    
    [v-cloak] {
        display: none;
    }

    /* Modal Interior Layout */
    .modal-brand-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
    }

    .modal-brand-header h2 {
        font-size: 18px;
        font-weight: 900;
        color: #093727;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pulse-indicator {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 800;
        color: #ef4444;
        text-transform: uppercase;
    }

    .live-dot-pulse {
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        animation: liveDotAnimation 1.6s infinite ease-in-out;
    }

    @keyframes liveDotAnimation {
        0% { opacity: 0.6; transform: scale(0.9); }
        50% { opacity: 1; transform: scale(1.1); }
        100% { opacity: 0.6; transform: scale(0.9); }
    }

    /* Tab Switcher Rows */
    .tab-row {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 8px;
        overflow-x: auto;
    }
    
    .tab-row button {
        padding: 10px 16px;
        border-radius: 30px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .tab-row button.active {
        background: linear-gradient(135deg, #093727 0%, #0d543c 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(9, 55, 39, 0.15);
    }

    .tab-row button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .tab-badge {
        background: #ef4444;
        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 10px;
    }

    /* Panels & Animations */
    .panel {
        flex: 1;
    }

    .panel-section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .panel-section-title h3 {
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }

    .refresh-action-btn {
        background: none;
        border: none;
        color: #059669;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .refresh-action-btn:hover {
        color: #10b981;
    }

    /* Match Items & Grid */
    .match-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .match-item {
        border: 1px solid rgba(9, 55, 39, 0.08);
        border-radius: 12px;
        padding: 16px;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.01);
        transition: all 0.2s ease;
    }

    .match-item:hover {
        border-color: #10b981;
        box-shadow: 0 6px 15px rgba(9, 55, 39, 0.04);
    }

    .match-item__top {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        font-size: 11px;
    }

    .modal-status-pill {
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .modal-status-pill.live {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .modal-status-pill.upcoming {
        background: #f1f5f9;
        color: #64748b;
    }

    .competition-tag {
        font-weight: 700;
        color: #475569;
    }

    .time-label {
        margin-left: auto;
        color: #94a3b8;
        font-weight: 600;
    }

    .match-item__main {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid rgba(9, 55, 39, 0.02);
        margin-bottom: 12px;
    }

    .team-block {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
    }

    .team-block:last-child {
        align-items: flex-end;
    }

    .team-label {
        font-size: 13px;
        font-weight: 700;
        color: #334155;
    }

    .score-label {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
    }

    .vs-divider {
        font-size: 11px;
        font-weight: 800;
        color: #94a3b8;
        padding: 0 16px;
    }

    .match-item__footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .venue-location {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 50%;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .modal-action-btn {
        background: rgba(9, 55, 39, 0.05);
        color: #093727;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .modal-action-btn:hover {
        background: #093727;
        color: #ffffff;
    }

    .modal-link-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .modal-link-btn:hover {
        background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
    }

    /* Scoreboard Detail View */
    .scoreboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .back-btn {
        background: none;
        border: none;
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .back-btn:hover {
        color: #093727;
    }

    .featured-comp {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }

    .scoreboard-main-card {
        background: linear-gradient(135deg, #093727 0%, #0d543c 100%);
        border-radius: 12px;
        padding: 20px;
        color: #ffffff;
        box-shadow: 0 8px 24px rgba(9, 55, 39, 0.15);
        margin-bottom: 20px;
    }

    .scoreboard-hero {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .hero-team {
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .hero-team:last-child {
        align-items: flex-end;
    }

    .hero-team-name {
        font-size: 15px;
        font-weight: 850;
        color: #34d399;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .hero-team-runs {
        font-size: 22px;
        font-weight: 900;
    }

    .hero-overs {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
        margin-left: 2px;
    }

    .hero-versus {
        font-size: 14px;
        font-weight: 800;
        color: rgba(255, 255, 255, 0.35);
        padding: 0 16px;
    }

    .equation-box {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 800;
        color: #fbbf24;
        text-align: center;
    }

    /* Ball timeline container */
    .timeline-container h4 {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 12px;
    }

    .timeline-list {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
    }

    .timeline-item {
        display: flex;
        gap: 12px;
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
        align-items: flex-start;
    }

    .timeline-item:last-child {
        border-bottom: none;
    }

    .time-badge {
        background: #f1f5f9;
        color: #475569;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .timeline-commentary {
        color: #334155;
        line-height: 1.4;
    }

    .empty-timeline {
        padding: 20px;
        text-align: center;
        background: #f8fafc;
        border-radius: 8px;
        color: #64748b;
        font-size: 13px;
    }

    .modal-panel-actions {
        margin-top: 20px;
        display: flex;
        justify-content: flex-end;
    }

    .modal-premium-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        display: inline-block;
        transition: all 0.25s ease;
    }

    .modal-premium-btn:hover {
        background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.25);
    }

    .modal-premium-btn.accent {
        background: linear-gradient(135deg, #093727 0%, #0d543c 100%);
    }

    .modal-premium-btn.accent:hover {
        background: linear-gradient(135deg, #0d543c 0%, #111c18 100%);
        box-shadow: 0 5px 15px rgba(9, 55, 39, 0.2);
    }

    /* Tournaments tab preview */
    .tourney-preview-box {
        text-align: center;
        padding: 40px 20px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px dashed rgba(9, 55, 39, 0.15);
    }

    .preview-trophy {
        font-size: 48px;
        display: block;
        margin-bottom: 16px;
    }

    .tourney-preview-box h3 {
        font-size: 18px;
        font-weight: 900;
        color: #093727;
        margin: 0 0 10px;
    }

    .tourney-preview-box p {
        font-size: 14px;
        color: #475569;
        max-width: 480px;
        margin: 0 auto 24px;
        line-height: 1.6;
    }

    .tourney-highlights {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .highlight-pill {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }

    /* Loading and Spinners */
    .spinner-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }

    .elegant-spinner {
        width: 32px;
        height: 32px;
        border: 3px solid rgba(9, 55, 39, 0.08);
        border-top-color: #10b981;
        border-radius: 50%;
        animation: spin 0.8s infinite linear;
        margin-bottom: 12px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .empty-matches-state {
        text-align: center;
        padding: 30px;
        color: #64748b;
    }

    .empty-icon {
        font-size: 32px;
        display: block;
        margin-bottom: 10px;
    }

    /* Fade-in transitions */
    .animate-fade-in {
        animation: fadeInAnimation 0.3s ease;
    }

    @keyframes fadeInAnimation {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    </style>

    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script>
    (() => {
        const { createApp } = Vue;
        const app = createApp({
            data() {
                return {
                    view: 'live',
                    matches: [],
                    activeMatch: null,
                    timeline: [],
                    loading: false,
                    pollingInterval: null
                }
            },
            methods: {
                async fetchMatches() {
                    // Only show spinner on first load to make polling seamless
                    if (this.matches.length === 0) {
                        this.loading = true;
                    }
                    try {
                        const response = await fetch('/gamehub/actionboard/matches/json');
                        if (response.ok) {
                            this.matches = await response.json();
                        }
                    } catch (error) {
                        console.error("Error fetching live matches:", error);
                    } finally {
                        this.loading = false;
                    }
                },
                async openScoreboard(match) {
                    this.activeMatch = match;
                    this.view = 'scores';
                    await this.fetchActiveMatchDetails(match.id);
                },
                async fetchActiveMatchDetails(matchId) {
                    try {
                        const response = await fetch(`/gamehub/actionboard/match/${matchId}/json`);
                        if (response.ok) {
                            const data = await response.json();
                            this.activeMatch = data;
                            this.timeline = data.timeline || [];
                        }
                    } catch (error) {
                        console.error("Error fetching match details:", error);
                    }
                },
                async pollActiveMatch() {
                    if (this.view === 'scores' && this.activeMatch) {
                        await this.fetchActiveMatchDetails(this.activeMatch.id);
                    }
                },
                startPolling() {
                    // Poll matches and scoreboard every 4 seconds
                    this.pollingInterval = setInterval(async () => {
                        await this.fetchMatches();
                        await this.pollActiveMatch();
                    }, 4000);
                },
                stopPolling() {
                    if (this.pollingInterval) {
                        clearInterval(this.pollingInterval);
                        this.pollingInterval = null;
                    }
                }
            },
            mounted() {
                // Wire trigger selectors
                document.querySelectorAll('.thanna-trigger').forEach(el => {
                    el.addEventListener('click', async (e) => {
                        e.preventDefault();
                        document.getElementById('thanna-actionboard-modal').style.display = 'flex';
                        this.view = 'live';
                        await this.fetchMatches();
                        this.startPolling();
                    });
                });
                
                // Modal closing logic
                const closeModal = () => {
                    document.getElementById('thanna-actionboard-modal').style.display = 'none';
                    this.stopPolling();
                };
                
                document.querySelectorAll('.thanna-modal__close, .thanna-modal__backdrop').forEach(el => {
                    el.addEventListener('click', closeModal);
                });
            },
            unmounted() {
                this.stopPolling();
            }
        });
        app.mount('#thanna-actionboard-app');
    })();
    </script>
</div>
