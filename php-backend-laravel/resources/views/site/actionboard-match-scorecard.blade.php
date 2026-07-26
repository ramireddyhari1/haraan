@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    $d = $detail;
    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];

    $normSquad = function ($squad) {
        $out = [];
        foreach ((array) $squad as $m) {
            $name = is_array($m) ? trim((string) ($m['name'] ?? '')) : trim((string) $m);
            $pid = is_array($m) ? trim((string) ($m['id'] ?? ($m['player_id'] ?? ''))) : '';
            if ($name !== '' && strtolower($name) !== 'null') $out[] = ['name' => $name, 'guest' => (is_array($m) && ($pid === '' || strtolower($pid) === 'null'))];
        }
        return $out;
    };
    $homeSquad = $normSquad($d['homeSquad'] ?? []);
    $awaySquad = $normSquad($d['awaySquad'] ?? []);
    $hasSquads = count($homeSquad) > 0 || count($awaySquad) > 0;
@endphp

@if(count($cards) > 0)
    @foreach($cards as $card)
        @php
            $bt = (int) ($card['battingTeam'] ?? 1);
            $logo = $bt === 2 ? hrn_team_icon($d['team2Logo'] ?? '', $d['team2Emblem'] ?? '') : hrn_team_icon($d['team1Logo'] ?? '', $d['team1Emblem'] ?? '');
            $code = hrn_team_code($bt === 2 ? (string) ($d['team2'] ?? '') : (string) ($d['team1'] ?? ''));
            $innCol = hrn_mono_color((string) ($card['battingName'] ?? $code));
            $batters = is_array($card['batters'] ?? null) ? $card['batters'] : [];
            $bowlers = is_array($card['bowlers'] ?? null) ? $card['bowlers'] : [];
            $fow = is_array($card['fow'] ?? null) ? $card['fow'] : [];
            $ex = is_array($card['extras'] ?? null) ? $card['extras'] : [];
            $scoreLine = ((int)($card['runs']??0)) . '/' . ((int)($card['wickets']??0));
            $battingSquad = $bt === 2 ? $awaySquad : $homeSquad;
            $batted = array_map(fn($b) => (string)($b['name']??''), $batters);
            $dnb = array_values(array_filter(array_map(fn($m)=>$m['name'], $battingSquad), fn($n)=>$n!=='' && !in_array($n,$batted,true)));
        @endphp
        <div class="mdx-card mdx-card--flush mdx-inn">
            <div class="mdx-inn-head" onclick="this.closest('.mdx-inn').classList.toggle('collapsed')">
                <span class="mdx-logo-sm" style="width:30px;height:30px;background:{{ $innCol }};color:#fff;border-color:transparent;">@if($logo!=='')<img src="{{ $logo }}" alt="">@else{{ $code }}@endif</span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:15px;font-weight:900;">{{ $card['battingName'] ?? '' }}</div>
                    <div class="mdx-muted" style="font-size:10px;font-weight:600;">Innings {{ $card['number'] ?? '' }}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:18px;font-weight:900;">{{ $scoreLine }}</div>
                    <div class="mdx-muted" style="font-size:10px;font-weight:600;">{{ $card['overs'] ?? '0.0' }} ov · RR {{ $card['runRate'] ?? '0.00' }}</div>
                </div>
                <svg class="mdx-chevron" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
            <div class="mdx-inn-body">
                {{-- Batting --}}
                <div>
                    <div class="mdx-table-head">
                        <span class="th-name" style="text-align:left;">BATTER</span>
                        <span class="mdx-tc">R</span><span class="mdx-tc">B</span><span class="mdx-tc">4s</span><span class="mdx-tc">6s</span><span class="mdx-tc-sr">SR</span>
                    </div>
                    @foreach($batters as $b)
                        @php $out = (bool)($b['out']??false); $r=(int)($b['runs']??0); $bl=(int)($b['balls']??0); @endphp
                        <div class="mdx-sc-row">
                            <div class="mdx-sc-name">
                                <b>{{ $b['name'] ?? '' }}@if(!$out) *@endif</b>
                                <span class="{{ $out ? '' : 'notout' }}">{{ $out ? ($b['dismissal'] ?? 'out') : 'not out' }}</span>
                            </div>
                            <span class="num runs mdx-tc" @if(!$out)style="color:var(--green);"@endif>{{ $r }}</span>
                            <span class="num mdx-tc">{{ $bl }}</span>
                            <span class="num mdx-tc">{{ (int)($b['fours']??0) }}</span>
                            <span class="num mdx-tc">{{ (int)($b['sixes']??0) }}</span>
                            <span class="num mdx-tc-sr">{{ hrn_sr($r,$bl) }}</span>
                        </div>
                    @endforeach
                    @if((int)($ex['total']??0) > 0)
                        <div class="mdx-sc-extras">
                            <span style="font-size:13px;font-weight:600;color:var(--ink2);">Extras</span>
                            <span style="font-size:12px;color:var(--ink2);">{{ (int)$ex['total'] }} (b {{ (int)($ex['b']??0) }}, lb {{ (int)($ex['lb']??0) }}, w {{ (int)($ex['wd']??0) }}, nb {{ (int)($ex['nb']??0) }})</span>
                        </div>
                    @endif
                    <div class="mdx-sc-total">
                        <div>
                            <div style="font-size:15px;font-weight:900;">Total</div>
                            <div class="mdx-muted" style="font-size:11px;">{{ $card['overs'] ?? '0.0' }} overs · RR {{ $card['runRate'] ?? '0.00' }}</div>
                        </div>
                        <div style="font-size:17px;font-weight:900;">{{ $scoreLine }}</div>
                    </div>
                </div>

                @if(count($dnb) > 0)
                    <div style="font-size:12px;"><b style="color:var(--muted);font-weight:600;">Did not bat: </b><span style="color:var(--ink2);">{{ implode(', ', $dnb) }}</span></div>
                @endif

                {{-- Bowling --}}
                @if(count($bowlers) > 0)
                    <div>
                        <div class="mdx-table-head">
                            <span class="th-name" style="text-align:left;">BOWLER</span>
                            <span class="mdx-tc">O</span><span class="mdx-tc">M</span><span class="mdx-tc">R</span><span class="mdx-tc">W</span><span class="mdx-tc-er">ER</span>
                        </div>
                        @foreach($bowlers as $bw)
                            @php $bb=(int)($bw['balls']??0); $br=(int)($bw['runs']??0); @endphp
                            <div class="mdx-sc-row">
                                <span class="mdx-sc-name" style="font-size:14px;font-weight:600;">{{ $bw['name'] ?? '' }}</span>
                                <span class="num mdx-tc">{{ hrn_overs_from_balls($bb) }}</span>
                                <span class="num mdx-tc">{{ (int)($bw['maidens']??0) }}</span>
                                <span class="num mdx-tc">{{ $br }}</span>
                                <span class="num mdx-tc" style="color:var(--ink);font-weight:700;">{{ (int)($bw['wickets']??0) }}</span>
                                <span class="num mdx-tc-er">{{ hrn_econ($br,$bb) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Fall of wickets --}}
                @if(count($fow) > 0)
                    <div>
                        <div class="mdx-sec-title" style="margin-bottom:10px;">FALL OF WICKETS</div>
                        <div class="mdx-fow">
                            @foreach($fow as $f)
                                <div class="mdx-fow-chip">
                                    <b>{{ (int)($f['score']??0) }}-{{ (int)($f['wicketNo']??0) }}</b>
                                    <span>{{ $f['batter'] ?? '' }} · {{ $f['over'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@elseif($hasSquads)
    <div class="mdx-card"><div class="mdx-muted" style="font-size:13px;">Squads are set — live figures appear here once scoring starts.</div></div>
    @foreach([[($d['team1Full']??$d['team1']??''), $homeSquad], [($d['team2Full']??$d['team2']??''), $awaySquad]] as [$tn, $sq])
        @if(count($sq) > 0)
            <div class="mdx-card">
                <div class="mdx-sec-title">{{ strtoupper($tn) }}</div>
                <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px;">
                    @foreach($sq as $m)
                        <div class="mdx-row"><span style="font-size:14px;font-weight:600;">{{ $m['name'] }}</span>@if($m['guest'])<span class="mdx-guest">GUEST</span>@endif</div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
@else
    <div class="mdx-card" style="text-align:center;">
        <div style="font-size:15px;font-weight:700;">No scorecard yet</div>
        <div class="mdx-muted" style="font-size:13px;margin-top:6px;">Squads and scoring will show here once this match has teams set up.</div>
    </div>
@endif
@endsection
