@extends('site.actionboard-match-layout')
@section('match_content')
@php
    $homeTeam = $match['home'];
    $awayTeam = $match['away'];
    $decision = $match['decision'] ?? '';
@endphp
<section class="match-grid">
    <div class="match-main">
        <article id="match-info" class="match-card match-card--stats">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Match info</p>
                    <h2>Teams & venue</h2>
                </div>
            </div>
            <div class="match-teams">
                <div class="team-detail">
                    <span>Home team</span>
                    <strong>{{ $match['homeFull'] ?? $match['home_full'] ?? $homeTeam }}</strong>
                    <em>{{ $homeTeam }}</em>
                </div>
                <div class="team-detail" style="margin-top: 12px;">
                    <span>Away team</span>
                    <strong>{{ $match['awayFull'] ?? $match['away_full'] ?? $awayTeam }}</strong>
                    <em>{{ $awayTeam }}</em>
                </div>
            </div>
            <div class="fact-list" style="margin-top: 20px;">
                <div class="fact-row"><span>Venue</span><strong>{{ $match['venue'] }}</strong></div>
                <div class="fact-row"><span>Competition</span><strong>{{ $match['competition'] }}</strong></div>
                <div class="fact-row"><span>Toss</span><strong>{{ $decision }}</strong></div>
            </div>
        </article>

        @php
            $homeSquad = $match['home_squad'] ?? [
                ['name' => 'Player 1', 'role' => 'WK'], ['name' => 'Player 2', 'role' => 'BAT'], ['name' => 'Player 3', 'role' => 'BAT'],
                ['name' => 'Player 4', 'role' => 'AR'], ['name' => 'Player 5', 'role' => 'AR'], ['name' => 'Player 6', 'role' => 'AR'],
                ['name' => 'Player 7', 'role' => 'BOWL'], ['name' => 'Player 8', 'role' => 'BOWL'], ['name' => 'Player 9', 'role' => 'BOWL'],
                ['name' => 'Player 10', 'role' => 'BOWL'], ['name' => 'Player 11', 'role' => 'BOWL']
            ];
            $awaySquad = $match['away_squad'] ?? [
                ['name' => 'Player A', 'role' => 'WK'], ['name' => 'Player B', 'role' => 'BAT'], ['name' => 'Player C', 'role' => 'BAT'],
                ['name' => 'Player D', 'role' => 'AR'], ['name' => 'Player E', 'role' => 'AR'], ['name' => 'Player F', 'role' => 'AR'],
                ['name' => 'Player G', 'role' => 'BOWL'], ['name' => 'Player H', 'role' => 'BOWL'], ['name' => 'Player I', 'role' => 'BOWL'],
                ['name' => 'Player J', 'role' => 'BOWL'], ['name' => 'Player K', 'role' => 'BOWL']
            ];
        @endphp

        <article class="match-card" style="margin-top: 24px;">
            <div class="match-card__heading">
                <div>
                    <p class="match-kicker">Playing XI</p>
                    <h2>Squads</h2>
                </div>
            </div>
            
            <div class="squad-grid">
                <div class="squad-column">
                    <h3 class="squad-team-title" style="color: #34d399 !important; border-bottom-color: rgba(16, 185, 129, 0.15) !important;">
                        {{ $homeTeam }}
                    </h3>
                    <ul class="squad-list">
                        @foreach($homeSquad as $player)
                            <li class="squad-player">
                                <div class="squad-player__avatar">
                                    {{ strtoupper(substr(is_array($player) ? $player['name'] : $player, 0, 1)) }}
                                </div>
                                <div class="squad-player__info">
                                    <span class="squad-player__name">{{ is_array($player) ? $player['name'] : $player }}</span>
                                    @if(is_array($player) && isset($player['role']))
                                        <span class="squad-player__role">{{ $player['role'] }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="squad-column">
                    <h3 class="squad-team-title" style="color: #60a5fa !important; border-bottom-color: rgba(59, 130, 246, 0.15) !important;">
                        {{ $awayTeam }}
                    </h3>
                    <ul class="squad-list">
                        @foreach($awaySquad as $player)
                            <li class="squad-player">
                                <div class="squad-player__avatar squad-player__avatar--away">
                                    {{ strtoupper(substr(is_array($player) ? $player['name'] : $player, 0, 1)) }}
                                </div>
                                <div class="squad-player__info">
                                    <span class="squad-player__name">{{ is_array($player) ? $player['name'] : $player }}</span>
                                    @if(is_array($player) && isset($player['role']))
                                        <span class="squad-player__role" style="color: #60a5fa !important; background: rgba(59, 130, 246, 0.12) !important; border: 1px solid rgba(59, 130, 246, 0.2) !important;">{{ $player['role'] }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </article>
    </div>
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

<style>
.squad-column {
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 12px !important;
    padding: 12px !important;
    box-shadow: none !important;
}
.squad-team-title {
    font-size: 14px !important;
    font-weight: 800 !important;
    border-bottom: 1.5px solid #E2E8F0 !important;
    padding-bottom: 8px !important;
    margin-bottom: 12px !important;
    letter-spacing: 0.5px;
}
.squad-player {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px !important;
    border-bottom: 1px solid #F1F5F9 !important;
    transition: all 0.2s ease !important;
    border-radius: 6px;
}
.squad-player:hover {
    background: rgba(0, 210, 106, 0.05) !important;
    padding-left: 12px !important;
}
.squad-player__avatar {
    width: 26px !important;
    height: 26px !important;
    border-radius: 50% !important;
    background: #00D26A !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    display: grid;
    place-items: center;
}
.squad-player__avatar--away {
    background: #3b82f6 !important;
    color: #ffffff !important;
}
.squad-player__name {
    font-size: 13px !important;
    color: #0F172A !important;
    font-weight: 600 !important;
}
.squad-player__role {
    font-size: 9px !important;
    font-weight: 800 !important;
    color: #00D26A !important;
    background: rgba(0, 210, 106, 0.08) !important;
    border: 1px solid rgba(0, 210, 106, 0.15) !important;
    padding: 1px 6px !important;
    border-radius: 8px !important;
    width: fit-content;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.match-card {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 16px !important;
    padding: 14px 18px 12px !important;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03) !important;
}
.fact-row {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 16px !important;
    border-bottom: 1px solid #F1F5F9 !important;
    padding: 10px 0 !important;
}
.fact-row span {
    color: #64748B !important;
    flex-shrink: 0;
}
.fact-row strong {
    color: #0F172A !important;
    text-align: right;
}
.fixture-item {
    border: 1px solid #E2E8F0 !important;
    border-radius: 10px !important;
    background: #F8FAFC !important;
    box-shadow: none !important;
    padding: 10px 12px !important;
    transition: all 0.2s ease !important;
}
.fixture-item:hover {
    border-color: #CBD5E1 !important;
    transform: translateY(-1px) !important;
    background: #F1F5F9 !important;
}
.fixture-item div strong {
    color: #0F172A !important;
}
.fixture-item div span {
    color: #64748B !important;
}
.fixture-item em {
    color: #00D26A !important;
}
</style>
@endsection
