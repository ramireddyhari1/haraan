@extends('site.actionboard-match-layout')
@section('match_content')
@include('site.partials.match-helpers')
@php
    $d = $detail;
    $isLive = (bool) ($d['isLive'] ?? false);
    $status = trim((string) ($d['status'] ?? ''));
    $recentOvers = is_array($d['recentOvers'] ?? null) ? $d['recentOvers'] : [];
    $thisOver = is_array($d['thisOver'] ?? null) ? $d['thisOver'] : [];
    $commentary = is_array($d['commentary'] ?? null) ? $d['commentary'] : [];
    $cards = is_array($d['inningsCards'] ?? null) ? $d['inningsCards'] : [];

    $striker = trim((string) ($d['striker'] ?? ''));
    $nonStriker = trim((string) ($d['nonStriker'] ?? ''));
    $strikerRB = hrn_parse_rb($d['strikerStats'] ?? '');
    $nonStrikerRB = hrn_parse_rb($d['nonStrikerStats'] ?? '');
    $bowler = trim((string) ($d['bowler'] ?? ''));

    // Bowler line "1-41 (3.5)" -> figures + overs + econ.
    $bwFig = ''; $bwOv = ''; $bwEcon = '0.00';
    if (preg_match('/(\d+)\s*-\s*(\d+)\s*\(([\d.]+)\)/', (string) ($d['bowlerStats'] ?? ''), $bm)) {
        $bwFig = $bm[1] . '-' . $bm[2];
        $bwOv = $bm[3];
        $ovp = explode('.', $bm[3]); $balls = ((int)($ovp[0]??0))*6 + ((int)($ovp[1]??0));
        $bwEcon = hrn_econ((int)$bm[2], $balls);
    }

    $partnership = $d['partnership'] ?? null;
    $lastWicket = $d['lastWicket'] ?? null;
    $pShip = ($partnership && ((int)($partnership['runs']??0)>0 || (int)($partnership['balls']??0)>0)) ? "P'Ship: ".(int)$partnership['runs']." (".(int)$partnership['balls'].")" : '';
    $lastWkt = ($lastWicket && trim((string)($lastWicket['name']??''))!=='') ? "Last wkt: ".$lastWicket['name']." ".(int)($lastWicket['runs']??0)." (".(int)($lastWicket['balls']??0).")" : '';

    // Fall-of-wicket lookup for enriching wicket banners.
    $fowLookup = function (int $inn, string $over) use ($cards) {
        foreach ($cards as $c) {
            if ((int)($c['number']??0) !== $inn) continue;
            foreach ((array)($c['fow']??[]) as $f) {
                if ((string)($f['over']??'') === $over) {
                    $bat = (string)($f['batter']??'');
                    $fig = null;
                    foreach ((array)($c['batters']??[]) as $b) { if ((string)($b['name']??'')===$bat) { $fig = (int)($b['runs']??0).' ('.(int)($b['balls']??0).')'; break; } }
                    return ['batter'=>$bat, 'score'=>(int)($f['score']??0).'-'.(int)($f['wicketNo']??0), 'fig'=>$fig];
                }
            }
        }
        return null;
    };
@endphp

@if(!$isLive && $status !== '')
    <div style="background:rgba(245,158,11,.1);color:var(--yellow);font-size:11px;font-weight:600;text-align:center;padding:6px;border-radius:10px;margin:-4px 0 0;">{{ $status }}</div>
@endif

{{-- Over tracker --}}
@if(count($recentOvers) > 0 || count($thisOver) > 0)
    <div class="mdx-overchips">
        @if(count($recentOvers) > 0)
            @foreach($recentOvers as $ov)
                <div class="mdx-overchip {{ $isLive && $loop->last ? 'current' : '' }}">
                    <div class="ol"><em>OVER</em><b>{{ $ov['label'] ?? '' }}</b></div>
                    <div class="odiv"></div>
                    <div class="oballs">@foreach(($ov['balls']??[]) as $b)<span class="mdx-chip sm {{ hrn_ball_kind($b) }}">{{ strtoupper($b)==='W'?'W':$b }}</span>@endforeach</div>
                    <div class="osum">= {{ (int)($ov['runs']??0) }}</div>
                </div>
            @endforeach
        @else
            @php $tor = 0; foreach($thisOver as $b){ $bi=(int)$b; $tor += is_numeric($b)?$bi:((stripos($b,'wd')===0||stripos($b,'nb')===0)?1:0); } @endphp
            <div class="mdx-overchip current">
                <div class="ol"><em>OVER</em><b>·</b></div>
                <div class="odiv"></div>
                <div class="oballs">@foreach($thisOver as $b)<span class="mdx-chip sm {{ hrn_ball_kind($b) }}">{{ strtoupper($b)==='W'?'W':$b }}</span>@endforeach</div>
                <div class="osum">= {{ $tor }}</div>
            </div>
        @endif
    </div>
@endif

{{-- Mini scorecard --}}
@if($striker !== '' || $bowler !== '')
<div class="mdx-card mdx-card--flush">
    <div class="mdx-bat-head" style="padding:8px 16px;border-bottom:1px solid var(--border);">
        <span class="h-name">BATTER</span>
        <span style="width:38px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;">R</span>
        <span style="width:34px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;">B</span>
        <span style="width:30px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;">4S</span>
        <span style="width:30px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;">6S</span>
    </div>
    @if($striker !== '')
        <div class="mdx-comm-row" style="border-bottom:1px solid var(--border);">
            <span class="mdx-avatar" style="width:36px;height:36px;">{{ hrn_initial($striker) }}</span>
            <div style="flex:1;"><div style="font-size:14px;font-weight:600;">{{ $striker }} *</div><div class="mdx-muted" style="font-size:10px;letter-spacing:1px;">SR {{ hrn_sr($strikerRB['runs'],$strikerRB['balls']) }}</div></div>
            <span style="width:38px;text-align:center;font-weight:600;">{{ $strikerRB['runs'] }}</span>
            <span style="width:34px;text-align:center;color:var(--ink2);">{{ $strikerRB['balls'] }}</span>
            <span style="width:30px;text-align:center;color:var(--ink2);">0</span>
            <span style="width:30px;text-align:center;color:var(--ink2);">0</span>
        </div>
    @endif
    @if($nonStriker !== '')
        <div class="mdx-comm-row" style="border-bottom:1px solid var(--border);">
            <span class="mdx-avatar" style="width:36px;height:36px;">{{ hrn_initial($nonStriker) }}</span>
            <div style="flex:1;"><div style="font-size:14px;font-weight:600;">{{ $nonStriker }}</div><div class="mdx-muted" style="font-size:10px;letter-spacing:1px;">SR {{ hrn_sr($nonStrikerRB['runs'],$nonStrikerRB['balls']) }}</div></div>
            <span style="width:38px;text-align:center;font-weight:600;">{{ $nonStrikerRB['runs'] }}</span>
            <span style="width:34px;text-align:center;color:var(--ink2);">{{ $nonStrikerRB['balls'] }}</span>
            <span style="width:30px;text-align:center;color:var(--ink2);">0</span>
            <span style="width:30px;text-align:center;color:var(--ink2);">0</span>
        </div>
    @endif
    @if($pShip !== '' || $lastWkt !== '')
        <div class="mdx-row" style="background:var(--bg);padding:10px 16px;border-bottom:1px solid var(--border);">
            <span class="mdx-muted" style="font-size:10px;font-weight:600;">{{ $pShip }}</span>
            <span class="mdx-muted" style="font-size:10px;font-weight:600;">{{ $lastWkt }}</span>
        </div>
    @endif
    @if($bowler !== '')
        <div class="mdx-bat-head" style="padding:8px 16px;border-bottom:1px solid var(--border);">
            <span class="h-name">BOWLER</span>
            <span style="width:48px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;">W-R</span>
            <span style="width:36px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;">OV</span>
            <span style="width:48px;text-align:center;color:var(--muted);font-size:10px;font-weight:600;">ECON</span>
        </div>
        <div class="mdx-comm-row">
            <span class="mdx-avatar" style="width:36px;height:36px;">{{ hrn_initial($bowler) }}</span>
            <div style="flex:1;"><div style="font-size:14px;font-weight:600;">{{ $bowler }}</div><div class="mdx-muted" style="font-size:10px;letter-spacing:1px;">BOWLER</div></div>
            <span style="width:48px;text-align:center;font-weight:600;">{{ $bwFig !== '' ? $bwFig : '0-0' }}</span>
            <span style="width:36px;text-align:center;color:var(--ink2);">{{ $bwOv !== '' ? $bwOv : '0.0' }}</span>
            <span style="width:48px;text-align:center;color:var(--ink2);">{{ $bwEcon }}</span>
        </div>
    @endif
</div>
@endif

{{-- Ball-by-ball feed --}}
@if(count($commentary) > 0)
    <div class="mdx-sec-title" style="margin-bottom:-4px;">COMMENTARY</div>
    @foreach($commentary as $line)
        @php $kind = (string)($line['kind']??'ball'); @endphp
        @if($kind === 'header')
            <div class="mdx-comm-head">{{ $line['text'] ?? '' }}</div>
        @elseif($kind === 'batter_in')
            @php $c = $line['career'] ?? null; $nm = trim((string)($line['text']??'')) ?: 'New batter'; @endphp
            <div class="mdx-banner newbat">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="mdx-banner-av">{{ hrn_initial($nm) }}</span>
                    <div style="flex:1;"><div class="bn-tag">NEW BATTER</div><div class="bn-name">{{ $nm }}</div></div>
                    @if(trim((string)($line['over']??''))!=='')<span class="bn-sub" style="font-weight:600;">{{ $line['over'] }} ov</span>@endif
                </div>
                @if($c && ((int)($c['innings']??0) > 0 || (int)($c['balls']??0) > 0))
                    <div class="mdx-career">
                        <div><b>{{ (int)($c['innings']??0) }}</b><span>INN</span></div>
                        <div><b>{{ (int)($c['runs']??0) }}</b><span>RUNS</span></div>
                        <div><b>{{ (int)($c['balls']??0) }}</b><span>BALLS</span></div>
                        <div><b>{{ (int)($c['highScore']??0) }}</b><span>HS</span></div>
                        <div><b>{{ isset($c['avg']) && $c['avg']!==null ? number_format((float)$c['avg'],1) : '—' }}</b><span>AVG</span></div>
                        <div><b>{{ isset($c['sr']) && $c['sr']!==null ? number_format((float)$c['sr'],1) : '—' }}</b><span>SR</span></div>
                    </div>
                @else
                    <div class="bn-sub" style="margin-top:8px;">First recorded innings</div>
                @endif
            </div>
        @elseif(!empty($line['wicket']))
            @php
                $inn=(int)($line['innings']??1); $over=(string)($line['over']??'');
                $fw = $fowLookup($inn,$over);
                $dismissal = trim(preg_replace('/^OUT!/','', (string)($line['text']??''))) ?: 'out';
                $bat = $fw['batter'] ?? trim((string)($line['battingName']??'')) ?: 'Batter';
            @endphp
            <div class="mdx-banner wkt">
                <span class="mdx-banner-av">{{ hrn_initial($bat) }}</span>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:6px;"><span class="bn-tag">WICKET</span><span style="background:#fff;color:#B91C1C;font-size:8px;font-weight:900;padding:1px 5px;border-radius:4px;">OUT</span></div>
                    <div class="bn-name" style="margin-top:3px;">{{ $bat }}@if(!empty($fw['fig'])) &nbsp;{{ $fw['fig'] }}@endif</div>
                    <div class="bn-sub">{{ $dismissal }}</div>
                </div>
                <div style="text-align:right;">
                    @if(!empty($fw['score']))<div style="font-size:15px;font-weight:900;">{{ $fw['score'] }}</div>@endif
                    @if($over!=='')<div class="bn-sub" style="font-weight:600;">{{ $over }} ov</div>@endif
                </div>
            </div>
        @else
            @php $label=(string)($line['label']??''); $kindc = hrn_ball_kind($label); @endphp
            <div class="mdx-comm-row {{ !empty($line['wicket'])?'wkt':'' }} {{ !empty($line['boundary'])?'boundary':'' }}" style="border-radius:0;">
                <span class="cov">{{ $line['over'] ?? '' }}</span>
                <span class="mdx-chip lg {{ $kindc }}">{{ $label==='0'||$label==='' ? '•' : (strtoupper($label)==='W'?'W':$label) }}</span>
                <span class="ctext">{{ $line['text'] ?? '' }}</span>
            </div>
        @endif
    @endforeach
@endif

@if(count($commentary) === 0 && $striker === '' && count($thisOver) === 0 && count($recentOvers) === 0)
    <div class="mdx-card"><div class="mdx-empty">No commentary yet — it'll appear here once scoring begins.</div></div>
@endif
@endsection
