@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    // The app's cricket Insights board (InsightsTab.kt → CricketInsightsBoard.kt). Figures
    // are CricketInsights::facts() — the payload GET /api/live-matches/{id}/insights sends
    // the app — so a chart here can never disagree with the phone.
    $d = $detail;
    $innings = is_array($insights['innings'] ?? null) ? array_values($insights['innings']) : [];
    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];
    $sideColor = fn (int $team) => $team === 2 ? '#EA580C' : '#2563EB';
    $sideName = fn (array $inn) => hrn_team_code((string) (($inn['battingTeam'] ?? 1) == 2 ? ($d['team2'] ?? '') : ($d['team1'] ?? ''))) ?: (string) ($inn['battingName'] ?? '');

    // Read from the scorecard's own dismissal line — "c Ravi b Imran", "lbw b Imran".
    $howOut = function (string $dis): ?array {
        $s = mb_strtolower(trim($dis));
        return match (true) {
            $s === '' || $s === 'not out' || str_starts_with($s, 'retired hurt') => null,
            str_starts_with($s, 'c & b') || str_starts_with($s, 'c&b') => ['Caught and bowled', '#0E7490'],
            str_starts_with($s, 'c ') || $s === 'caught' => ['Caught', '#0D9488'],
            str_starts_with($s, 'lbw') => ['LBW', '#7C3AED'],
            str_starts_with($s, 'run out') => ['Run out', '#EA580C'],
            str_starts_with($s, 'st ') || $s === 'st' || str_starts_with($s, 'stumped') => ['Stumped', '#2563EB'],
            str_starts_with($s, 'hit wicket') => ['Hit wicket', '#B45309'],
            str_starts_with($s, 'retired') => ['Retired out', '#64748B'],
            str_starts_with($s, 'b ') || $s === 'bowled' => ['Bowled', '#DC2626'],
            default => ['Out', '#64748B'],
        };
    };

    // ── Match progress geometry (Manhattan / Worm / Run rate), drawn server-side ──
    $W = 320; $H = 150; $padL = 26; $padB = 18; $padT = 8;
    $maxOvers = max(1, ...array_map(fn ($i) => count($i['progress'] ?? []), $innings ?: [['progress' => []]]));
    $maxOverRuns = max(6, ...array_map(fn ($i) => max(array_merge([0], array_map(fn ($o) => (int) $o['runs'], $i['progress'] ?? []))), $innings ?: [['progress' => []]]));
    $maxTotal = max(10, ...array_map(fn ($i) => (int) ($i['runs'] ?? 0), $innings ?: [['runs' => 0]]));
    $maxRR = 6.0;
    foreach ($innings as $inn) foreach (($inn['progress'] ?? []) as $o) $maxRR = max($maxRR, $o['total'] / max(1, $o['over']));
    $maxRR = ceil($maxRR / 2) * 2;
    $plotW = $W - $padL - 4; $plotH = $H - $padT - $padB;
    $xAt = fn (float $over) => $padL + $plotW * ($over / $maxOvers);
    $yAt = fn (float $v, float $max) => $padT + $plotH * (1 - min(1, $v / $max));
@endphp

@if(! empty($insightsLock))
    <div class="ins-card ins-lock">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <b>Advanced insights</b>
        <p>{{ $insightsLock['signed_in'] ? $insightsLock['message'] : 'Sign in to see insights for this match.' }}</p>
        @if(! $insightsLock['signed_in'])
            <a class="ins-btn" href="{{ route('site.login') }}">Sign in</a>
        @else
            <a class="ins-btn" href="{{ url('/membership') }}">See plans</a>
        @endif
    </div>
    @if(! empty($ground))@include('site.partials.insights-ground', ['ground' => $ground, 'thisInnings' => 0])@endif
@elseif(count($innings) === 0)
    @if(! empty($ground))
        @include('site.partials.insights-ground', ['ground' => $ground, 'thisInnings' => 0])
    @else
        <div class="ins-empty">
            <b>Nothing to read yet</b>
            <span>Insights appear once the innings has enough deliveries to say something about.</span>
        </div>
    @endif
@else
    {{-- ── 1. Match progress ── --}}
    <div class="ins-head">Match progress</div>
    <div class="ins-pills" data-group="prog">
        <button class="is-on" data-i="0">Manhattan</button><button data-i="1">Worm</button><button data-i="2">Run rate</button>
    </div>
    <div class="ins-card">
        <div class="ins-legend">
            @foreach($innings as $inn)
                <span><i style="background:{{ $sideColor((int) $inn['battingTeam']) }}"></i>{{ $sideName($inn) }} {{ $inn['runs'] }}/{{ $inn['wickets'] }} <em>({{ $inn['overs'] }})</em></span>
            @endforeach
        </div>
        @php $n = count($innings); $slot = $plotW / $maxOvers; $bw = max(2, ($slot - 3) / max(1, $n)); @endphp
        @foreach(['manhattan', 'worm', 'rate'] as $mi => $mode)
            <svg class="ins-chart" data-group="prog" data-i="{{ $mi }}" @if($mi > 0) hidden @endif viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ ucfirst($mode) }} chart">
                @php $ymax = $mode === 'manhattan' ? $maxOverRuns : ($mode === 'worm' ? $maxTotal : $maxRR); @endphp
                @for($g = 0; $g <= 4; $g++)
                    @php $gy = $padT + $plotH * $g / 4; $gv = $ymax * (1 - $g / 4); @endphp
                    <line x1="{{ $padL }}" x2="{{ $W - 4 }}" y1="{{ $gy }}" y2="{{ $gy }}" stroke="#EEF2F7"/>
                    <text x="{{ $padL - 5 }}" y="{{ $gy + 3 }}" text-anchor="end" class="ins-ax">{{ $mode === 'rate' ? number_format($gv, 0) : (int) round($gv) }}</text>
                @endfor
                @php $step = max(1, (int) ceil($maxOvers / 8)); @endphp
                @for($ov = $step; $ov <= $maxOvers; $ov += $step)
                    <text x="{{ $mode === 'manhattan' ? $xAt($ov - 0.5) : $xAt($ov) }}" y="{{ $H - 4 }}" text-anchor="middle" class="ins-ax">{{ $ov }}</text>
                @endfor
                @foreach($innings as $k => $inn)
                    @php $col = $sideColor((int) $inn['battingTeam']); $prog = $inn['progress'] ?? []; @endphp
                    @if($mode === 'manhattan')
                        @foreach($prog as $o)
                            @php $x = $padL + $slot * ($o['over'] - 1) + 1.5 + $bw * $k; $y = $yAt((int) $o['runs'], $maxOverRuns); @endphp
                            <rect x="{{ round($x, 1) }}" y="{{ round($y, 1) }}" width="{{ round($bw, 1) }}" height="{{ round($padT + $plotH - $y, 1) }}" rx="1.5" fill="{{ $col }}" opacity=".85"><title>Over {{ $o['over'] }}: {{ $o['runs'] }} runs{{ $o['wickets'] ? ', '.$o['wickets'].' wkt' : '' }}</title></rect>
                            @if((int) $o['wickets'] > 0)
                                <circle cx="{{ round($x + $bw / 2, 1) }}" cy="{{ round($y - 5, 1) }}" r="3" fill="#DC2626" stroke="#fff" stroke-width="1"/>
                            @endif
                        @endforeach
                    @else
                        @php
                            $pts = [$xAt(0).','.$yAt(0, $ymax)];
                            foreach ($prog as $o) {
                                $v = $mode === 'worm' ? (float) $o['total'] : $o['total'] / max(1, $o['over']);
                                $pts[] = round($xAt($o['over']), 1).','.round($yAt($v, $ymax), 1);
                            }
                        @endphp
                        <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="{{ $col }}" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
                        @if($mode === 'worm')
                            @foreach($prog as $o)
                                @if((int) $o['wickets'] > 0)
                                    <circle cx="{{ round($xAt($o['over']), 1) }}" cy="{{ round($yAt((float) $o['total'], $ymax), 1) }}" r="3.2" fill="#DC2626" stroke="#fff" stroke-width="1"><title>Over {{ $o['over'] }}: {{ $o['total'] }}/{{ $o['totalWickets'] }}</title></circle>
                                @endif
                            @endforeach
                        @endif
                    @endif
                @endforeach
            </svg>
        @endforeach
        <div class="ins-figs">
            @foreach($innings as $inn)
                <div style="--c: {{ $sideColor((int) $inn['battingTeam']) }}">
                    <span>{{ $sideName($inn) }}</span>
                    <b>{{ number_format((float) $inn['runRate'], 2) }}</b><em>Run rate</em>
                    @if(! empty($inn['bestOver']))<b>{{ $inn['bestOver']['runs'] }}</b><em>Best over (#{{ $inn['bestOver']['over'] }})</em>@endif
                    <b>{{ (int) $inn['boundaryPercent'] }}%</b><em>From boundaries</em>
                    <b>{{ (int) $inn['dotPercent'] }}%</b><em>Dot balls</em>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Phases: Start / Middle / Finish --}}
    @if(collect($innings)->contains(fn ($i) => ! empty($i['phases'])))
        <div class="ins-head">Phases</div>
        @include('site.partials.insights-side-pills', ['group' => 'phase', 'innings' => $innings, 'sideName' => $sideName])
        @foreach($innings as $k => $inn)
            <div class="ins-card" data-group="phase" data-i="{{ $k }}" @if($k !== count($innings) - 1) hidden @endif>
                <div class="ins-phases">
                    @foreach(($inn['phases'] ?? []) as $ph)
                        <div>
                            <span>{{ $ph['label'] }} <em>{{ $ph['overs'] }} ov</em></span>
                            <b>{{ $ph['runs'] }}<small>/{{ $ph['wickets'] }}</small></b>
                            <em>RR {{ number_format((float) $ph['runRate'], 2) }}</em>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    {{-- ── 2. Partnerships ── --}}
    @if(collect($innings)->contains(fn ($i) => ! empty($i['partnerships'])))
        <div class="ins-head">Partnerships</div>
        @include('site.partials.insights-side-pills', ['group' => 'stand', 'innings' => $innings, 'sideName' => $sideName])
        @foreach($innings as $k => $inn)
            @php $stands = $inn['partnerships'] ?? []; $maxStand = max(1, ...array_map(fn ($s) => (int) $s['runs'], $stands ?: [['runs' => 1]])); $col = $sideColor((int) $inn['battingTeam']); @endphp
            <div class="ins-card" data-group="stand" data-i="{{ $k }}" @if($k !== count($innings) - 1) hidden @endif>
                @forelse($stands as $st)
                    @php $split = $st['split'] ?? []; $a = $split[0] ?? null; $b = $split[1] ?? null; $tot = max(1, (int) $st['runs']); @endphp
                    <div class="ins-stand">
                        <div class="ins-stand-top">
                            <span class="ins-wk">{{ $st['wicket'] }}{{ match((int) $st['wicket']) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }} wkt</span>
                            <span class="ins-stand-names">{{ $st['batters'] }}</span>
                            <b>{{ $st['runs'] }}{{ ! empty($st['unbroken']) ? '*' : '' }} <em>({{ $st['balls'] }})</em></b>
                        </div>
                        <div class="ins-stand-bar" style="width: calc((100% - 52px) * {{ max(6, round($st['runs'] * 100 / $maxStand)) / 100 }})">
                            @if($a)<i style="flex: {{ max(1, (int) $a['runs']) }}; background: {{ $col }}"></i>@endif
                            @if($b)<i style="flex: {{ max(1, (int) $b['runs']) }}; background: {{ $col }}; opacity: .45"></i>@endif
                        </div>
                        @if($a || $b)
                            <div class="ins-stand-split">
                                @if($a)<span>{{ $a['name'] }} {{ $a['runs'] }} ({{ $a['balls'] }})</span>@endif
                                @if($b)<span>{{ $b['name'] }} {{ $b['runs'] }} ({{ $b['balls'] }})</span>@endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="ins-none">No partnerships yet.</div>
                @endforelse
            </div>
        @endforeach
    @endif

    {{-- ── 3. Wagon wheel ── --}}
    <div class="ins-head">Wagon wheel</div>
    @include('site.partials.insights-side-pills', ['group' => 'wagon', 'innings' => $innings, 'sideName' => $sideName])
    <div class="ins-pills ins-pills--sub" data-filter="wagon">
        <button class="is-on" data-f="all">All</button><button data-f="4">Fours</button><button data-f="6">Sixes</button>
    </div>
    @foreach($innings as $k => $inn)
        @php $shots = $inn['shots'] ?? []; $zones = collect($inn['shotZones'] ?? [])->keyBy('zone'); @endphp
        <div class="ins-card" data-group="wagon" data-i="{{ $k }}" @if($k !== count($innings) - 1) hidden @endif>
            @if(count($shots) === 0)
                <div class="ins-none">No shot directions recorded for this innings yet. The scorer marks them on the ground after each scoring ball.</div>
            @else
                <svg class="ins-wagon" viewBox="-130 -130 260 260" role="img" aria-label="Wagon wheel">
                    <ellipse cx="0" cy="0" rx="124" ry="124" fill="#E8F5E9" stroke="#C8E6C9" stroke-width="2"/>
                    <ellipse cx="0" cy="0" rx="62" ry="62" fill="none" stroke="#C8E6C9" stroke-dasharray="3 4"/>
                    @for($z = 0; $z < 8; $z++)
                        @php $ang = deg2rad($z * 45 - 90 + 22.5); @endphp
                        <line x1="0" y1="0" x2="{{ round(cos($ang) * 124, 1) }}" y2="{{ round(sin($ang) * 124, 1) }}" stroke="#D7EDD9" stroke-width="1"/>
                    @endfor
                    <rect x="-4" y="-14" width="8" height="28" rx="2" fill="#E7D6B0"/>
                    @foreach($shots as $s)
                        @php
                            $runs = (int) ($s['runs'] ?? 0);
                            if ($s['x'] !== null && $s['y'] !== null) { $ex = (float) $s['x'] * 120; $ey = (float) $s['y'] * 120; }
                            else {
                                $ang = deg2rad(((int) $s['zone']) * 45 - 90);
                                $r = $runs >= 6 ? 120 : ($runs === 4 ? 112 : 40 + $runs * 18);
                                $ex = cos($ang) * $r; $ey = sin($ang) * $r;
                            }
                            $col = $runs >= 6 ? '#16A34A' : ($runs === 4 ? '#2563EB' : '#94A3B8');
                        @endphp
                        <line class="ins-shot" data-r="{{ $runs >= 6 ? 6 : ($runs === 4 ? 4 : 0) }}" x1="0" y1="-4" x2="{{ round($ex, 1) }}" y2="{{ round($ey, 1) }}" stroke="{{ $col }}" stroke-width="{{ $runs >= 4 ? 2 : 1.4 }}" stroke-linecap="round" opacity=".9"><title>{{ $s['batter'] ?? '' }} · {{ $runs }} run{{ $runs === 1 ? '' : 's' }} · over {{ $s['over'] ?? '' }}</title></line>
                    @endforeach
                </svg>
                <div class="ins-wagon-key"><span><i style="background:#2563EB"></i>Four</span><span><i style="background:#16A34A"></i>Six</span><span><i style="background:#94A3B8"></i>Others</span></div>
                @php $topZone = $zones->sortByDesc('runs')->first(); @endphp
                @if($topZone && (int) $topZone['runs'] > 0)
                    <div class="ins-note">Most runs: <b>{{ $topZone['runs'] }}</b> from {{ $topZone['shots'] }} shot{{ $topZone['shots'] == 1 ? '' : 's' }} in one zone.</div>
                @endif
            @endif
        </div>
    @endforeach

    {{-- ── 4. Shots (named strokes) ── --}}
    @if(collect($innings)->contains(fn ($i) => ! empty($i['shotTypes'])))
        <div class="ins-head">Shots</div>
        @include('site.partials.insights-side-pills', ['group' => 'shots', 'innings' => $innings, 'sideName' => $sideName])
        @foreach($innings as $k => $inn)
            @php $types = $inn['shotTypes'] ?? []; $maxT = max(1, ...array_map(fn ($t) => (int) $t['runs'], $types ?: [['runs' => 1]])); @endphp
            <div class="ins-card" data-group="shots" data-i="{{ $k }}" @if($k !== count($innings) - 1) hidden @endif>
                @forelse($types as $t)
                    <div class="ins-bar-row">
                        <span class="ins-bar-l">{{ $t['label'] }}</span>
                        <span class="ins-bar-track"><i style="width: {{ round($t['runs'] * 100 / $maxT) }}%; background: {{ $sideColor((int) $inn['battingTeam']) }}"></i></span>
                        <b>{{ $t['runs'] }}</b><em>{{ $t['shots'] }} shot{{ $t['shots'] == 1 ? '' : 's' }}</em>
                    </div>
                @empty
                    <div class="ins-none">No strokes named for this innings.</div>
                @endforelse
            </div>
        @endforeach
    @endif

    {{-- ── 5. How the wickets fell ── --}}
    @php $withOuts = array_values(array_filter($cards, fn ($c) => collect($c['batters'] ?? [])->contains(fn ($b) => ! empty($b['out'])))); @endphp
    @if(count($withOuts) > 0)
        <div class="ins-head">How the wickets fell</div>
        @if(count($withOuts) > 1)
            <div class="ins-pills" data-group="wkts">
                @foreach($withOuts as $k => $c)
                    <button class="{{ $k === count($withOuts) - 1 ? 'is-on' : '' }}" data-i="{{ $k }}">{{ hrn_team_code((string) ((int) ($c['battingTeam'] ?? 1) === 2 ? ($d['team2'] ?? '') : ($d['team1'] ?? ''))) }}</button>
                @endforeach
            </div>
        @endif
        @foreach($withOuts as $k => $c)
            @php
                $groups = [];
                foreach (($c['batters'] ?? []) as $b) {
                    if (empty($b['out'])) continue;
                    $h = $howOut((string) ($b['dismissal'] ?? ''));
                    if (! $h) continue;
                    $groups[$h[0]] ??= ['tint' => $h[1], 'names' => []];
                    $groups[$h[0]]['names'][] = $b['name'] ?? '';
                }
                uasort($groups, fn ($x, $y) => count($y['names']) <=> count($x['names']));
                $tot = max(1, array_sum(array_map(fn ($g) => count($g['names']), $groups)));
            @endphp
            <div class="ins-card" data-group="wkts" data-i="{{ $k }}" @if($k !== count($withOuts) - 1) hidden @endif>
                <div class="ins-wbar">@foreach($groups as $g)<i style="flex: {{ count($g['names']) }}; background: {{ $g['tint'] }}"></i>@endforeach</div>
                @foreach($groups as $label => $g)
                    <div class="ins-how">
                        <span class="ins-how-ic" style="background: {{ $g['tint'] }}14; color: {{ $g['tint'] }}">{{ count($g['names']) }}</span>
                        <div><b>{{ $label }}</b><span>{{ implode(', ', $g['names']) }}</span></div>
                        <em>{{ round(count($g['names']) * 100 / $tot) }}%</em>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    {{-- ── 6. Scoring breakdown ── --}}
    <div class="ins-head">Scoring breakdown</div>
    @include('site.partials.insights-side-pills', ['group' => 'scoring', 'innings' => $innings, 'sideName' => $sideName])
    @foreach($innings as $k => $inn)
        @php
            $b = $inn['breakdown'] ?? [];
            $kinds = [['Dots', $b['dots'] ?? 0, '#B8C2CF'], ['1s', $b['ones'] ?? 0, '#475569'], ['2s', $b['twos'] ?? 0, '#64748B'], ['3s', $b['threes'] ?? 0, '#94A3B8'], ['4s', $b['fours'] ?? 0, '#2563EB'], ['6s', $b['sixes'] ?? 0, '#16A34A'], ['Extras', $b['extras'] ?? 0, '#CBD5E1']];
            $sum = max(1, array_sum(array_column($kinds, 1)));
        @endphp
        <div class="ins-card" data-group="scoring" data-i="{{ $k }}" @if($k !== count($innings) - 1) hidden @endif>
            <div class="ins-wbar ins-wbar--tall">@foreach($kinds as [$l, $c, $t])@if($c > 0)<i style="flex: {{ $c }}; background: {{ $t }}"></i>@endif @endforeach</div>
            <div class="ins-kinds">
                @foreach($kinds as [$l, $c, $t])
                    <div><i style="background: {{ $t }}"></i><b>{{ (int) $c }}</b><span>{{ $l }}</span><em>{{ round($c * 100 / $sum) }}%</em></div>
                @endforeach
            </div>
        </div>
    @endforeach

    @include('site.partials.insights-player-form')

    @if(! empty($ground))@include('site.partials.insights-ground', ['ground' => $ground, 'thisInnings' => (int) ($innings[0]['runs'] ?? 0)])@endif

    @if(! empty($insights['analysis']))
        <div class="ins-head">The read</div>
        <div class="ins-card ins-read">{!! nl2br(e($insights['analysis'])) !!}</div>
    @endif
@endif

<style>
.ins-head { font-size: 17px; font-weight: 900; letter-spacing: -.2px; margin: 22px 2px 10px; }
.ins-head:first-child { margin-top: 6px; }
.ins-card { background: var(--surface); border: 1px solid #EEF2F7; border-radius: 18px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 10px 24px rgba(15,23,42,.05); }
.ins-card[hidden], .ins-chart[hidden] { display: none; }
.ins-pills { display: inline-flex; gap: 4px; background: #E9EEF5; border-radius: 999px; padding: 3px; margin-bottom: 12px; }
.ins-pills--sub { margin-left: 6px; }
.ins-pills button { border: 0; background: transparent; font: inherit; font-size: 12px; font-weight: 700; color: var(--ink2); padding: 6px 12px; border-radius: 999px; cursor: pointer; }
.ins-pills button.is-on { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(15,23,42,.12); }
.ins-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
.ins-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
.ins-legend em { font-style: normal; color: var(--muted); font-weight: 600; }
.ins-chart { width: 100%; height: auto; display: block; }
.ins-ax { font-size: 8px; fill: #94A3B8; font-weight: 600; }
.ins-figs { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-top: 12px; border-top: 1px solid var(--border); padding-top: 12px; }
.ins-figs > div { display: grid; grid-template-columns: auto 1fr; gap: 2px 8px; align-items: baseline; }
.ins-figs > div > span { grid-column: 1 / -1; font-size: 11px; font-weight: 800; color: var(--c); letter-spacing: .4px; margin-bottom: 2px; }
.ins-figs b { font-size: 15px; font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-figs em { font-style: normal; font-size: 11px; color: var(--ink2); }
.ins-phases { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.ins-phases > div { display: flex; flex-direction: column; gap: 3px; padding: 10px; background: var(--bg); border-radius: 12px; }
.ins-phases span { font-size: 11px; font-weight: 800; color: var(--ink2); }
.ins-phases span em, .ins-phases > div > em { font-style: normal; font-weight: 600; color: var(--muted); font-size: 10.5px; }
.ins-phases b { font-size: 20px; font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-phases small { font-size: 13px; color: var(--muted); }
.ins-stand { padding: 10px 0; border-top: 1px solid #F1F5F9; }
.ins-stand:first-child { border-top: 0; padding-top: 0; }
.ins-stand-top { display: flex; align-items: baseline; gap: 8px; }
.ins-wk { font-size: 10px; font-weight: 800; color: var(--muted); width: 44px; flex: 0 0 auto; }
.ins-stand-names { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ins-stand-top b { font-size: 15px; font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-stand-top b em { font-style: normal; font-size: 11px; color: var(--muted); font-weight: 600; }
.ins-stand-bar { display: flex; gap: 2px; height: 8px; margin: 7px 0 0 52px; border-radius: 4px; overflow: hidden; }
.ins-stand-bar i { display: block; }
.ins-stand-split { display: flex; justify-content: space-between; gap: 8px; margin: 4px 0 0 52px; font-size: 11px; color: var(--ink2); }
.ins-wagon { width: 100%; max-width: 280px; display: block; margin: 0 auto; }
.ins-wagon .ins-shot[hidden] { display: none; }
.ins-wagon-key { display: flex; justify-content: center; gap: 14px; font-size: 11px; font-weight: 600; color: var(--ink2); margin-top: 8px; }
.ins-wagon-key i, .ins-kinds i { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 5px; }
.ins-note { text-align: center; font-size: 12px; color: var(--ink2); margin-top: 8px; }
.ins-bar-row { display: grid; grid-template-columns: 96px 1fr auto auto; gap: 8px; align-items: center; padding: 6px 0; font-size: 12.5px; }
.ins-bar-l { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ins-bar-track { height: 8px; background: #F1F5F9; border-radius: 4px; overflow: hidden; }
.ins-bar-track i { display: block; height: 100%; border-radius: 4px; }
.ins-bar-row b { font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-bar-row em { font-style: normal; font-size: 11px; color: var(--muted); min-width: 52px; text-align: right; }
.ins-wbar { display: flex; gap: 2px; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
.ins-wbar--tall { height: 14px; margin-bottom: 14px; }
.ins-wbar i { display: block; }
.ins-how { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-top: 1px solid #F1F5F9; }
.ins-how:nth-child(2) { border-top: 0; }
.ins-how-ic { width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-weight: 900; font-size: 17px; flex: 0 0 auto; }
.ins-how > div { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.ins-how b { font-size: 15px; font-weight: 600; }
.ins-how div span { font-size: 12.5px; color: var(--muted); }
.ins-how em { font-style: normal; font-weight: 800; color: var(--ink2); }
.ins-kinds { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px 8px; }
.ins-kinds > div { display: flex; flex-direction: column; align-items: flex-start; }
.ins-kinds b { font-size: 18px; font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-kinds span { font-size: 11px; font-weight: 700; color: var(--ink2); }
.ins-kinds em { font-style: normal; font-size: 10.5px; color: var(--muted); }
.ins-none { font-size: 13px; color: var(--ink2); text-align: center; padding: 14px 6px; line-height: 1.5; }
.ins-read { font-size: 14px; line-height: 1.6; color: var(--ink); }
.ins-empty { text-align: center; padding: 56px 28px; display: flex; flex-direction: column; gap: 6px; }
.ins-empty b { font-size: 15px; font-weight: 800; }
.ins-empty span { font-size: 13px; color: var(--ink2); }
.ins-lock { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 28px 20px; margin-top: 6px; }
.ins-lock svg { width: 30px; height: 30px; color: var(--blue); }
.ins-lock b { font-size: 16px; font-weight: 900; }
.ins-lock p { font-size: 13px; color: var(--ink2); margin: 0; line-height: 1.5; }
/* Ground insights */
.ins-ground { padding: 0; overflow: hidden; }
.ins-g-map { height: 130px; background: #DCEFD9; }
.ins-g-map img, .ins-g-map svg { width: 100%; height: 100%; object-fit: cover; display: block; }
.ins-g-body { padding: 16px; }
.ins-g-top { display: flex; align-items: flex-start; gap: 10px; }
.ins-g-top > div { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.ins-g-top b { font-size: 16px; font-weight: 800; }
.ins-g-top span { font-size: 12px; color: var(--ink2); }
.ins-g-dir { font-size: 12px; font-weight: 700; color: var(--blue); text-decoration: none; border: 1px solid #DBEAFE; background: #EFF6FF; padding: 6px 10px; border-radius: 10px; white-space: nowrap; }
.ins-g-verdict { margin-top: 14px; padding: 10px 12px; border-radius: 12px; background: color-mix(in srgb, var(--t) 9%, transparent); display: flex; flex-direction: column; gap: 2px; }
.ins-g-verdict b { font-size: 12px; font-weight: 900; letter-spacing: .6px; color: var(--t); }
.ins-g-verdict span { font-size: 12px; color: var(--ink2); }
.ins-g-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px 8px; margin-top: 14px; }
.ins-g-stats > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.ins-g-stats b { font-size: 17px; font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-g-stats span { font-size: 10.5px; font-weight: 600; color: var(--ink2); }
.ins-g-stats em { font-style: normal; font-size: 10.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ins-g-split { margin-top: 14px; }
.ins-g-split > span { font-size: 11px; font-weight: 800; color: var(--ink2); }
.ins-g-bar { display: flex; gap: 2px; height: 10px; border-radius: 5px; overflow: hidden; margin: 6px 0 4px; }
.ins-g-bar i:first-child { background: var(--blue); } .ins-g-bar i:last-child { background: #CBD5E1; }
.ins-g-legend { display: flex; justify-content: space-between; font-size: 11px; color: var(--ink2); }
.ins-g-bullets { margin: 12px 0 0; padding-left: 18px; font-size: 12.5px; color: var(--ink2); line-height: 1.6; }
.ins-g-note { font-size: 12px; color: var(--ink2); margin: 12px 0 0; line-height: 1.5; }
.ins-g-conf { margin-top: 12px; font-size: 11px; font-weight: 700; color: var(--ink2); display: flex; align-items: center; gap: 6px; }
.ins-g-conf i { width: 7px; height: 7px; border-radius: 50%; background: var(--t); }
/* Player form */
.ins-pf-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.ins-pf-head .ins-pills { margin: 22px 0 10px; }
.ins-pills--sm button { padding: 5px 11px; font-size: 11.5px; }
.ins-pf-teams { display: flex; border-bottom: 1px solid var(--border); margin: -4px -4px 12px; }
.ins-pf-teams button { flex: 1; border: 0; background: none; font: inherit; font-size: 13px; font-weight: 600; color: var(--muted); padding: 8px 4px 10px; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ins-pf-teams button.is-on { color: var(--ink); border-bottom-color: var(--blue); }
.ins-pf-row { display: flex; gap: 10px; padding-left: 4px; padding-top: 4px; overflow-x: auto; scrollbar-width: none; padding-bottom: 4px; }
.ins-pf-row[hidden] { display: none; }
.ins-pf-av { flex: 0 0 auto; border: 0; background: none; font: inherit; display: flex; flex-direction: column; align-items: center; gap: 5px; cursor: pointer; width: 58px; }
.ins-pf-av i { width: 44px; height: 44px; border-radius: 50%; color: #fff; font-style: normal; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; opacity: .55; outline: 2px solid transparent; outline-offset: 2px; }
.ins-pf-av.is-on i { opacity: 1; outline-color: var(--blue); }
.ins-pf-av span { font-size: 10.5px; color: var(--ink2); font-weight: 600; white-space: nowrap; max-width: 58px; overflow: hidden; text-overflow: ellipsis; }
.ins-pf-body { margin-top: 14px; border-top: 1px solid #F1F5F9; padding-top: 12px; }
.ins-pf-who { display: flex; align-items: baseline; gap: 8px; margin-bottom: 10px; }
.ins-pf-who b { font-size: 15px; font-weight: 800; }
.ins-pf-who span { font-size: 11.5px; color: var(--muted); }
.ins-pf-totals { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; background: var(--bg); border-radius: 12px; padding: 10px; margin-bottom: 10px; text-align: center; }
.ins-pf-totals b { display: block; font-size: 16px; font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-pf-totals span { font-size: 10px; font-weight: 700; color: var(--muted); }
.ins-pf-inn { padding: 8px 0; border-top: 1px solid #F1F5F9; }
.ins-pf-inn:first-child { border-top: 0; }
.ins-pf-inn > div { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
.ins-pf-inn b { font-size: 15px; font-weight: 900; font-variant-numeric: tabular-nums; }
.ins-pf-inn b em { font-style: normal; font-size: 11px; color: var(--muted); font-weight: 600; }
.ins-pf-inn span { font-size: 11px; color: var(--ink2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ins-pf-inn > i { display: block; height: 4px; border-radius: 2px; background: var(--blue); margin-top: 5px; }
.ins-pf-eff { display: flex; gap: 14px; flex-wrap: wrap; font-size: 11.5px; color: var(--ink2); margin-top: 8px; }
.ins-btn { margin-top: 6px; background: var(--blue); color: #fff; text-decoration: none; font-weight: 700; font-size: 13px; padding: 10px 20px; border-radius: 12px; }
</style>
<script>
(function () {
    // Pill switches: a pill group swaps which [data-group][data-i] panel is showing.
    document.querySelectorAll('.ins-pills[data-group]').forEach(function (bar) {
        var g = bar.dataset.group;
        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('button'); if (!btn) return;
            bar.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
            document.querySelectorAll('[data-group="' + g + '"]:not(.ins-pills)').forEach(function (el) {
                el.hidden = el.dataset.i !== btn.dataset.i;
            });
        });
    });
    // Wagon filter: All / Fours / Sixes across every innings' wheel.
    var wf = document.querySelector('.ins-pills[data-filter="wagon"]');
    if (wf) wf.addEventListener('click', function (e) {
        var btn = e.target.closest('button'); if (!btn) return;
        wf.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
        document.querySelectorAll('.ins-shot').forEach(function (l) {
            l.style.display = (btn.dataset.f === 'all' || l.dataset.r === btn.dataset.f) ? '' : 'none';
        });
    });
})();
</script>
@endsection
