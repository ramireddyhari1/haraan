@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    $d = $detail;
    $team1Full = trim((string) ($d['team1Full'] ?? '')) ?: (string) ($d['team1'] ?? '');
    $team2Full = trim((string) ($d['team2Full'] ?? '')) ?: (string) ($d['team2'] ?? '');
    $logo1 = (string) ($d['team1Logo'] ?? '');
    $logo2 = (string) ($d['team2Logo'] ?? '');
    $competition = trim((string) ($d['formatLabel'] ?? ''));

    $facts = [];
    if (trim((string) ($d['status'] ?? '')) !== '') $facts['Status'] = $d['status'];
    if (trim((string) ($d['venue'] ?? '')) !== '') $facts['Venue'] = $d['venue'];
    if (trim((string) ($d['toss'] ?? '')) !== '') $facts['Toss'] = $d['toss'];

    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];

    // Normalise a squad (strings or {id,name}) into [name, isGuest] rows.
    $normSquad = function ($squad) {
        $out = [];
        foreach ((array) $squad as $m) {
            if (is_array($m)) {
                $name = trim((string) ($m['name'] ?? ''));
                $pid = trim((string) ($m['id'] ?? ($m['player_id'] ?? '')));
                if ($name !== '' && strtolower($name) !== 'null') $out[] = ['name' => $name, 'guest' => ($pid === '' || strtolower($pid) === 'null')];
            } else {
                $name = trim((string) $m);
                if ($name !== '' && strtolower($name) !== 'null') $out[] = ['name' => $name, 'guest' => false];
            }
        }
        return $out;
    };
    $squads = [
        [$team1Full, $normSquad($d['homeSquad'] ?? [])],
        [$team2Full, $normSquad($d['awaySquad'] ?? [])],
    ];
@endphp

{{-- Match overview --}}
<div class="mdx-card">
    @if($competition !== '')<div class="mdx-eyebrow" style="margin-bottom:14px;">{{ $competition }}</div>@endif
    <div class="mdx-row">
        <div class="mdx-team-line">
            <span class="mdx-logo-sm">@if($logo1!=='')<img src="{{ $logo1 }}" alt="">@else{{ hrn_team_code($team1Full) }}@endif</span>
            <b style="font-size:12px;letter-spacing:.3px;">{{ strtoupper($team1Full) }}</b>
        </div>
        <span class="mdx-muted" style="font-size:12px;padding:0 8px;">vs</span>
        <div class="mdx-team-line" style="justify-content:flex-end;flex:1;">
            <b style="font-size:12px;letter-spacing:.3px;text-align:right;">{{ strtoupper($team2Full) }}</b>
            <span class="mdx-logo-sm">@if($logo2!=='')<img src="{{ $logo2 }}" alt="">@else{{ hrn_team_code($team2Full) }}@endif</span>
        </div>
    </div>
    @if(count($facts) > 0)
        <div class="mdx-divider"></div>
        @foreach($facts as $k => $v)
            <div class="mdx-fact" @if(!$loop->first)style="margin-top:12px;"@endif>
                <span class="k">{{ $k }}</span>
                <span class="v">{{ $v }}</span>
            </div>
        @endforeach
    @endif
</div>

{{-- Per-innings summaries --}}
@foreach($cards as $card)
    @php
        $runs = (int) ($card['runs'] ?? 0); $wkts = (int) ($card['wickets'] ?? 0);
        $batters = is_array($card['batters'] ?? null) ? $card['batters'] : [];
        $bowlers = is_array($card['bowlers'] ?? null) ? $card['bowlers'] : [];
        $topBat = null; foreach ($batters as $b) { if ($topBat === null || (int)($b['runs']??0) > (int)($topBat['runs']??0)) $topBat = $b; }
        $topBowl = null; foreach ($bowlers as $b) { $sc = (int)($b['wickets']??0)*100 - (int)($b['runs']??0); if ($topBowl === null || $sc > ((int)($topBowl['wickets']??0)*100 - (int)($topBowl['runs']??0))) $topBowl = $b; }
    @endphp
    <div class="mdx-card">
        <div class="mdx-row" style="align-items:flex-end;">
            <div>
                <div class="mdx-eyebrow">Innings {{ $card['number'] ?? '' }}</div>
                <div style="margin-top:4px;font-size:15px;font-weight:700;">{{ $card['battingName'] ?? '' }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:22px;font-weight:800;">{{ $runs }}/{{ $wkts }}</div>
                <div class="mdx-muted" style="font-size:11px;font-weight:600;">{{ $card['overs'] ?? '0.0' }} ov · RR {{ $card['runRate'] ?? '0.00' }}</div>
            </div>
        </div>
        @if($topBat && (int)($topBat['runs']??0) > 0)
            <div class="mdx-row" style="margin-top:12px;">
                <span class="mdx-muted" style="font-size:12px;">Top scorer</span>
                <span style="font-size:13px;font-weight:600;">{{ $topBat['name'] ?? '' }} &nbsp;{{ (int)$topBat['runs'] }} ({{ (int)($topBat['balls']??0) }})</span>
            </div>
        @endif
        @if($topBowl && (int)($topBowl['wickets']??0) > 0)
            <div class="mdx-row" style="margin-top:8px;">
                <span class="mdx-muted" style="font-size:12px;">Best bowler</span>
                <span style="font-size:13px;font-weight:600;">{{ $topBowl['name'] ?? '' }} &nbsp;{{ (int)$topBowl['wickets'] }}-{{ (int)($topBowl['runs']??0) }} ({{ hrn_overs_from_balls((int)($topBowl['balls']??0)) }})</span>
            </div>
        @endif
    </div>
@endforeach

{{-- Squads --}}
@foreach($squads as [$teamName, $squad])
    @if(count($squad) > 0)
        <div class="mdx-card">
            <div class="mdx-row">
                <span class="mdx-sec-title">{{ strtoupper($teamName) }}</span>
                <span class="mdx-muted" style="font-size:11px;font-weight:600;">{{ count($squad) }} players</span>
            </div>
            <div class="mdx-squad-grid" style="margin-top:12px;">
                @foreach($squad as $m)
                    <div class="mdx-squad-cell">
                        <span class="mdx-avatar" style="width:26px;height:26px;font-size:11px;">{{ hrn_initial($m['name']) }}</span>
                        <span style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $m['name'] }}</span>
                        @if($m['guest'])<span class="mdx-guest">G</span>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endforeach
@endsection
