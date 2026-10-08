@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    // The app's MVP tab (MvpTab.kt). The ranking is LiveMatchController::buildMvp — the
    // same replayed ball log as the scorecard — so nothing here is computed twice.
    $d = $detail;
    $players = is_array($d['mvp'] ?? null) ? array_values($d['mvp']) : [];
    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];
    $isLive = (bool) ($d['isLive'] ?? false);

    $teamName = fn (array $p) => (string) (($p['teamName'] ?? '') !== '' ? $p['teamName'] : ((int) ($p['team'] ?? 1) === 2 ? ($d['team2'] ?? '') : ($d['team1'] ?? '')));
    $accent = fn (array $p) => hrn_mono_color($teamName($p));
    $teamLogo = fn (array $p) => (int) ($p['team'] ?? 1) === 2
        ? hrn_team_icon($d['team2Logo'] ?? '', $d['team2Emblem'] ?? '')
        : hrn_team_icon($d['team1Logo'] ?? '', $d['team1Emblem'] ?? '');
    $didBat = fn (array $p) => (int) ($p['ballsFaced'] ?? 0) > 0;
    $didBowl = fn (array $p) => (int) ($p['ballsBowled'] ?? 0) > 0;
    $role = function (array $p) use ($didBat): string {
        $bat = (int) ($p['batPoints'] ?? 0); $bowl = (int) ($p['bowlPoints'] ?? 0);
        return match (true) {
            $bat > 0 && $bowl > 0 => 'ALL-ROUND',
            $bowl > 0 && ! $didBat($p) => 'BOWLER',
            $bowl > $bat => 'BOWLER',
            default => 'BATTER',
        };
    };
    // "Kadapa Kings" -> "Kadapa Kings'", "Nellore XI" -> "Nellore XI's".
    $possessive = fn (string $n) => str_ends_with(mb_strtolower($n), 's') ? $n."'" : $n."'s";
    $initials = function (string $name): string {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($parts, 0, 2))));
    };
    // Context in words, derived from the innings cards on screen — never asserted.
    $context = function (array $p) use ($cards, $didBat, $didBowl, $teamName, $possessive): ?string {
        $parts = [];
        $team = (int) ($p['team'] ?? 1);
        if ($didBat($p) && (int) $p['runs'] > 0) {
            $teamRuns = array_sum(array_map(fn ($c) => (int) ($c['battingTeam'] ?? 1) === $team ? (int) ($c['runs'] ?? 0) : 0, $cards));
            if ($teamRuns > 0) {
                $share = (int) round($p['runs'] * 100 / $teamRuns);
                if ($share >= 10) $parts[] = $share.'% of '.$possessive($teamName($p) ?: 'their side').' runs';
            }
        }
        if ($didBowl($p) && (int) $p['wickets'] > 0) {
            $fell = array_sum(array_map(fn ($c) => (int) ($c['battingTeam'] ?? 1) !== $team ? (int) ($c['wickets'] ?? 0) : 0, $cards));
            $w = (int) $p['wickets'];
            $parts[] = $fell > 0 ? "{$w} of the {$fell} wickets to fall" : $w.' wicket'.($w === 1 ? '' : 's');
        }
        return $parts ? implode('  ·  ', $parts) : null;
    };

    $leader = $players[0] ?? null;
    $bestBat = null; $bestBowl = null;
    foreach ($players as $p) {
        if ($didBat($p) && (int) $p['batPoints'] > 0 && ($bestBat === null || $p['batPoints'] > $bestBat['batPoints'])) $bestBat = $p;
        if ($didBowl($p) && (int) $p['bowlPoints'] > 0 && ($bestBowl === null || $p['bowlPoints'] > $bestBowl['bowlPoints'])) $bestBowl = $p;
    }
    $topRuns = max(array_map(fn ($p) => (int) ($p['runs'] ?? 0), $players ?: [['runs' => 0]]));
    $topWkts = max(array_map(fn ($p) => (int) ($p['wickets'] ?? 0), $players ?: [['wickets' => 0]]));
    $alsoBest = [];
    if ($leader) {
        if ($bestBat && $bestBat['name'] === $leader['name']) $alsoBest[] = 'BEST BATTER';
        if ($bestBowl && $bestBowl['name'] === $leader['name']) $alsoBest[] = 'BEST BOWLER';
    }
    $named = array_filter([$leader['name'] ?? null, $bestBat['name'] ?? null, $bestBowl['name'] ?? null]);
    $rest = array_values(array_filter($players, fn ($p) => ! in_array($p['name'], $named, true)));
    $profileUrl = fn (array $p) => ($p['playerId'] ?? '') !== '' ? route('site.player.profile', $p['playerId']) : null;
@endphp

@if(! $leader)
    <div class="mvp-empty">
        <b>No impact yet</b>
        <span>Once the first ball is scored, every batter and bowler is ranked here by what they did with it.</span>
    </div>
@else
    <div class="mvp-label">{{ $isLive ? 'LEADING THE MATCH' : 'HEROES OF THE MATCH' }}</div>

    {{-- ── Hero: player of the match ── --}}
    @php $p = $leader; $ac = $accent($p); $ctx = $context($p); $url = $profileUrl($p); @endphp
    <div class="mvp-hero" style="--ac: {{ $ac }}">
        <div class="mvp-photo">
            @if(($p['photo'] ?? '') !== '')
                <img src="{{ $p['photo'] }}" alt="" loading="lazy">
            @else
                <span class="mvp-mono">{{ $initials($p['name']) }}</span>
            @endif
            <span class="mvp-award">{{ $isLive ? 'Leading the match' : 'Player of the match' }}</span>
        </div>
        <div class="mvp-hero-body">
            <span class="mvp-wm">{{ (int) $p['points'] }}</span>
            <div class="mvp-role {{ $alsoBest ? 'is-honour' : '' }}">{{ $alsoBest ? implode('  ·  ', $alsoBest) : $role($p) }}</div>
            <div class="mvp-name">@if($url)<a href="{{ $url }}">{{ $p['name'] }}</a>@else{{ $p['name'] }}@endif</div>
            <div class="mvp-team">
                @php $lg = $teamLogo($p); @endphp
                @if($lg !== '')<img src="{{ $lg }}" alt="">@endif
                {{ $teamName($p) }}
            </div>
            <div class="mvp-rule"></div>
            <div class="mvp-figs">
                @if($didBat($p))
                    <div><span class="mvp-k">BATTING</span><b>{{ $p['batLine'] }}</b><em>SR {{ $p['strikeRate'] }}</em></div>
                @endif
                @if($didBat($p) && $didBowl($p))<i class="mvp-vr"></i>@endif
                @if($didBowl($p))
                    <div><span class="mvp-k">BOWLING</span><b>{{ $p['bowlLine'] }}</b><em>ECON {{ $p['econ'] }}@if((int) $p['maidens'] > 0) · {{ (int) $p['maidens'] }} MDN @endif</em></div>
                @endif
            </div>
            @if($didBat($p) && ((int) $p['fours'] > 0 || (int) $p['sixes'] > 0))
                <div class="mvp-pips" aria-label="{{ (int) $p['fours'] }} fours, {{ (int) $p['sixes'] }} sixes">
                    @for($i = 0; $i < (int) $p['fours']; $i++)<span class="pip4">4</span>@endfor
                    @for($i = 0; $i < (int) $p['sixes']; $i++)<span class="pip6">6</span>@endfor
                </div>
            @endif
            @if($ctx)<div class="mvp-ctx">{{ $ctx }}</div>@endif
            @include('site.partials.mvp-follow', ['p' => $p])
        </div>
    </div>

    {{-- ── Awards ── --}}
    @foreach([['p' => $bestBat, 'kind' => 'bat'], ['p' => $bestBowl, 'kind' => 'bowl']] as $aw)
        @php $p = $aw['p']; @endphp
        @continue(! $p || $p['name'] === $leader['name'])
        @php
            $ac = $accent($p); $url = $profileUrl($p);
            $isBat = $aw['kind'] === 'bat';
            $why = $isBat
                ? ((int) $p['runs'] > 0 && (int) $p['runs'] === $topRuns ? 'Top score of the match' : 'Highest impact with the bat')
                : ((int) $p['wickets'] > 0 && (int) $p['wickets'] === $topWkts ? 'Best figures of the match' : 'Highest impact with the ball');
            $detailLine = $isBat
                ? 'SR '.$p['strikeRate'].((int) $p['fours'] > 0 ? '  ·  '.$p['fours'].'x4' : '').((int) $p['sixes'] > 0 ? '  ·  '.$p['sixes'].'x6' : '')
                : 'ER '.$p['econ'].((int) $p['maidens'] > 0 ? '  ·  '.$p['maidens'].' mdn' : '');
        @endphp
        <div class="mvp-award-card" style="--ac: {{ $ac }}">
            <div class="mvp-face">
                @if(($p['photo'] ?? '') !== '')<img src="{{ $p['photo'] }}" alt="" loading="lazy">@else<span>{{ $initials($p['name']) }}</span>@endif
            </div>
            <div class="mvp-aw-body">
                <div class="mvp-aw-k">{{ $isBat ? 'Best batter' : 'Best bowler' }}</div>
                <div class="mvp-aw-name">@if($url)<a href="{{ $url }}">{{ $p['name'] }}</a>@else{{ $p['name'] }}@endif</div>
                <div class="mvp-aw-team">{{ $teamName($p) }}</div>
                <div class="mvp-aw-fig">{{ $isBat ? $p['batLine'] : $p['bowlLine'] }}</div>
                <div class="mvp-aw-why">{{ $why }} · {{ $detailLine }}</div>
                @include('site.partials.mvp-follow', ['p' => $p])
            </div>
            <div class="mvp-aw-pts"><b>{{ (int) $p['points'] }}</b><span>IMPACT</span></div>
        </div>
    @endforeach

    {{-- ── Everyone else who did something ── --}}
    @if(count($rest) > 0)
        <div class="mvp-label">STAR PERFORMANCES</div>
        <div class="mvp-grid">
            @foreach($rest as $p)
                @php $ac = $accent($p); $url = $profileUrl($p); @endphp
                <div class="mvp-star" style="--ac: {{ $ac }}">
                    <div class="mvp-star-top">
                        <span class="mvp-face sm">@if(($p['photo'] ?? '') !== '')<img src="{{ $p['photo'] }}" alt="" loading="lazy">@else<span>{{ $initials($p['name']) }}</span>@endif</span>
                        <span class="mvp-star-pts">{{ (int) $p['points'] }}</span>
                    </div>
                    <div class="mvp-star-name">@if($url)<a href="{{ $url }}">{{ $p['name'] }}</a>@else{{ $p['name'] }}@endif</div>
                    <div class="mvp-star-team">{{ $teamName($p) }}</div>
                    @if($didBat($p))<div class="mvp-star-line"><b>{{ $p['batLine'] }}</b> <span>SR {{ $p['strikeRate'] }}</span></div>@endif
                    @if($didBowl($p))<div class="mvp-star-line"><b>{{ $p['bowlLine'] }}</b> <span>ER {{ $p['econ'] }}</span></div>@endif
                    @include('site.partials.mvp-follow', ['p' => $p])
                </div>
            @endforeach
        </div>
    @endif

    <details class="mvp-formula">
        <summary><span>HOW IMPACT IS SCORED</span><em>Show</em></summary>
        <dl>
            <dt>Batting</dt><dd>1 per run, +1 per four, +2 per six, plus a strike-rate bonus after 10 balls faced.</dd>
            <dt>Bowling</dt><dd>20 per wicket, 8 per maiden, plus an economy bonus after a full over.</dd>
            <dt>Fielding</dt><dd>Not counted — the scorer doesn't record which fielder took the catch.</dd>
        </dl>
        <p>Figures come from the same ball-by-ball log as the scorecard.</p>
    </details>
@endif

<script>
// Follow / Following on a real linked player — the button is only rendered when the
// server's buildMvp says it can be honest (signed in, a real account, not yourself).
function mvpFollow(btn) {
    var on = btn.classList.contains('is-on');
    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    btn.disabled = true;
    fetch('/gamehub/players/' + encodeURIComponent(btn.dataset.id) + '/follow', {
        method: on ? 'DELETE' : 'POST',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    }).then(function (r) {
        btn.disabled = false;
        if (!r.ok) return;
        document.querySelectorAll('.mvp-follow[data-id="' + btn.dataset.id + '"]').forEach(function (b) {
            b.classList.toggle('is-on', !on);
            b.textContent = on ? 'Follow' : 'Following';
        });
    }).catch(function () { btn.disabled = false; });
}
</script>

<style>
.mvp-follow { margin-top: 12px; border: 0; cursor: pointer; font: inherit; font-size: 12.5px; font-weight: 700; padding: 7px 16px; border-radius: 999px; background: var(--blue); color: #fff; }
.mvp-follow.is-on { background: #F1F5F9; color: var(--ink2); }
.mvp-follow[disabled] { opacity: .6; }
.mvp-star .mvp-follow { margin-top: 8px; padding: 5px 12px; font-size: 11.5px; }
.mvp-label { font-size: 10px; font-weight: 800; letter-spacing: 1.1px; color: var(--muted); margin: 4px 2px 10px; }
.mvp-empty { text-align: center; padding: 56px 28px; display: flex; flex-direction: column; gap: 8px; }
.mvp-empty b { font-size: 17px; font-weight: 900; }
.mvp-empty span { font-size: 13px; color: var(--ink2); line-height: 1.5; }
.mvp-hero { position: relative; background: var(--surface); border-radius: 22px; overflow: hidden; border: 1px solid color-mix(in srgb, var(--ac) 22%, transparent); margin-bottom: 14px; box-shadow: 0 10px 24px rgba(15,23,42,.05); }
.mvp-photo { position: relative; height: 210px; background: linear-gradient(160deg, color-mix(in srgb, var(--ac) 85%, #fff), var(--ac)); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.mvp-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 25%; }
.mvp-photo::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 45%, rgba(0,0,0,.55)); }
.mvp-mono { font-size: 72px; font-weight: 900; color: rgba(255,255,255,.92); letter-spacing: -2px; }
.mvp-award { position: absolute; left: 16px; bottom: 14px; z-index: 1; color: #fff; font-size: 12px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; }
.mvp-hero-body { position: relative; padding: 18px; background: linear-gradient(135deg, color-mix(in srgb, var(--ac) 10%, transparent), transparent 55%); }
.mvp-wm { position: absolute; top: -6px; right: 10px; font-size: 96px; font-weight: 900; line-height: 1; color: var(--ac); opacity: .07; font-variant-numeric: tabular-nums; pointer-events: none; }
.mvp-role { text-align: right; font-size: 9px; font-weight: 800; letter-spacing: .9px; color: var(--muted); }
.mvp-role.is-honour { color: var(--ac); }
.mvp-name { font-size: 22px; font-weight: 900; margin-top: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mvp-name a, .mvp-aw-name a, .mvp-star-name a { color: inherit; text-decoration: none; }
.mvp-team { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--ink2); margin-top: 5px; }
.mvp-team img { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; }
.mvp-rule { height: 1px; background: var(--border); margin: 16px 0 14px; }
.mvp-figs { display: flex; align-items: flex-start; }
.mvp-figs > div { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.mvp-k { font-size: 9px; font-weight: 800; letter-spacing: .9px; color: var(--muted); }
.mvp-figs b { font-size: 26px; font-weight: 900; letter-spacing: -.5px; font-variant-numeric: tabular-nums; }
.mvp-figs em { font-style: normal; font-size: 11px; font-weight: 700; color: var(--ink2); }
.mvp-vr { width: 1px; height: 46px; background: var(--border); margin: 0 14px; }
.mvp-pips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 14px; }
.mvp-pips span { width: 20px; height: 20px; border-radius: 50%; font-size: 10px; font-weight: 900; display: inline-flex; align-items: center; justify-content: center; color: #fff; }
.pip4 { background: var(--blue); } .pip6 { background: var(--green); }
.mvp-ctx { margin-top: 12px; font-size: 12.5px; font-weight: 500; color: var(--ink2); }
.mvp-award-card { display: flex; gap: 14px; align-items: center; background: var(--surface); border: 1px solid #EEF2F7; border-left: 3px solid var(--ac); border-radius: 18px; padding: 14px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
.mvp-face { width: 56px; height: 56px; flex: 0 0 auto; border-radius: 50%; overflow: hidden; background: var(--ac); display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 900; font-size: 18px; }
.mvp-face.sm { width: 38px; height: 38px; font-size: 13px; }
.mvp-face img { width: 100%; height: 100%; object-fit: cover; }
.mvp-aw-body { flex: 1; min-width: 0; }
.mvp-aw-k { font-size: 9.5px; font-weight: 800; letter-spacing: .9px; text-transform: uppercase; color: var(--ac); }
.mvp-aw-name { font-size: 16px; font-weight: 900; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.mvp-aw-team { font-size: 11.5px; color: var(--ink2); font-weight: 600; }
.mvp-aw-fig { font-size: 20px; font-weight: 900; margin-top: 6px; font-variant-numeric: tabular-nums; }
.mvp-aw-why { font-size: 11px; color: var(--ink2); margin-top: 2px; }
.mvp-aw-pts { text-align: center; display: flex; flex-direction: column; }
.mvp-aw-pts b { font-size: 20px; font-weight: 900; font-variant-numeric: tabular-nums; }
.mvp-aw-pts span { font-size: 8.5px; font-weight: 800; letter-spacing: .9px; color: var(--muted); }
.mvp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.mvp-star { background: var(--surface); border: 1px solid #EEF2F7; border-top: 3px solid var(--ac); border-radius: 16px; padding: 12px; min-width: 0; }
.mvp-star-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.mvp-star-pts { font-size: 18px; font-weight: 900; color: var(--ac); font-variant-numeric: tabular-nums; }
.mvp-star-name { font-size: 14px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mvp-star-team { font-size: 11px; color: var(--muted); font-weight: 600; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mvp-star-line { font-size: 12px; color: var(--ink2); }
.mvp-star-line b { color: var(--ink); font-size: 14px; font-variant-numeric: tabular-nums; }
.mvp-formula { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 14px; }
.mvp-formula summary { list-style: none; display: flex; justify-content: space-between; cursor: pointer; }
.mvp-formula summary::-webkit-details-marker { display: none; }
.mvp-formula summary span { font-size: 9.5px; font-weight: 800; letter-spacing: 1.1px; color: var(--muted); }
.mvp-formula summary em { font-style: normal; font-size: 11.5px; font-weight: 700; color: var(--blue); }
.mvp-formula[open] summary em { font-size: 0; } .mvp-formula[open] summary em::after { content: "Hide"; font-size: 11.5px; }
.mvp-formula dl { display: grid; grid-template-columns: 58px 1fr; gap: 7px 0; margin: 12px 0 0; font-size: 11px; }
.mvp-formula dt { font-weight: 700; } .mvp-formula dd { margin: 0; color: var(--ink2); }
.mvp-formula p { font-size: 10px; color: var(--muted); margin: 10px 0 0; }
</style>
@endsection
