@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    $d = $detail;
    $isLive = (bool) ($d['isLive'] ?? false);
    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];

    $striker = trim((string) ($d['striker'] ?? ''));
    $nonStriker = trim((string) ($d['nonStriker'] ?? ''));
    $strikerRB = hrn_parse_rb($d['strikerStats'] ?? '');
    $nonStrikerRB = hrn_parse_rb($d['nonStrikerStats'] ?? '');
    $bowler = trim((string) ($d['bowler'] ?? ''));
    $bowlerStats = trim((string) ($d['bowlerStats'] ?? ''));
    $thisOver = is_array($d['thisOver'] ?? null) ? $d['thisOver'] : [];
    $recentOvers = is_array($d['recentOvers'] ?? null) ? $d['recentOvers'] : [];
    $partnership = $d['partnership'] ?? null;

    // Chase tracker
    $showChase = $isLive && count($cards) >= 2;
    $chaseData = null;
    if ($showChase) {
        $first = $cards[0]; $chase = end($cards);
        $target = (int) ($first['runs'] ?? 0) + 1;
        $maxOvers = 20;
        if (preg_match('/(\d+)/', (string) ($d['formatLabel'] ?? ''), $mm) && (int) $mm[1] > 0) $maxOvers = (int) $mm[1];
        $ov = explode('.', (string) ($chase['overs'] ?? '0.0'));
        $ballsBowled = ((int) ($ov[0] ?? 0)) * 6 + ((int) ($ov[1] ?? 0));
        $ballsLeft = max($maxOvers * 6 - $ballsBowled, 0);
        $need = max($target - (int) ($chase['runs'] ?? 0), 0);
        $rrr = $ballsLeft > 0 ? number_format($need / ($ballsLeft / 6.0), 2) : '—';
        $chaseData = compact('chase', 'target', 'ballsLeft', 'need', 'rrr');
    }

    // Win chance — only when it's not the flat 50/50 default.
    $pct = (int) round(((float) ($d['winProbTeam1'] ?? 0.5)) * 100);
    $showWin = $isLive && $pct !== 50 && count($cards) >= 1;

    $last3 = array_slice($recentOvers, -3);
@endphp

@if($showChase && $chaseData)
    <div class="mdx-card">
        <div style="font-size:15px;font-weight:900;">{{ $chaseData['chase']['battingName'] ?? '' }} need {{ $chaseData['need'] }} off {{ $chaseData['ballsLeft'] }}</div>
        <div class="mdx-chase-stats">
            <div class="mdx-chase-stat"><b>{{ $chaseData['target'] }}</b><span>TARGET</span></div>
            <div class="mdx-chase-stat"><b>{{ $chaseData['chase']['runRate'] ?? '0.00' }}</b><span>CRR</span></div>
            <div class="mdx-chase-stat"><b>{{ $chaseData['rrr'] }}</b><span>REQ RATE</span></div>
        </div>
    </div>
@endif

{{-- Live action card --}}
<div class="mdx-card">
    <div class="mdx-bat-head">
        <span class="h-name">BATTING</span>
        <span class="mdx-col-rb">R (B)</span>
        <span class="mdx-col-sr">SR</span>
    </div>

    @if($striker !== '')
        <div class="mdx-bat-line" style="margin-top:10px;">
            <div class="b-name">
                <span class="mdx-avatar">{{ hrn_initial($striker) }}</span>
                <span class="mdx-bat-name striker">{{ $striker }}*</span>
            </div>
            <span class="mdx-bat-rb">{{ $strikerRB['runs'] }} ({{ $strikerRB['balls'] }})</span>
            <span class="mdx-bat-sr">{{ hrn_sr($strikerRB['runs'], $strikerRB['balls']) }}</span>
        </div>
    @endif
    @if($nonStriker !== '')
        <div class="mdx-bat-line" style="margin-top:10px;">
            <div class="b-name">
                <span class="mdx-avatar">{{ hrn_initial($nonStriker) }}</span>
                <span class="mdx-bat-name">{{ $nonStriker }}</span>
            </div>
            <span class="mdx-bat-rb">{{ $nonStrikerRB['runs'] }} ({{ $nonStrikerRB['balls'] }})</span>
            <span class="mdx-bat-sr">{{ hrn_sr($nonStrikerRB['runs'], $nonStrikerRB['balls']) }}</span>
        </div>
    @endif

    @if($partnership && ((int)($partnership['runs']??0) > 0 || (int)($partnership['balls']??0) > 0))
        <div class="mdx-row" style="margin-top:10px;">
            <span class="mdx-muted" style="font-size:11px;font-weight:600;">Partnership</span>
            <span style="font-size:13px;font-weight:700;">{{ (int)$partnership['runs'] }} ({{ (int)$partnership['balls'] }})</span>
        </div>
    @endif

    @if($striker !== '' || $bowler !== '')<div class="mdx-divider"></div>@endif

    @if($bowler !== '')
        <div class="mdx-row">
            <span class="mdx-sec-title">BOWLER</span>
            <span style="font-size:13px;"><b style="font-weight:700;">{{ $bowler }}</b>@if($bowlerStats!=='')<span class="mdx-muted">&nbsp; {{ $bowlerStats }}</span>@endif</span>
        </div>
    @endif

    @if(count($thisOver) > 0)
        <div class="mdx-divider"></div>
        <div class="mdx-row">
            <span class="mdx-sec-title">THIS OVER</span>
            <span style="display:flex;gap:6px;">
                @foreach($thisOver as $ball)
                    <span class="mdx-chip {{ hrn_ball_kind($ball) }}">{{ strtoupper($ball) === 'W' ? 'W' : $ball }}</span>
                @endforeach
            </span>
        </div>
    @endif

    @if(count($last3) > 0)
        <div style="margin-top:16px;" class="mdx-sec-title">LAST 3 OVERS</div>
        <div class="mdx-overbox-row" style="margin-top:8px;">
            @foreach($last3 as $ov)
                <div class="mdx-overbox">
                    <b>{{ (int) ($ov['runs'] ?? 0) }}</b>
                    <i></i>
                    <span>{{ $ov['label'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if($showWin)
    @php $winner = $pct >= 50 ? ($d['team1'] ?? '') : ($d['team2'] ?? ''); $shown = $pct >= 50 ? $pct : 100 - $pct; @endphp
    <div class="mdx-card">
        <div class="mdx-row" style="align-items:flex-end;">
            <div><span style="color:var(--blue);font-weight:900;font-size:18px;">{{ $shown }}%</span> <span style="font-weight:700;font-size:14px;">{{ $winner }} Win Chance</span></div>
            <span class="mdx-muted" style="font-size:11px;">After {{ $d['overs'] ?? '' }} ov</span>
        </div>
        <div class="mdx-winbar"><i style="width: {{ max($pct, 100 - $pct) }}%;"></i></div>
    </div>
@endif

@if($striker === '' && count($thisOver) === 0 && count($recentOvers) === 0)
    <div class="mdx-card"><div class="mdx-empty">Live figures appear here once scoring starts.</div></div>
@endif
@endsection
