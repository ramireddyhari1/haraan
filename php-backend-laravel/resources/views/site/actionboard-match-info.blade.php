@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    $d = $detail;
    $team1 = (string) ($d['team1'] ?? '');
    $team2 = (string) ($d['team2'] ?? '');
    $team1Full = trim((string) ($d['team1Full'] ?? '')) ?: $team1;
    $team2Full = trim((string) ($d['team2Full'] ?? '')) ?: $team2;
    $logo1 = hrn_team_icon($d['team1Logo'] ?? '', $d['team1Emblem'] ?? '');
    $logo2 = hrn_team_icon($d['team2Logo'] ?? '', $d['team2Emblem'] ?? '');
    $col1 = hrn_mono_color($team1Full);
    $col2 = hrn_mono_color($team2Full);
    $competition = trim((string) ($d['formatLabel'] ?? ''));

    // Drop a "venue" that just echoes a team name (reads as placeholder data).
    $teamsLower = array_map('mb_strtolower', array_filter([$team1, $team2, $team1Full, $team2Full], fn ($s) => trim((string) $s) !== ''));
    $venue = trim((string) ($d['venue'] ?? ''));
    $venueOk = $venue !== '' && !in_array(mb_strtolower($venue), $teamsLower, true);

    // Facts strip — icon · label · value. Only what we actually have.
    $facts = [];
    if (trim((string) ($d['status'] ?? '')) !== '') $facts[] = ['icon' => 'status', 'k' => 'Status', 'v' => $d['status']];
    if ($venueOk) $facts[] = ['icon' => 'venue', 'k' => 'Venue', 'v' => $venue];
    // "Starts" when the creator set a time, "Started" when that is simply when scoring began.
    if (trim((string) ($d['startLabel'] ?? '')) !== '') $facts[] = ['icon' => 'time', 'k' => !empty($d['startIsScheduled']) ? 'Starts' : 'Started', 'v' => $d['startLabel']];
    if (trim((string) ($d['toss'] ?? '')) !== '') $facts[] = ['icon' => 'toss', 'k' => 'Toss', 'v' => $d['toss']];

    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];

    $normSquad = function ($squad) {
        $out = [];
        foreach ((array) $squad as $m) {
            if (is_array($m)) {
                $name = trim((string) ($m['name'] ?? ''));
                $pid = trim((string) ($m['id'] ?? ($m['player_id'] ?? '')));
                if ($name !== '' && strtolower($name) !== 'null') $out[] = ['name' => $name, 'photo' => trim((string) ($m['photo'] ?? '')), 'guest' => ($pid === '' || strtolower($pid) === 'null')];
            } else {
                $name = trim((string) $m);
                if ($name !== '' && strtolower($name) !== 'null') $out[] = ['name' => $name, 'photo' => '', 'guest' => false];
            }
        }
        return $out;
    };
    $squads = [
        [$team1Full, $normSquad($d['homeSquad'] ?? []), $col1],
        [$team2Full, $normSquad($d['awaySquad'] ?? []), $col2],
    ];

@endphp

{{-- ── Matchup overview (InfoTab MatchOverviewCard): teams on one line, then plain fact rows ── --}}
<div class="mdx-card info-card">
    @if($competition !== '')<div class="info-eyebrow">{{ strtoupper($competition) }}</div>@endif
    <div class="info-matchup">
        <div class="info-side">
            <span class="info-crest" @if($logo1==='')style="background:{{ $col1 }}"@endif>@if($logo1!=='')<img src="{{ $logo1 }}" alt="">@else{{ hrn_team_code($team1Full) }}@endif</span>
            <b>{{ strtoupper($team1Full) }}</b>
        </div>
        <span class="info-vs">vs</span>
        <div class="info-side is-r">
            <b>{{ strtoupper($team2Full) }}</b>
            <span class="info-crest" @if($logo2==='')style="background:{{ $col2 }}"@endif>@if($logo2!=='')<img src="{{ $logo2 }}" alt="">@else{{ hrn_team_code($team2Full) }}@endif</span>
        </div>
    </div>
    @if(count($facts) > 0)
        <div class="info-rule"></div>
        @foreach($facts as $f)
            <div class="info-fact"><span>{{ $f['k'] }}</span><b>{{ $f['v'] }}</b></div>
        @endforeach
    @endif
</div>

{{-- ── Per-innings summaries ── --}}
@foreach($cards as $card)
    @php
        $runs = (int) ($card['runs'] ?? 0); $wkts = (int) ($card['wickets'] ?? 0);
        $battingName = (string) ($card['battingName'] ?? '');
        $batters = is_array($card['batters'] ?? null) ? $card['batters'] : [];
        $bowlers = is_array($card['bowlers'] ?? null) ? $card['bowlers'] : [];
        $topBat = null; foreach ($batters as $b) { if ($topBat === null || (int)($b['runs']??0) > (int)($topBat['runs']??0)) $topBat = $b; }
        $topBowl = null; foreach ($bowlers as $b) { $sc = (int)($b['wickets']??0)*100 - (int)($b['runs']??0); if ($topBowl === null || $sc > ((int)($topBowl['wickets']??0)*100 - (int)($topBowl['runs']??0))) $topBowl = $b; }
        $hasBat = $topBat && (int)($topBat['runs']??0) > 0;
        $hasBowl = $topBowl && (int)($topBowl['wickets']??0) > 0;
    @endphp
    <div class="mdx-card info-card">
        <div class="info-inn-top">
            <div class="info-inn-id">
                <span class="info-inn-eyebrow">INNINGS {{ $card['number'] ?? '' }}</span>
                <b>{{ $battingName }}</b>
            </div>
            <div class="info-inn-score">
                <b>{{ $runs }}/{{ $wkts }}</b>
                <span>{{ $card['overs'] ?? '0.0' }} ov · RR {{ $card['runRate'] ?? '0.00' }}</span>
            </div>
        </div>
        @if($hasBat)
            <div class="info-hl"><span>Top scorer</span><b>{{ $topBat['name'] ?? '' }}&ensp;{{ (int)$topBat['runs'] }} ({{ (int)($topBat['balls']??0) }})</b></div>
        @endif
        @if($hasBowl)
            <div class="info-hl"><span>Best bowler</span><b>{{ $topBowl['name'] ?? '' }}&ensp;{{ (int)$topBowl['wickets'] }}-{{ (int)($topBowl['runs']??0) }} ({{ hrn_overs_from_balls((int)($topBowl['balls']??0)) }})</b></div>
        @endif
    </div>
@endforeach

{{-- ── Squads: two a row, a quiet disc each (a face when the player has one) ── --}}
@foreach($squads as [$teamName, $squad, $teamCol])
    @if(count($squad) > 0)
        @php
            $hasReal = false; $hasGuest = false;
            foreach ($squad as $m) { if ($m['guest']) $hasGuest = true; else $hasReal = true; }
            $showGuest = $hasReal && $hasGuest;
        @endphp
        <div class="mdx-card info-card">
            <div class="info-squad-head">
                <span>{{ strtoupper($teamName) }}</span>
                <span>{{ count($squad) }} players</span>
            </div>
            <div class="info-players">
                @foreach($squad as $m)
                    <div class="info-player">
                        @if($m['photo'] !== '')
                            <img class="info-player-av" src="{{ $m['photo'] }}" alt="" loading="lazy">
                        @else
                            <span class="info-player-av">{{ hrn_initial($m['name']) }}</span>
                        @endif
                        <span class="info-player-name">{{ $m['name'] }}</span>
                        @if($showGuest && $m['guest'])<em class="info-guest">G</em>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endforeach

<style>
/* Info tab, as InfoTab.kt draws it: three plain cards, rows not tiles. */
.info-card { padding: 18px; }
.info-eyebrow { color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: 1px; margin-bottom: 14px; }
.info-matchup { display: flex; align-items: center; }
.info-side { flex: 1; min-width: 0; display: flex; align-items: center; gap: 10px; }
.info-side.is-r { justify-content: flex-end; text-align: right; }
.info-side b { font-size: 12px; font-weight: 700; color: var(--ink); line-height: 1.3; min-width: 0; overflow-wrap: anywhere; }
.info-crest { width: 34px; height: 34px; border-radius: 50%; flex: 0 0 auto; display: grid; place-items: center; overflow: hidden;
    background: #fff; border: 1px solid #CBD5E1; color: #fff; font-size: 11px; font-weight: 800; }
.info-crest img { width: 100%; height: 100%; object-fit: cover; }
.info-vs { flex: 0 0 auto; padding: 0 8px; color: var(--muted); font-size: 12px; }
.info-rule { height: 1px; background: var(--border); margin: 16px 0; }
.info-fact { display: flex; align-items: flex-start; font-size: 13px; }
.info-fact + .info-fact { margin-top: 12px; }
.info-fact span { width: 88px; flex: 0 0 auto; color: var(--muted); }
.info-fact b { flex: 1; min-width: 0; font-weight: 600; color: var(--ink); }

.info-inn-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
.info-inn-id { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.info-inn-eyebrow { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: 1px; }
.info-inn-id b { font-size: 15px; font-weight: 700; color: var(--ink); }
.info-inn-score { text-align: right; flex: 0 0 auto; }
.info-inn-score b { display: block; font-family: 'Archivo Black', sans-serif; font-weight: 400; font-size: 22px; color: var(--ink); font-variant-numeric: tabular-nums; line-height: 1.15; }
.info-inn-score span { font-size: 11px; font-weight: 600; color: var(--muted); font-variant-numeric: tabular-nums; }
.info-hl { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-top: 12px; }
.info-hl + .info-hl { margin-top: 8px; }
.info-hl span { font-size: 12px; color: var(--muted); flex: 0 0 auto; }
.info-hl b { font-size: 13px; font-weight: 600; color: var(--ink); text-align: right; font-variant-numeric: tabular-nums; }

.info-squad-head { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 12px; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: 1px; }
.info-squad-head span:last-child { letter-spacing: 0; font-weight: 600; flex: 0 0 auto; }
.info-players { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 10px; }
.info-player { display: flex; align-items: center; gap: 8px; min-width: 0; }
.info-player-av { width: 26px; height: 26px; border-radius: 50%; flex: 0 0 auto; display: grid; place-items: center; object-fit: cover;
    background: var(--bg); border: 1px solid var(--border); color: var(--ink2); font-size: 11px; font-weight: 700; }
.info-player-name { font-size: 13px; font-weight: 600; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.info-guest { flex: 0 0 auto; font-style: normal; background: var(--border); color: var(--ink2); font-size: 8px; font-weight: 700; padding: 1px 5px; border-radius: 5px; }
</style>
@endsection
