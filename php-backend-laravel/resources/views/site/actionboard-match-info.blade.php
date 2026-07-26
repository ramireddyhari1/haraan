@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    $d = $detail;
    $team1 = (string) ($d['team1'] ?? '');
    $team2 = (string) ($d['team2'] ?? '');
    $team1Full = trim((string) ($d['team1Full'] ?? '')) ?: $team1;
    $team2Full = trim((string) ($d['team2Full'] ?? '')) ?: $team2;
    $logo1 = (string) ($d['team1Logo'] ?? '');
    $logo2 = (string) ($d['team2Logo'] ?? '');
    $col1 = hrn_mono_color($team1Full);
    $col2 = hrn_mono_color($team2Full);
    $competition = trim((string) ($d['formatLabel'] ?? ''));

    // Drop a "venue" that just echoes a team name (or is blank) — it reads as fake
    // placeholder data rather than a real ground.
    $teamsLower = array_map('mb_strtolower', array_filter([$team1, $team2, $team1Full, $team2Full], fn ($s) => trim((string) $s) !== ''));
    $venue = trim((string) ($d['venue'] ?? ''));
    $venueOk = $venue !== '' && !in_array(mb_strtolower($venue), $teamsLower, true);

    $facts = [];
    if (trim((string) ($d['status'] ?? '')) !== '') $facts['Status'] = $d['status'];
    if ($venueOk) $facts['Venue'] = $venue;
    if (trim((string) ($d['toss'] ?? '')) !== '') $facts['Toss'] = $d['toss'];

    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];

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
        [$team1Full, $normSquad($d['homeSquad'] ?? []), $col1],
        [$team2Full, $normSquad($d['awaySquad'] ?? []), $col2],
    ];
@endphp

{{-- Match overview --}}
<div class="mdx-card">
    @if($competition !== '')<div class="mdx-eyebrow" style="margin-bottom:14px;">{{ $competition }}</div>@endif
    <div class="mdx-row">
        <div class="mdx-team-line">
            <span class="mdx-logo-sm" style="background:{{ $col1 }};color:#fff;border-color:transparent;">@if($logo1!=='')<img src="{{ $logo1 }}" alt="">@else{{ hrn_team_code($team1Full) }}@endif</span>
            <b style="font-size:12px;letter-spacing:.3px;">{{ strtoupper($team1Full) }}</b>
        </div>
        <span class="mdx-muted" style="font-size:12px;padding:0 8px;">vs</span>
        <div class="mdx-team-line" style="justify-content:flex-end;flex:1;">
            <b style="font-size:12px;letter-spacing:.3px;text-align:right;">{{ strtoupper($team2Full) }}</b>
            <span class="mdx-logo-sm" style="background:{{ $col2 }};color:#fff;border-color:transparent;">@if($logo2!=='')<img src="{{ $logo2 }}" alt="">@else{{ hrn_team_code($team2Full) }}@endif</span>
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
        $bt = (int) ($card['battingTeam'] ?? 1);
        $innCol = $bt === 2 ? $col2 : $col1;
        $battingName = (string) ($card['battingName'] ?? '');
        $batters = is_array($card['batters'] ?? null) ? $card['batters'] : [];
        $bowlers = is_array($card['bowlers'] ?? null) ? $card['bowlers'] : [];
        $topBat = null; foreach ($batters as $b) { if ($topBat === null || (int)($b['runs']??0) > (int)($topBat['runs']??0)) $topBat = $b; }
        $topBowl = null; foreach ($bowlers as $b) { $sc = (int)($b['wickets']??0)*100 - (int)($b['runs']??0); if ($topBowl === null || $sc > ((int)($topBowl['wickets']??0)*100 - (int)($topBowl['runs']??0))) $topBowl = $b; }
    @endphp
    <div class="mdx-card" style="border-left:3px solid {{ $innCol }};">
        <div class="mdx-row" style="align-items:flex-end;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="mdx-logo-sm" style="width:30px;height:30px;background:{{ $innCol }};color:#fff;border-color:transparent;">{{ hrn_team_code($battingName) }}</span>
                <div>
                    <div class="mdx-eyebrow">Innings {{ $card['number'] ?? '' }}</div>
                    <div style="margin-top:3px;font-size:15px;font-weight:700;">{{ $battingName }}</div>
                </div>
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
@foreach($squads as [$teamName, $squad, $teamCol])
    @if(count($squad) > 0)
        @php
            $hasReal = false; $hasGuest = false;
            foreach ($squad as $m) { if ($m['guest']) $hasGuest = true; else $hasReal = true; }
            $showGuest = $hasReal && $hasGuest; // only badge guests when the sheet is actually mixed
        @endphp
        <div class="mdx-card">
            <div class="mdx-row">
                <div style="display:flex;align-items:center;gap:9px;">
                    <span class="mdx-logo-sm" style="width:24px;height:24px;font-size:9px;background:{{ $teamCol }};color:#fff;border-color:transparent;">{{ hrn_team_code($teamName) }}</span>
                    <span class="mdx-sec-title">{{ strtoupper($teamName) }}</span>
                </div>
                <span class="mdx-muted" style="font-size:11px;font-weight:600;">{{ count($squad) }} players</span>
            </div>
            <div class="mdx-squad-grid" style="margin-top:14px;">
                @foreach($squad as $m)
                    <div class="mdx-squad-cell">
                        <span class="mdx-avatar" style="width:26px;height:26px;font-size:11px;background:{{ hrn_mono_color($m['name']) }};color:#fff;border-color:transparent;">{{ hrn_initial($m['name']) }}</span>
                        <span style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $m['name'] }}</span>
                        @if($showGuest && $m['guest'])<span class="mdx-guest">G</span>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endforeach
@endsection
