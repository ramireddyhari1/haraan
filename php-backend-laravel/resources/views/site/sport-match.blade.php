@extends('site.layout')
@section('body_class', 'match-page-body')
@section('content')
@include('site.partials.match-helpers')
@php
    /*
     | Match detail for every sport that isn't cricket — football, basketball, kabaddi,
     | volleyball, tennis, table tennis, badminton. The web twin of the app's per-sport
     | screens: the same server payloads (detail.board / detail.football), the same
     | player-stats calculator the careers are built from, and the same insights.
     */
    $d = $detail;
    $sport = (string) ($d['sport'] ?? 'football');
    $board = is_array($d['board'] ?? null) ? $d['board'] : null;
    $fb = is_array($d['football'] ?? null) ? $d['football'] : null;
    $family = $board['family'] ?? ($sport === 'football' ? 'tally' : 'points');
    $isLive = (bool) ($d['isLive'] ?? false);
    $finished = $match->isFinished();

    $team1 = (string) ($d['team1Full'] ?? $d['team1'] ?? '');
    $team2 = (string) ($d['team2Full'] ?? $d['team2'] ?? '');
    $code1 = hrn_team_code((string) ($d['team1'] ?? $team1));
    $code2 = hrn_team_code((string) ($d['team2'] ?? $team2));
    $col1 = hrn_mono_color($team1);
    $col2 = hrn_mono_color($team2);
    $logo1 = hrn_team_icon($d['team1Logo'] ?? '', $d['team1Emblem'] ?? '');
    $logo2 = hrn_team_icon($d['team2Logo'] ?? '', $d['team2Emblem'] ?? '');

    $home = (int) $match->home_score;
    $away = (int) $match->away_score;
    $sportLabel = ucwords(str_replace('_', ' ', $sport));
    $setNoun = (string) ($board['set_noun'] ?? 'Set');
    $scoreUnit = match ($family) {
        'sets', 'tennis' => \Illuminate\Support\Str::plural($setNoun),
        'tally' => 'Goals',
        default => 'Points',
    };

    $result = \App\Services\Stats\MatchPlayerStatsCalculator::resultFor($match);
    $status = match (true) {
        $isLive => 'Live',
        $result === 'home' => $team1 . ' won',
        $result === 'away' => $team2 . ' won',
        $result === 'draw' => 'Match drawn',
        $finished => 'Completed',
        default => 'Starts soon',
    };
    $chips = [];
    if ($board) {
        if (!empty($board['match_point'])) $chips[] = ['Match point · ' . ($board['match_point'] === 'home' ? $code1 : $code2), 'hot'];
        elseif (!empty($board['set_point'])) $chips[] = [$setNoun . ' point · ' . ($board['set_point'] === 'home' ? $code1 : $code2), 'warm'];
        if (!empty($board['tiebreak'])) $chips[] = ['Tie-break', 'warm'];
        if (!empty($board['deuce'])) $chips[] = ['Deuce', 'cool'];
        if (!empty($board['golden_point'])) $chips[] = ['Golden point', 'hot'];
        if (($board['break_points'] ?? 0) > 0) $chips[] = [$board['break_points'] > 1 ? $board['break_points'] . ' break points' : 'Break point', 'hot'];
        if (!empty($board['do_or_die'])) $chips[] = ['Do-or-die raid', 'hot'];
        if (!empty($board['period_label']) && $isLive) $chips[] = [$board['period_label'], 'cool'];
    }
    if ($fb && !empty($fb['clock']['phase']) && $isLive) $chips[] = [ucwords(str_replace('_', ' ', $fb['clock']['phase'])), 'cool'];

    $serving = $board['serving'] ?? null;
    $tabLabels = ['summary' => 'Summary', 'timeline' => $family === 'tally' ? 'Timeline' : 'Play by play', 'players' => 'Players', 'insights' => 'Insights'];
    $url = fn (string $t) => route('site.gamehub.actionboard.match', ['id' => $match->id, 'tab' => $t]);

    // The stats a player row shows, in the sport's own order.
    $playerCols = match ($sport) {
        'football' => ['goals' => 'G', 'assists' => 'A', 'yellow_cards' => 'YC', 'red_cards' => 'RC'],
        'basketball' => ['points' => 'PTS', 'rebounds' => 'REB', 'assists' => 'AST', 'three_pointers' => '3PM', 'steals' => 'STL', 'fouls' => 'PF'],
        'kabaddi' => ['points' => 'PTS', 'raid_points' => 'RAID', 'bonus_points' => 'BON', 'tackle_points' => 'TKL', 'super_raids' => 'SR'],
        'tennis' => ['points_won' => 'PTS', 'aces' => 'ACE', 'double_faults' => 'DF', 'breaks' => 'BRK', 'games_won' => 'GMS'],
        default => ['points_won' => 'PTS', 'aces' => 'ACE', 'winners' => 'WIN', 'blocks' => 'BLK'],
    };
    $num = fn ($row, $k) => (int) (((array) ($row['stats'] ?? []))[$k] ?? 0);
@endphp

<div class="smx" data-sport="{{ $sport }}">
    <header class="smx-bar">
        <a href="{{ route('site.gamehub.actionboard') }}" class="smx-ic" aria-label="Back to ActionBoard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <div class="smx-bar-title">
            <span class="smx-bar-teams">{{ $code1 }} <span class="smx-vs-lite">vs</span> {{ $code2 }}</span>
            <span class="smx-sport">{{ $sportLabel }}</span>
            @if($isLive)<span class="smx-live"><span class="smx-live-dot"></span>LIVE</span>@endif
        </div>
    </header>

    {{-- Scoreboard --}}
    <section class="smx-hero" aria-label="Score">
        <div class="smx-hero-meta">{{ trim((string) ($d['formatLabel'] ?? '')) ?: $sportLabel }}@if(trim((string) ($d['venue'] ?? '')) !== '') · {{ $d['venue'] }}@endif</div>
        <div class="smx-hero-row">
            @foreach([['home', $team1, $code1, $col1, $logo1, $home], ['away', $team2, $code2, $col2, $logo2, $away]] as [$side, $name, $code, $col, $logo, $score])
                <div class="smx-team {{ $side === 'away' ? 'smx-team-r' : '' }}">
                    <div class="smx-crest" @if($logo==='')style="background:{{ $col }}"@endif>
                        @if($logo !== '')<img src="{{ $logo }}" alt="">@else<span>{{ $code }}</span>@endif
                    </div>
                    <div class="smx-team-name" title="{{ $name }}">
                        {{ $name }}
                        @if($serving === $side)<span class="smx-serve" title="Serving" aria-label="Serving"></span>@endif
                        @if(($board['raiding'] ?? null) === $side)<span class="smx-raid">RAIDING</span>@endif
                    </div>
                    <div class="smx-score">{{ $score }}</div>
                </div>
                @if($side === 'home')
                    <div class="smx-mid">
                        <span class="smx-unit">{{ $scoreUnit }}</span>
                        @if($board && is_array($board['current'] ?? null))
                            <span class="smx-current">{{ $board['current'][0] }}–{{ $board['current'][1] }}</span>
                            <span class="smx-unit">this {{ strtolower($setNoun) }}</span>
                        @elseif($board && is_array($board['points'] ?? null) && $sport === 'tennis' && empty($board['decided']))
                            <span class="smx-current">{{ ($board['games'][0] ?? 0) }}–{{ ($board['games'][1] ?? 0) }}</span>
                            <span class="smx-unit">{{ $board['tiebreak'] ? 'TB ' : '' }}{{ $board['points'][0] }}–{{ $board['points'][1] }}</span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
        <div class="smx-status">{{ $status }}</div>
        @if($chips)
            <div class="smx-chips">@foreach($chips as [$label, $tone])<span class="smx-chip smx-chip-{{ $tone }}">{{ $label }}</span>@endforeach</div>
        @endif
    </section>

    <nav class="smx-tabs" role="tablist" aria-label="Match sections">
        @foreach($tabs as $t)
            <a href="{{ $url($t) }}" class="smx-tab {{ $tab === $t ? 'is-on' : '' }}" role="tab" aria-selected="{{ $tab === $t ? 'true' : 'false' }}">{{ $tabLabels[$t] }}</a>
        @endforeach
    </nav>

    <div class="smx-content">
    @if($tab === 'summary')
        {{-- Set / game grid --}}
        @if($board && in_array($family, ['sets', 'tennis'], true) && (count($board['sets'] ?? []) || is_array($board['current'] ?? null) || is_array($board['games'] ?? null)))
            <div class="smx-card smx-card-flush">
                <div class="smx-table-wrap">
                <table class="smx-grid">
                    <thead><tr><th scope="col">Team</th>
                        @foreach($board['sets'] ?? [] as $i => $s)<th scope="col">{{ substr($setNoun, 0, 1) }}{{ $i + 1 }}</th>@endforeach
                        @if(empty($board['decided']))<th scope="col" class="now">Now</th>@endif
                    </tr></thead>
                    <tbody>
                    @foreach([0 => $code1, 1 => $code2] as $idx => $code)
                        <tr><th scope="row">{{ $code }}</th>
                            @foreach($board['sets'] ?? [] as $i => $s)
                                <td class="{{ $s[$idx] > $s[1 - $idx] ? 'won' : '' }}">{{ $s[$idx] }}@if(isset($board['tiebreaks'][$i]) && $board['tiebreaks'][$i] !== null && $s[$idx] < $s[1 - $idx])<sup>{{ $board['tiebreaks'][$i] }}</sup>@endif</td>
                            @endforeach
                            @if(empty($board['decided']))
                                <td class="now">{{ $sport === 'tennis' ? ($board['games'][$idx] ?? 0) : ($board['current'][$idx] ?? 0) }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif

        {{-- Period breakdown --}}
        @if($board && $family === 'points' && count($board['periods'] ?? []) > 0)
            <div class="smx-card smx-card-flush">
                <div class="smx-table-wrap">
                <table class="smx-grid">
                    <thead><tr><th scope="col">Team</th>
                        @foreach($board['periods'] as $i => $p)<th scope="col">{{ $sport === 'basketball' ? ($i < ($board['regulation_periods'] ?? 4) ? 'Q' . ($i + 1) : 'OT') : 'H' . ($i + 1) }}</th>@endforeach
                        <th scope="col">T</th>
                    </tr></thead>
                    <tbody>
                    @foreach([0 => [$code1, $home], 1 => [$code2, $away]] as $idx => [$code, $total])
                        <tr><th scope="row">{{ $code }}</th>
                            @foreach($board['periods'] as $p)<td>{{ $p[$idx] }}</td>@endforeach
                            <td class="won">{{ $total }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
                @if($sport === 'basketball' && is_array($board['team_fouls'] ?? null))
                    <div class="smx-foot">Team fouls this quarter: {{ $code1 }} {{ $board['team_fouls'][0] }} · {{ $code2 }} {{ $board['team_fouls'][1] }}</div>
                @endif
                @if($sport === 'kabaddi' && is_array($board['mat'] ?? null))
                    <div class="smx-foot">On the mat: {{ $code1 }} {{ $board['mat']['on'][0] }}/{{ $board['mat']['size'] }} · {{ $code2 }} {{ $board['mat']['on'][1] }}/{{ $board['mat']['size'] }}</div>
                @endif
            </div>
        @endif

        {{-- Football scorers --}}
        @if($fb)
            <div class="smx-card">
                <div class="smx-eyebrow">Goals</div>
                <div class="smx-two">
                    @foreach(['home_scorers' => 'l', 'away_scorers' => 'r'] as $key => $align)
                        <ul class="smx-scorers smx-{{ $align }}">
                            @forelse($fb[$key] ?? [] as $sc)
                                <li>{{ $sc['name'] }} <span>{{ implode(', ', $sc['minutes'] ?? []) }}</span></li>
                            @empty
                                <li class="smx-muted">—</li>
                            @endforelse
                        </ul>
                    @endforeach
                </div>
            </div>
            @if(!empty($fb['stats']['has_any']))
                <div class="smx-card">
                    @foreach($fb['stats']['groups'] as $g)
                        <div class="smx-eyebrow">{{ $g['title'] }}</div>
                        @foreach($g['rows'] as $r)
                            @php $tot = max(1, $r['home'] + $r['away']); @endphp
                            <div class="smx-statrow">
                                <b>{{ $r['home'] }}</b><span>{{ $r['label'] }}</span><b>{{ $r['away'] }}</b>
                                <div class="smx-statbar"><i style="width:{{ round($r['home'] * 100 / $tot) }}%"></i></div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Top performers from the same player stats as careers --}}
        @php
            $leadKey = array_key_first($playerCols);
            $top = collect($players)->filter(fn ($p) => $num($p, $leadKey) > 0)->sortByDesc(fn ($p) => $num($p, $leadKey))->take(4);
        @endphp
        @if($top->isNotEmpty())
            <div class="smx-card">
                <div class="smx-eyebrow">Top performers</div>
                @foreach($top as $p)
                    <div class="smx-perf">
                        <span class="smx-dot smx-dot-{{ $p['side'] }}" aria-hidden="true"></span>
                        <span class="smx-perf-name">{{ $p['name'] }}</span>
                        <span class="smx-perf-val">{{ $num($p, $leadKey) }} <em>{{ $playerCols[$leadKey] }}</em></span>
                    </div>
                @endforeach
                <a class="smx-more" href="{{ $url('players') }}">All players</a>
            </div>
        @endif

        @if($matchAd)
            <a class="smx-ad" href="{{ route('site.ad.click', $matchAd->id) }}" rel="sponsored noopener">
                @if($matchAd->image_url)<img src="{{ $matchAd->image_url }}" alt="" loading="lazy">@endif
                <span class="smx-ad-body">
                    <span class="smx-ad-label">Sponsored{{ $matchAd->sponsor ? ' · ' . $matchAd->sponsor : '' }}</span>
                    <strong>{{ $matchAd->title }}</strong>
                    @if($matchAd->subtitle)<span>{{ $matchAd->subtitle }}</span>@endif
                </span>
                @if($matchAd->cta_text)<span class="smx-ad-cta">{{ $matchAd->cta_text }}</span>@endif
            </a>
        @endif

        @if(!$board && !$fb)
            <div class="smx-empty">Nothing has been scored yet.</div>
        @endif

    @elseif($tab === 'timeline')
        @php
            $rows = $fb
                ? array_reverse($fb['timeline'] ?? [])
                : ($board['feed'] ?? []);
        @endphp
        <div class="smx-card smx-card-flush">
            @forelse($rows as $r)
                @php
                    $side = $r['side'] ?? null;
                    $tags = $r['tags'] ?? [];
                    $text = $fb
                        ? ($r['headline'] ?? '')
                        : trim(match ($r['kind'] ?? '') {
                            'point' => ($r['player'] ? $r['player'] . ' · ' : '') . ($r['value'] > 1 ? '+' . $r['value'] . ' ' : '') . ucwords(str_replace(['_', ':'], [' ', ' '], (string) ($r['detail'] ?: 'point'))),
                            'raid' => ($r['player'] ? $r['player'] . ' · ' : '') . 'Empty raid',
                            'period' => 'End of period',
                            'timeout' => 'Timeout',
                            'serve' => 'Serve',
                            default => ($r['player'] ? $r['player'] . ' · ' : '') . ucwords((string) $r['kind']),
                        });
                @endphp
                <div class="smx-feed">
                    <span class="smx-feed-at">{{ $fb ? ($r['minute_label'] ?? '') : ($r['line'] ?? '') }}</span>
                    <span class="smx-dot smx-dot-{{ $side ?: 'none' }}" aria-hidden="true"></span>
                    <span class="smx-feed-text">{{ $text }}
                        @foreach($tags as $tg)<span class="smx-tag">{{ str_replace('_', ' ', $tg) }}</span>@endforeach
                    </span>
                    <span class="smx-feed-score">{{ $r['home_score'] ?? '' }}–{{ $r['away_score'] ?? '' }}</span>
                </div>
            @empty
                <div class="smx-empty">No events recorded yet.</div>
            @endforelse
        </div>

    @elseif($tab === 'players')
        @foreach(['home' => $team1, 'away' => $team2] as $side => $teamName)
            @php $rows = collect($players)->where('side', $side)->sortByDesc(fn ($p) => $num($p, array_key_first($playerCols))); @endphp
            <div class="smx-card smx-card-flush">
                <div class="smx-card-head">{{ $teamName }}</div>
                @if($rows->isEmpty())
                    <div class="smx-empty">No squad entered.</div>
                @else
                <div class="smx-table-wrap">
                <table class="smx-grid smx-players">
                    <thead><tr><th scope="col">Player</th>@foreach($playerCols as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($rows as $p)
                        <tr>
                            <th scope="row">
                                @if(!empty($p['playerId']))<a href="{{ route('site.player.profile', $p['playerId']) }}">{{ $p['name'] }}</a>@else{{ $p['name'] }}@endif
                                @if(empty($p['played']))<span class="smx-guest">not in squad</span>@endif
                            </th>
                            @foreach($playerCols as $k => $label)<td>{{ $num($p, $k) ?: '–' }}</td>@endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
                @endif
            </div>
        @endforeach
        <p class="smx-note">Only what the scorer recorded is shown. A dash means nothing was recorded, not zero.</p>

    @elseif($tab === 'insights')
        @php $in = $insights ?? []; $flow = $in['flow'] ?? []; @endphp
        @if(($in['moments'] ?? 0) === 0)
            <div class="smx-empty">Insights appear once the match has scoring moments.</div>
        @else
            <div class="smx-card">
                <div class="smx-eyebrow">Match flow</div>
                <div class="smx-figs">
                    <div><b>{{ $flow['lead_changes'] ?? 0 }}</b><span>Lead changes</span></div>
                    <div><b>{{ $flow['ties'] ?? 0 }}</b><span>Times level</span></div>
                    <div><b>{{ $flow['biggest_lead']['margin'] ?? 0 }}</b><span>Biggest lead</span></div>
                    <div><b>{{ $flow['longest_run']['value'] ?? 0 }}</b><span>Longest run</span></div>
                </div>
            </div>
            @if(!empty($in['reads']))
                <div class="smx-card">
                    <div class="smx-eyebrow">The story</div>
                    <ul class="smx-reads">@foreach($in['reads'] as $read)<li>{{ $read }}</li>@endforeach</ul>
                </div>
            @endif
            @if(!empty($in['players']))
                <div class="smx-card">
                    <div class="smx-eyebrow">Who made the difference</div>
                    @foreach(array_slice($in['players'], 0, 6) as $p)
                        <div class="smx-perf">
                            <span class="smx-dot smx-dot-{{ $p['side'] }}" aria-hidden="true"></span>
                            <span class="smx-perf-name">{{ $p['name'] }}</span>
                            <span class="smx-perf-val">{{ $p['headline'] }} <em>{{ $p['headline_label'] }}</em></span>
                            <span class="smx-share" title="Share of team total"><i style="width:{{ (int) $p['share'] }}%"></i></span>
                        </div>
                    @endforeach
                </div>
            @endif
            @if(!empty($in['untracked']))
                <p class="smx-note">Not recorded in this match: {{ implode(', ', $in['untracked']) }}.</p>
            @endif
        @endif
    @endif
    </div>
</div>

<style>
header.topbar { display: none !important; }
main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
.match-page-body .mfoot, .match-page-body footer { display: none !important; }

.smx {
    --bg: #F4F7FB; --surface: #FFFFFF; --border: #E2E8F0;
    --ink: #0F172A; --ink2: #475569; --muted: #94A3B8;
    --home: #1D4ED8; --away: #475569; --live: #EF4444; --win: #16A34A; --warm: #B45309;
    background: var(--bg); min-height: 100vh; color: var(--ink); padding-bottom: 40px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.smx * { box-sizing: border-box; }
.smx-bar, .smx-hero, .smx-tabs, .smx-content { max-width: 720px; margin-left: auto; margin-right: auto; }
.smx-bar { display: flex; align-items: center; gap: 12px; padding: 10px 16px; position: sticky; top: 0; z-index: 30; background: var(--bg); }
.smx-ic { width: 38px; height: 38px; border-radius: 12px; background: #F1F5FA; border: 1px solid #E4EAF1; display: inline-flex; align-items: center; justify-content: center; color: #334155; }
.smx-ic:focus-visible, .smx-tab:focus-visible, .smx-more:focus-visible, .smx-ad:focus-visible { outline: 2px solid var(--home); outline-offset: 2px; }
.smx-ic svg { width: 19px; height: 19px; }
.smx-bar-title { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
.smx-bar-teams { font-size: 16px; font-weight: 800; white-space: nowrap; }
.smx-vs-lite { color: var(--muted); font-weight: 600; }
.smx-sport { font-size: 11px; font-weight: 700; color: var(--ink2); background: #E8EEF6; padding: 3px 8px; border-radius: 999px; }
.smx-live { display: inline-flex; align-items: center; gap: 5px; background: #FCE6E6; color: var(--live); font-size: 10px; font-weight: 800; letter-spacing: .5px; padding: 4px 7px; border-radius: 7px; }
.smx-live-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--live); animation: smxPulse .85s ease-in-out infinite alternate; }
@keyframes smxPulse { from { opacity: 1 } to { opacity: .35 } }

.smx-hero { margin-top: 6px; padding: 16px; background: linear-gradient(180deg, #D3EAF8 0%, #AFD2EC 100%); border-radius: 22px; margin-inline: 16px; }
@media (min-width: 752px) { .smx-hero { margin-inline: auto; } }
.smx-hero-meta { text-align: center; color: rgba(51,65,85,.8); font-size: 11px; font-weight: 600; }
.smx-hero-row { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8px; align-items: center; margin-top: 12px; }
.smx-team { display: flex; flex-direction: column; min-width: 0; gap: 6px; }
.smx-team-r { align-items: flex-end; text-align: right; }
.smx-crest { width: 44px; height: 44px; border-radius: 50%; background: #fff; border: 1.5px solid #CBD5E1; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.smx-crest img { width: 100%; height: 100%; object-fit: cover; }
.smx-crest span { color: #fff; font-size: 13px; font-weight: 800; }
.smx-team-name { font-size: 13px; font-weight: 700; color: #1E293B; display: flex; gap: 6px; align-items: center; max-width: 100%; overflow-wrap: anywhere; }
.smx-team-r .smx-team-name { justify-content: flex-end; }
.smx-serve { width: 8px; height: 8px; border-radius: 50%; background: #F59E0B; box-shadow: 0 0 0 3px rgba(245,158,11,.25); flex: 0 0 auto; }
.smx-raid { font-size: 9px; font-weight: 800; letter-spacing: .6px; color: #fff; background: var(--live); border-radius: 5px; padding: 2px 5px; }
.smx-score { font-size: 42px; font-weight: 900; letter-spacing: -1px; line-height: 1; font-variant-numeric: tabular-nums; }
.smx-team:not(.smx-team-r) .smx-score { color: #0D47A1; }
.smx-mid { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 0 4px; }
.smx-unit { font-size: 10px; font-weight: 700; color: rgba(51,65,85,.6); text-transform: uppercase; letter-spacing: 1px; }
.smx-current { font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; }
.smx-status { text-align: center; margin-top: 12px; font-size: 13px; font-weight: 700; color: #0F172A; }
.smx-chips { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-top: 10px; }
.smx-chip { font-size: 11px; font-weight: 700; border-radius: 999px; padding: 4px 10px; background: #fff; }
.smx-chip-hot { color: #B91C1C; } .smx-chip-warm { color: var(--warm); } .smx-chip-cool { color: #1E40AF; }

.smx-tabs { display: flex; position: sticky; top: 58px; z-index: 20; background: var(--bg); border-bottom: 1px solid var(--border); margin-top: 10px; overflow-x: auto; }
.smx-tab { flex: 1; text-align: center; padding: 12px 10px; font-size: 13px; font-weight: 600; color: var(--ink2); text-decoration: none; position: relative; white-space: nowrap; }
.smx-tab.is-on { color: var(--ink); }
.smx-tab.is-on::after { content: ""; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%); width: 24px; height: 2px; border-radius: 2px; background: var(--live); }

.smx-content { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
.smx-card { background: var(--surface); border: 1px solid #EEF2F7; border-radius: 16px; padding: 16px; }
.smx-card-flush { padding: 0; overflow: hidden; }
.smx-card-head { padding: 12px 16px; font-weight: 800; font-size: 14px; border-bottom: 1px solid var(--border); }
.smx-eyebrow { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }
.smx-table-wrap { overflow-x: auto; }
.smx-grid { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
.smx-grid th, .smx-grid td { padding: 10px 12px; text-align: center; font-size: 13px; border-bottom: 1px solid #F1F5F9; }
.smx-grid thead th { font-size: 10px; color: var(--muted); font-weight: 700; letter-spacing: .5px; }
.smx-grid tbody th { text-align: left; font-weight: 700; }
.smx-grid td.won { font-weight: 800; color: var(--ink); }
.smx-grid td, .smx-grid th.now, .smx-grid td.now { color: var(--ink2); }
.smx-grid td.now { background: #F8FAFC; font-weight: 800; color: var(--home); }
.smx-grid sup { font-size: 9px; margin-left: 1px; }
.smx-players tbody th a { color: var(--ink); text-decoration: none; }
.smx-players tbody th a:hover { text-decoration: underline; }
.smx-foot { padding: 10px 16px; font-size: 12px; color: var(--ink2); }
.smx-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.smx-scorers { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; font-size: 13px; font-weight: 600; }
.smx-scorers span { color: var(--muted); font-weight: 500; }
.smx-r { text-align: right; }
.smx-statrow { display: grid; grid-template-columns: 32px 1fr 32px; align-items: center; gap: 8px; margin: 6px 0 12px; font-size: 13px; }
.smx-statrow span { text-align: center; color: var(--ink2); }
.smx-statrow b:last-of-type { text-align: right; }
.smx-statbar { grid-column: 1 / -1; height: 5px; border-radius: 3px; background: #CBD5E1; overflow: hidden; }
.smx-statbar i { display: block; height: 100%; background: var(--home); }
.smx-perf { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #F1F5F9; }
.smx-perf:last-of-type { border-bottom: 0; }
.smx-perf-name { flex: 1; min-width: 0; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
.smx-perf-val { font-weight: 800; font-variant-numeric: tabular-nums; }
.smx-perf-val em { font-style: normal; font-size: 10px; color: var(--muted); font-weight: 700; }
.smx-share { width: 56px; height: 5px; border-radius: 3px; background: #E2E8F0; overflow: hidden; }
.smx-share i { display: block; height: 100%; background: var(--home); }
.smx-dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 auto; background: #CBD5E1; }
.smx-dot-home { background: var(--home); } .smx-dot-away { background: var(--away); }
.smx-more { display: inline-block; margin-top: 8px; font-size: 13px; font-weight: 700; color: var(--home); text-decoration: none; }
.smx-feed { display: grid; grid-template-columns: 58px 10px 1fr auto; align-items: center; gap: 10px; padding: 11px 16px; border-bottom: 1px solid #F1F5F9; font-size: 13px; }
.smx-feed-at { color: var(--muted); font-size: 11px; font-weight: 700; font-variant-numeric: tabular-nums; }
.smx-feed-text { min-width: 0; overflow-wrap: anywhere; }
.smx-feed-score { font-weight: 800; font-variant-numeric: tabular-nums; color: var(--ink2); }
.smx-tag { display: inline-block; margin-left: 6px; font-size: 10px; font-weight: 700; color: var(--warm); background: #FEF3C7; border-radius: 5px; padding: 1px 6px; text-transform: capitalize; }
.smx-guest { margin-left: 6px; font-size: 9px; font-weight: 700; color: var(--ink2); background: var(--border); padding: 1px 5px; border-radius: 5px; }
.smx-figs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; text-align: center; }
.smx-figs b { display: block; font-size: 22px; font-weight: 900; font-variant-numeric: tabular-nums; }
.smx-figs span { font-size: 10px; color: var(--muted); font-weight: 700; }
@media (max-width: 420px) { .smx-figs { grid-template-columns: repeat(2, 1fr); } .smx-score { font-size: 34px; } }
.smx-reads { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 6px; font-size: 14px; }
.smx-ad { display: flex; align-items: center; gap: 12px; background: var(--surface); border: 1px solid #EEF2F7; border-radius: 16px; padding: 12px; text-decoration: none; color: var(--ink); }
.smx-ad img { width: 64px; height: 64px; object-fit: cover; border-radius: 10px; max-width: 100%; }
.smx-ad-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; font-size: 13px; }
.smx-ad-label { font-size: 10px; color: var(--muted); font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
.smx-ad-cta { font-size: 12px; font-weight: 800; color: #fff; background: var(--home); border-radius: 10px; padding: 8px 12px; white-space: nowrap; }
.smx-empty { text-align: center; color: var(--muted); font-size: 13px; padding: 26px 12px; }
.smx-note { font-size: 12px; color: var(--muted); margin: 0 4px; }
.smx-muted { color: var(--muted); }
@media (prefers-reduced-motion: reduce) { .smx-live-dot { animation: none; } }
</style>
@if($isLive)
<script>
    // A live board refreshes itself, like the app's.
    setTimeout(function () { location.reload(); }, 20000);
</script>
@endif
@endsection
