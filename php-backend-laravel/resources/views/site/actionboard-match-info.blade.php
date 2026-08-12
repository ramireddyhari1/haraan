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
    if (trim((string) ($d['toss'] ?? '')) !== '') $facts[] = ['icon' => 'toss', 'k' => 'Toss', 'v' => $d['toss']];

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

    $factIcon = [
        'status' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4"/><circle cx="12" cy="12" r="2" fill="currentColor" stroke="none"/></svg>',
        'venue'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
        'toss'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.66 3.58 3 8 3s8-1.34 8-3V6M4 12v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg>',
    ];
@endphp

{{-- ── Matchup overview ── --}}
<div class="mdx-card">
    @if($competition !== '')<div class="info-format">{{ $competition }}</div>@endif
    <div class="info-matchup">
        <div class="info-team">
            <span class="info-crest" style="background:{{ $col1 }}">@if($logo1!=='')<img src="{{ $logo1 }}" alt="">@else{{ hrn_team_code($team1Full) }}@endif</span>
            <b>{{ $team1Full }}</b>
        </div>
        <span class="info-vs">VS</span>
        <div class="info-team">
            <span class="info-crest" style="background:{{ $col2 }}">@if($logo2!=='')<img src="{{ $logo2 }}" alt="">@else{{ hrn_team_code($team2Full) }}@endif</span>
            <b>{{ $team2Full }}</b>
        </div>
    </div>
    @if(count($facts) > 0)
        <div class="info-facts">
            @foreach($facts as $f)
                <div class="info-fact">
                    {!! $factIcon[$f['icon']] !!}
                    <span class="fk">{{ $f['k'] }}</span>
                    <span class="fv">{{ $f['v'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ── Per-innings summaries ── --}}
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
        $hasBat = $topBat && (int)($topBat['runs']??0) > 0;
        $hasBowl = $topBowl && (int)($topBowl['wickets']??0) > 0;
    @endphp
    <div class="mdx-card info-inn" style="--inn:{{ $innCol }}">
        <div class="info-inn-top">
            <span class="info-inn-crest" style="background:{{ $innCol }}">{{ hrn_team_code($battingName) }}</span>
            <div class="info-inn-id">
                <span class="info-inn-eyebrow">Innings {{ $card['number'] ?? '' }}</span>
                <span class="info-inn-name">{{ $battingName }}</span>
            </div>
            <div class="info-inn-score">
                <b>{{ $runs }}<i>/{{ $wkts }}</i></b>
                <span>{{ $card['overs'] ?? '0.0' }} ov · RR {{ $card['runRate'] ?? '0.00' }}</span>
            </div>
        </div>
        @if($hasBat || $hasBowl)
            <div class="info-perf-wrap">
                @if($hasBat)
                    <div class="info-perf">
                        <span class="info-perf-av" style="background:{{ hrn_mono_color($topBat['name'] ?? '') }}">{{ hrn_initial($topBat['name'] ?? '') }}</span>
                        <div class="info-perf-meta">
                            <span class="info-perf-role">Top scorer</span>
                            <span class="info-perf-name">{{ $topBat['name'] ?? '' }}</span>
                        </div>
                        <span class="info-perf-pill">{{ (int)$topBat['runs'] }} ({{ (int)($topBat['balls']??0) }})</span>
                    </div>
                @endif
                @if($hasBowl)
                    <div class="info-perf">
                        <span class="info-perf-av" style="background:{{ hrn_mono_color($topBowl['name'] ?? '') }}">{{ hrn_initial($topBowl['name'] ?? '') }}</span>
                        <div class="info-perf-meta">
                            <span class="info-perf-role">Best bowler</span>
                            <span class="info-perf-name">{{ $topBowl['name'] ?? '' }}</span>
                        </div>
                        <span class="info-perf-pill">{{ (int)$topBowl['wickets'] }}-{{ (int)($topBowl['runs']??0) }} ({{ hrn_overs_from_balls((int)($topBowl['balls']??0)) }})</span>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endforeach

{{-- ── Squads (flush list, not another boxed card) ── --}}
@foreach($squads as [$teamName, $squad, $teamCol])
    @if(count($squad) > 0)
        @php
            $hasReal = false; $hasGuest = false;
            foreach ($squad as $m) { if ($m['guest']) $hasGuest = true; else $hasReal = true; }
            $showGuest = $hasReal && $hasGuest;
        @endphp
        <div class="mdx-card">
            <div class="info-squad-head">
                <span class="info-inn-crest" style="width:30px;height:30px;font-size:11px;background:{{ $teamCol }}">{{ hrn_team_code($teamName) }}</span>
                <b>{{ $teamName }}</b>
                <span class="info-chip">{{ count($squad) }} players</span>
            </div>
            <div class="info-players">
                @foreach($squad as $m)
                    <div class="info-player">
                        <span class="info-player-av" style="background:{{ hrn_mono_color($m['name']) }}">{{ hrn_initial($m['name']) }}</span>
                        <span>{{ $m['name'] }}</span>
                        @if($showGuest && $m['guest'])<em class="info-guest">G</em>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endforeach

<style>
/* ── Info tab design system (8pt spacing · tabular numerals · layered surfaces) ── */
.info-format {
    display: inline-flex; align-self: center; margin: 0 auto 16px;
    background: #EEF4FF; color: #2563EB; font-size: 11px; font-weight: 700;
    letter-spacing: .3px; padding: 5px 13px; border-radius: 999px;
}
.mdx-content .info-format { display: flex; width: fit-content; }

.info-matchup { display: flex; align-items: flex-start; gap: 12px; }
.info-team { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.info-crest {
    width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center;
    color: #fff; font-size: 15px; font-weight: 800; overflow: hidden;
    box-shadow: 0 0 0 3px #fff, 0 3px 10px rgba(15,23,42,.14);
}
.info-crest img { width: 100%; height: 100%; object-fit: cover; }
.info-team b { font-size: 14px; font-weight: 700; color: #0F172A; text-align: center; line-height: 1.25; }
.info-vs {
    flex: 0 0 auto; margin-top: 12px; width: 32px; height: 32px; border-radius: 50%;
    background: #0F172A; color: #fff; display: grid; place-items: center; font-size: 10px; font-weight: 800;
}

.info-facts { display: flex; gap: 8px; margin-top: 20px; }
.info-fact {
    flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 6px;
    text-align: center; padding: 12px 8px; background: #F6F8FB; border-radius: 12px;
}
.info-fact svg { width: 18px; height: 18px; color: #64748B; }
.info-fact .fk { font-size: 9.5px; font-weight: 700; letter-spacing: .5px; color: #94A3B8; text-transform: uppercase; }
.info-fact .fv { font-size: 12.5px; font-weight: 700; color: #0F172A; line-height: 1.3; }

/* Innings */
.info-inn { border-left: 3px solid var(--inn); }
.info-inn-top { display: flex; align-items: center; gap: 12px; }
.info-inn-crest {
    width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center;
    color: #fff; font-size: 12px; font-weight: 800; flex: 0 0 auto;
    box-shadow: 0 0 0 2px #fff, 0 2px 6px rgba(15,23,42,.12);
}
.info-inn-id { display: flex; flex-direction: column; min-width: 0; }
.info-inn-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .5px; color: #94A3B8; text-transform: uppercase; }
.info-inn-name { font-size: 16px; font-weight: 800; color: #0F172A; line-height: 1.2; margin-top: 2px; }
.info-inn-score { margin-left: auto; text-align: right; }
.info-inn-score b { font-size: 28px; font-weight: 800; letter-spacing: -1px; color: #0F172A; font-variant-numeric: tabular-nums; }
.info-inn-score b i { font-size: 17px; font-weight: 800; font-style: normal; color: #94A3B8; }
.info-inn-score span { display: block; font-size: 11px; font-weight: 600; color: #94A3B8; margin-top: 3px; font-variant-numeric: tabular-nums; }

.info-perf-wrap { margin-top: 16px; padding-top: 4px; border-top: 1px solid #F1F5F9; }
.info-perf { display: flex; align-items: center; gap: 10px; padding: 10px 0; }
.info-perf + .info-perf { border-top: 1px solid #F5F7FA; }
.info-perf-av { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 12px; font-weight: 700; flex: 0 0 auto; }
.info-perf-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.info-perf-role { font-size: 9.5px; font-weight: 700; letter-spacing: .5px; color: #94A3B8; text-transform: uppercase; }
.info-perf-name { font-size: 14px; font-weight: 600; color: #0F172A; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.info-perf-pill { flex: 0 0 auto; font-size: 12.5px; font-weight: 800; color: #0F172A; background: #F1F5F9; border-radius: 9px; padding: 6px 11px; font-variant-numeric: tabular-nums; }

/* Squads */
.info-squad-head { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.info-squad-head b { font-size: 15px; font-weight: 800; color: #0F172A; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.info-chip { margin-left: auto; flex: 0 0 auto; background: #F1F5F9; color: #475569; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
.info-players { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 14px; }
.info-player { display: flex; align-items: center; gap: 9px; min-width: 0; }
.info-player-av { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 11px; font-weight: 700; flex: 0 0 auto; }
.info-player span { font-size: 13px; font-weight: 600; color: #0F172A; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.info-guest { flex: 0 0 auto; font-style: normal; background: #E2E8F0; color: #475569; font-size: 8px; font-weight: 700; padding: 1px 5px; border-radius: 5px; }
</style>
@endsection
