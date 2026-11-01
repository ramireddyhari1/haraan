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
    $bwFig = ''; $bwOv = ''; $bwEcon = '0.00'; $bwWkts = 0;
    if (preg_match('/(\d+)\s*-\s*(\d+)\s*\(([\d.]+)\)/', (string) ($d['bowlerStats'] ?? ''), $bm)) {
        $bwFig = $bm[1] . '-' . $bm[2];
        $bwWkts = (int) $bm[1];
        $bwOv = $bm[3];
        $ovp = explode('.', $bm[3]); $balls = ((int)($ovp[0]??0))*6 + ((int)($ovp[1]??0));
        $bwEcon = hrn_econ((int)$bm[2], $balls);
    }

    $partnership = $d['partnership'] ?? null;
    $lastWicket = $d['lastWicket'] ?? null;
    $pShip = ($partnership && ((int)($partnership['runs']??0)>0 || (int)($partnership['balls']??0)>0)) ? "P'Ship: ".(int)$partnership['runs']." (".(int)$partnership['balls'].")" : '';
    $lastWkt = ($lastWicket && trim((string)($lastWicket['name']??''))!=='') ? "Last wkt: ".$lastWicket['name']." ".(int)($lastWicket['runs']??0)." (".(int)($lastWicket['balls']??0).")" : '';

    // Faces come from the squads (linked accounts carry a photo); the app's stock
    // cricketer stands in otherwise — never a letter in a circle.
    $squad = array_merge((array) ($d['homeSquad'] ?? []), (array) ($d['awaySquad'] ?? []));
    $faceOf = function (?string $name, ?string $photo = null) use ($squad): string {
        $photo = trim((string) $photo);
        if ($photo === '') {
            $clean = strtolower(trim(rtrim((string) $name, '* ')));
            foreach ($squad as $p) {
                if (strtolower(trim((string) ($p['name'] ?? ''))) === $clean) { $photo = trim((string) ($p['photo'] ?? '')); break; }
            }
        }
        return $photo !== '' ? $photo : asset('images/default_player_avatar.jpg');
    };
    $verifiedOf = function (string $name) use ($squad): bool {
        foreach ($squad as $p) { if (strcasecmp(trim((string) ($p['name'] ?? '')), $name) === 0) return (bool) ($p['is_verified'] ?? false); }
        return false;
    };
    // 4s and 6s for the two at the crease, from the live innings card.
    $current = $cards ? end($cards) : null;
    $batLine = function (string $name) use ($current): array {
        foreach ((array) ($current['batters'] ?? []) as $b) {
            if (strcasecmp((string) ($b['name'] ?? ''), $name) === 0) return ['fours' => (int) ($b['fours'] ?? 0), 'sixes' => (int) ($b['sixes'] ?? 0)];
        }
        return ['fours' => 0, 'sixes' => 0];
    };

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

    $ballSvg = '<svg class="cm-dotball" viewBox="0 0 20 20" aria-label="dot"><circle cx="10" cy="10" r="10" fill="#94A3B8" fill-opacity=".55"/><g transform="rotate(-30 10 10)" fill="none" stroke="#fff" stroke-opacity=".9" stroke-width="1.6" stroke-linecap="round"><path d="M7.2 .8 Q12.8 10 7.2 19.2"/><path d="M10.4 .8 Q16 10 10.4 19.2"/></g></svg>';
@endphp

@if(!$isLive && $status !== '')
    <div class="cm-status"><span>{{ $status }}</span></div>
@endif

{{-- Over tracker — recentOvers already ends with the over in progress. --}}
@if(count($recentOvers) > 0 || count($thisOver) > 0)
    <div class="mdx-overchips">
        @if(count($recentOvers) > 0)
            @foreach($recentOvers as $ov)
                <div class="mdx-overchip {{ $isLive && $loop->last ? 'current' : '' }}">
                    <div class="ol"><em>OVER</em><b>{{ $ov['label'] ?? '' }}</b></div>
                    <div class="odiv"></div>
                    <div class="oballs">@foreach(($ov['balls']??[]) as $b)<span class="cm-ball {{ hrn_ball_kind($b) }}">{{ strtoupper($b)==='W'?'W':$b }}</span>@endforeach</div>
                    <div class="osum">= {{ (int)($ov['runs']??0) }}</div>
                </div>
            @endforeach
        @else
            @php $tor = 0; foreach($thisOver as $b){ $bi=(int)$b; $tor += is_numeric($b)?$bi:((stripos($b,'wd')===0||stripos($b,'nb')===0)?1:0); } @endphp
            <div class="mdx-overchip current">
                <div class="ol"><em>OVER</em><b>{{ (int) floor((float) ($d['overs'] ?? 0)) + 1 }}</b></div>
                <div class="odiv"></div>
                <div class="oballs">@foreach($thisOver as $b)<span class="cm-ball {{ hrn_ball_kind($b) }}">{{ strtoupper($b)==='W'?'W':$b }}</span>@endforeach</div>
                <div class="osum">= {{ $tor }}</div>
            </div>
        @endif
    </div>
@endif

{{-- Batter + partnership + bowler as one card — the mini scorecard. --}}
@if($striker !== '' || $bowler !== '')
<div class="cm-mini">
    <div class="cm-head">
        <span class="cm-h-name">BATTER</span>
        <span class="c38">R</span><span class="c34">B</span><span class="c30">4S</span><span class="c30">6S</span><i></i>
    </div>
    @foreach([[$striker, $strikerRB, true], [$nonStriker, $nonStrikerRB, false]] as [$nm, $rb, $onStrike])
        @if($nm !== '')
            @php $bl = $batLine($nm); $xp = $rb['runs'] + $bl['fours'] + $bl['sixes'] * 2; @endphp
            <div class="cm-prow">
                <img class="cm-face" src="{{ $faceOf($nm) }}" alt="" loading="lazy">
                <div class="cm-who">
                    <b>{{ $nm }}{{ $onStrike ? ' *' : '' }}</b>
                    <span><em>SR {{ hrn_sr($rb['runs'], $rb['balls']) }}</em><i class="cm-xp">+{{ $xp }} XP</i></span>
                </div>
                <span class="c38 cm-strong">{{ $rb['runs'] }}</span><span class="c34">{{ $rb['balls'] }}</span><span class="c30">{{ $bl['fours'] }}</span><span class="c30">{{ $bl['sixes'] }}</span><i></i>
            </div>
        @endif
    @endforeach
    @if($pShip !== '' || $lastWkt !== '')
        <div class="cm-pship"><span>{{ $pShip }}</span><span>{{ $lastWkt }}</span></div>
    @endif
    @if($bowler !== '')
        <div class="cm-head">
            <span class="cm-h-name">BOWLER</span>
            <span class="c48">W-R</span><span class="c36">OV</span><span class="c48">ECON</span><i></i>
        </div>
        <div class="cm-prow">
            <img class="cm-face" src="{{ $faceOf($bowler) }}" alt="" loading="lazy">
            <div class="cm-who">
                <b>{{ $bowler }}@if($verifiedOf($bowler))<svg class="cm-tick" viewBox="0 0 24 24" aria-label="Verified"><path fill="#2563EB" d="m23 12-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>@endif</b>
                <span><em>BOWLER</em><i class="cm-xp">+{{ $bwWkts * 20 + 5 }} XP</i></span>
            </div>
            <span class="c48 cm-strong">{{ $bwFig !== '' ? $bwFig : '0-0' }}</span><span class="c36">{{ $bwOv !== '' ? $bwOv : '0.0' }}</span><span class="c48">{{ $bwEcon }}</span><i></i>
        </div>
    @endif
</div>
@endif

{{-- Ball-by-ball feed: full-width rows; only the wicket and milestones become cards. --}}
@if(count($commentary) > 0)
<div class="cm-feed">
    <div class="cm-title">COMMENTARY</div>
    @foreach($commentary as $line)
        @php $kind = (string)($line['kind']??'ball'); @endphp
        @if($kind === 'header')
            <div class="cm-divider"><span>{{ strtoupper((string) ($line['text'] ?? '')) }}</span><i></i></div>
        @elseif($kind === 'milestone')
            @php
                $mk = (string) ($line['milestoneKind'] ?? 'fifty');
                $accent = ['century' => '#D97706', 'partnership' => '#15803D', 'target' => '#DC2626'][$mk] ?? '#2563EB';
                $eyebrow = ['partnership' => 'PARTNERSHIP', 'target' => 'MATCH WON'][$mk] ?? 'MILESTONE';
                $mLabel = (string) ($line['label'] ?? '');
            @endphp
            <div class="cm-mile" style="--acc: {{ $accent }}">
                <span class="cm-mile-ghost">{{ $mLabel }}</span>
                @if(in_array($mk, ['fifty', 'century'], true))
                    <img class="cm-mile-disc" src="{{ $faceOf((string) ($line['text'] ?? ''), $line['photo'] ?? null) }}" alt="" loading="lazy">
                @else
                    <span class="cm-mile-disc {{ strlen($mLabel) > 3 ? 'is-long' : '' }}">{{ $mLabel }}</span>
                @endif
                <div class="cm-mile-txt">
                    <em>{{ $eyebrow }}</em>
                    <b>{{ $line['text'] ?? '' }}</b>
                    @if(trim((string) ($line['detail'] ?? '')) !== '')<span>{{ $line['detail'] }}</span>@endif
                </div>
            </div>
        @elseif($kind === 'batter_in')
            @php
                $c = $line['career'] ?? null;
                $c = ($c && ((int)($c['innings']??0) > 0 || (int)($c['balls']??0) > 0)) ? $c : null;
                $nm = trim((string)($line['text']??'')) ?: 'New batter';
                $note = match (true) {
                    $c === null => 'first innings',
                    isset($c['sr']) && $c['sr'] !== null => (int) ($c['runs'] ?? 0) . ' runs · SR ' . number_format((float) $c['sr'], 0),
                    (int) ($c['highScore'] ?? 0) > 0 => (int) ($c['runs'] ?? 0) . ' runs · HS ' . (int) $c['highScore'],
                    default => (int) ($c['runs'] ?? 0) . ' runs',
                };
            @endphp
            <div class="cm-row">
                <span class="cm-ov">{{ $line['over'] ?? '' }}</span>
                <img class="cm-walk" src="{{ $faceOf($nm, $line['photo'] ?? null) }}" alt="" loading="lazy">
                <div class="cm-walk-txt"><b>{{ $nm }}</b><span>walks in · {{ $note }}</span></div>
            </div>
        @elseif(!empty($line['wicket']))
            @php
                $inn=(int)($line['innings']??1); $over=(string)($line['over']??'');
                $fw = $fowLookup($inn,$over);
                $txt = (string)($line['text']??'');
                $dismissal = str_contains($txt, 'OUT!') ? ltrim(trim(substr($txt, strpos($txt, 'OUT!') + 4)), ', ') : $txt;
                $dismissal = trim($dismissal) !== '' ? $dismissal : 'out';
                $bat = ($fw['batter'] ?? '') !== '' ? $fw['batter'] : (trim((string)($line['battingName']??'')) ?: 'Batter');
            @endphp
            <div class="cm-wkt">
                <span class="cm-wkt-face">
                    <img src="{{ $faceOf($bat, $line['photo'] ?? null) }}" alt="" loading="lazy">
                    <i>W</i>
                </span>
                <div class="cm-wkt-txt">
                    <em>WICKET</em>
                    <b>{{ $bat }}</b>
                    @if(!empty($fw['fig']))<strong>{{ $fw['fig'] }}</strong>@endif
                    <span>{{ $dismissal }}</span>
                </div>
                <div class="cm-wkt-side">
                    {{-- Bowled: leg and middle standing, off stump knocked back, bails flying. --}}
                    <svg viewBox="0 0 70 58" aria-hidden="true">
                        <circle cx="35" cy="30.2" r="27.8" fill="#E5484D" fill-opacity=".08"/>
                        <ellipse cx="35" cy="52.2" rx="20" ry="2" fill="#000" fill-opacity=".07"/>
                        <line x1="5.6" y1="52.2" x2="64.4" y2="52.2" stroke="#E5484D" stroke-opacity=".35" stroke-width="1.5" stroke-linecap="round"/>
                        <g fill="#E6C79A" stroke="#9A7240" stroke-width="1.2">
                            <rect x="22.9" y="16.2" width="5.25" height="36" rx="2.6"/>
                            <rect x="32.4" y="16.2" width="5.25" height="36" rx="2.6"/>
                            <rect x="42.9" y="16.2" width="5.25" height="36" rx="2.6" transform="rotate(24 45.5 52.2)"/>
                            <rect x="20.4" y="5.06" width="11.9" height="3.77" rx="1.9" transform="rotate(-32 26.4 6.94)"/>
                            <rect x="46" y="10.9" width="11.9" height="3.77" rx="1.9" transform="rotate(40 52 12.8)"/>
                        </g>
                        <g fill="#F6E3C4"><rect x="24.05" y="19.35" width="1.15" height="25.2" rx="1"/><rect x="33.55" y="19.35" width="1.15" height="25.2" rx="1"/></g>
                        <g stroke="#C62828" stroke-opacity=".3" stroke-width="1.5" stroke-linecap="round">
                            <line x1="40.3" y1="34.3" x2="56.4" y2="34.3"/><line x1="45.4" y1="37.1" x2="56.4" y2="37.1"/><line x1="40.3" y1="40" x2="56.4" y2="40"/>
                        </g>
                        <circle cx="58.6" cy="37.1" r="6.4" fill="#C62828" stroke="#8E1B1B" stroke-width="1.2"/>
                        <line x1="58.6" y1="31.7" x2="58.6" y2="42.5" stroke="#fff" stroke-opacity=".9" stroke-width="1.4" stroke-linecap="round" transform="rotate(-35 58.6 37.1)"/>
                        <circle cx="56.2" cy="34.7" r="1.8" fill="#fff" fill-opacity=".35"/>
                    </svg>
                    @if(!empty($fw['score']))<b>{{ $fw['score'] }}</b>@endif
                    @if($over!=='')<span>{{ $over }} ov</span>@endif
                </div>
            </div>
        @else
            @php
                $label = (string)($line['label']??'');
                $lk = strtolower($label);
                $chip = match (true) {
                    !empty($line['boundary']) => 'is-bound',
                    $label === '0' || $label === '' => 'is-dot',
                    in_array($lk, ['wd','nb','b','lb'], true) => 'is-extra',
                    default => 'is-run',
                };
            @endphp
            <div class="cm-row {{ !empty($line['boundary'])?'is-boundary':'' }}">
                <span class="cm-ov">{{ $line['over'] ?? '' }}</span>
                <span class="cm-chip {{ $chip }}">@if($chip === 'is-dot'){!! $ballSvg !!}@else{{ $label }}@endif</span>
                <span class="cm-text">{{ $line['text'] ?? '' }}</span>
            </div>
        @endif
    @endforeach
</div>
@endif

@if(count($commentary) === 0 && $striker === '' && count($thisOver) === 0 && count($recentOvers) === 0)
    <div class="mdx-card"><div class="mdx-empty">No commentary yet — it'll appear here once scoring begins.</div></div>
@endif

<style>
/* Commentary tab, as CommentaryTab.kt draws it. */
.cm-status { text-align: center; margin-top: -4px; }
.cm-status span { display: inline-block; background: rgba(245,158,11,.12); color: #B45309; font-size: 11px; font-weight: 600; padding: 5px 14px; border-radius: 999px; }
.cm-ball { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 500; color: var(--ink2); background: var(--bg); border: 1px solid var(--border); flex: 0 0 auto; }
.cm-ball.six { color: #16A34A; background: rgba(22,163,74,.15); border: 1.5px solid #16A34A; font-weight: 700; }
.cm-ball.four { color: #2563EB; background: rgba(37,99,235,.15); border: 1.5px solid #2563EB; font-weight: 700; }
.cm-ball.wicket { color: #fff; background: var(--red); border-color: var(--red); font-weight: 700; }

.cm-mini { margin: 2px -4px 0; background: #fff; border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
.cm-head, .cm-prow { display: flex; align-items: center; padding: 6px 16px; border-bottom: 1px solid var(--border); }
.cm-head span { color: var(--muted); font-size: 10px; font-weight: 500; text-align: center; }
.cm-head .cm-h-name { flex: 1; text-align: left; letter-spacing: 1px; }
.cm-mini i:empty { width: 8px; flex: 0 0 auto; }
.c30 { width: 30px; } .c34 { width: 34px; } .c36 { width: 36px; } .c38 { width: 38px; } .c48 { width: 48px; }
.cm-prow { padding: 16px; gap: 12px; }
.cm-prow > span { font-size: 14px; color: var(--ink2); text-align: center; font-variant-numeric: tabular-nums; }
.cm-prow > span.c48, .cm-prow > span.c36 { font-size: 13px; }
.cm-prow > .cm-strong { color: var(--ink); font-weight: 600; }
.cm-face { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; background: var(--bg); flex: 0 0 auto; }
.cm-who { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.cm-who b { font-size: 14px; font-weight: 500; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }
.cm-who > span { display: flex; align-items: center; gap: 6px; }
.cm-who em { white-space: nowrap; font-style: normal; font-size: 10px; letter-spacing: 1px; color: var(--ink2); }
.cm-xp { font-style: normal; font-size: 9px; font-weight: 700; color: #2563EB; background: rgba(37,99,235,.12); padding: 1px 6px; border-radius: 6px; white-space: nowrap; }
.cm-tick { width: 14px; height: 14px; flex: 0 0 auto; }
.cm-pship { display: flex; justify-content: space-between; gap: 10px; background: var(--bg); padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 10px; font-weight: 500; color: var(--ink2); }
.cm-mini > :last-child { border-bottom: 0; }

/* The feed runs edge to edge: white rows on hairlines, no gaps between them. */
.cm-feed { margin: 0 -16px; }
.cm-title { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: 1px; padding: 2px 16px 4px; }
.cm-divider { display: flex; align-items: center; gap: 10px; padding: 14px 16px 8px; }
.cm-divider span { color: var(--ink2); font-size: 10px; font-weight: 800; letter-spacing: 1.2px; }
.cm-divider i { flex: 1; height: 1px; background: var(--border); }
.cm-row { display: flex; align-items: center; background: #fff; border-bottom: 1px solid var(--border); padding: 14px 16px; }
.cm-ov { width: 38px; flex: 0 0 auto; color: var(--muted); font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; }
.cm-chip { width: 34px; height: 34px; border-radius: 50%; flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; margin-right: 14px; background: var(--bg); color: var(--ink2); }
.cm-chip.is-bound { background: rgba(245,158,11,.15); color: #F59E0B; }
.cm-chip.is-extra { background: rgba(180,83,9,.12); color: #B45309; }
.cm-dotball { width: 17px; height: 17px; }
.cm-text { flex: 1; font-size: 15px; line-height: 21px; color: var(--ink); }
.cm-row.is-boundary .cm-text { font-weight: 700; }
.cm-walk { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 1.5px solid rgba(37,99,235,.55); background: var(--bg); margin-right: 12px; flex: 0 0 auto; }
.cm-walk-txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.cm-walk-txt b { font-size: 15px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cm-walk-txt span { font-size: 13px; color: var(--ink2); }

/* A wicket: one red edge on a white card. */
.cm-wkt { position: relative; display: flex; align-items: center; margin: 8px 12px; padding: 14px 12px 14px 16px; background: #fff; border: 1px solid rgba(229,72,77,.22); border-radius: 18px; overflow: hidden;
    box-shadow: 0 10px 22px -16px rgba(15,23,42,.35), 0 1px 2px rgba(15,23,42,.06); }
.cm-wkt::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #E5484D; }
.cm-wkt-face { position: relative; width: 62px; height: 62px; flex: 0 0 auto; margin-right: 14px; }
.cm-wkt-face img { width: 62px; height: 62px; border-radius: 50%; object-fit: cover; border: 2.5px solid #E5484D; background: #FDECEC; display: block; }
.cm-wkt-face i { position: absolute; right: -4px; bottom: -3px; width: 24px; height: 24px; border-radius: 50%; background: #fff; display: inline-flex; align-items: center; justify-content: center; font-style: normal; font-size: 10px; font-weight: 700; color: #fff; }
.cm-wkt-face i::before { content: ""; position: absolute; inset: 2.5px; border-radius: 50%; background: #E5484D; }
.cm-wkt-face i { isolation: isolate; } .cm-wkt-face i::before { z-index: -1; }
.cm-wkt-txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.cm-wkt-txt em { font-style: normal; color: #E5484D; font-size: 11px; font-weight: 800; letter-spacing: 1.5px; margin-bottom: 2px; }
.cm-wkt-txt b { font-family: 'Archivo Black', sans-serif; font-weight: 400; font-size: 18px; line-height: 22px; color: var(--ink); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.cm-wkt-txt strong { font-family: 'Archivo Black', sans-serif; font-weight: 400; font-size: 15px; color: var(--ink2); font-variant-numeric: tabular-nums; }
.cm-wkt-txt span { margin-top: 4px; font-size: 13.5px; line-height: 18px; color: var(--ink2); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
.cm-wkt-side { display: flex; flex-direction: column; align-items: center; margin-left: 10px; flex: 0 0 auto; }
.cm-wkt-side svg { width: 70px; height: 58px; }
.cm-wkt-side b { font-family: 'Archivo Black', sans-serif; font-weight: 400; font-size: 20px; color: var(--ink); font-variant-numeric: tabular-nums; }
.cm-wkt-side span { font-size: 12px; font-weight: 600; color: var(--muted); }

/* A milestone: the event's own colour, the number set enormous behind the words. */
.cm-mile { position: relative; display: flex; align-items: center; gap: 16px; margin: 7px 12px; padding: 18px 16px; border-radius: 16px; overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--acc) 35%, transparent);
    background: linear-gradient(90deg, color-mix(in srgb, var(--acc) 16%, #fff), color-mix(in srgb, var(--acc) 5%, #fff)); }
.cm-mile-ghost { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); font-size: 112px; font-weight: 900; line-height: 1; color: var(--acc); opacity: .13; font-variant-numeric: tabular-nums; pointer-events: none; }
.cm-mile-disc { position: relative; width: 84px; height: 84px; border-radius: 50%; flex: 0 0 auto; object-fit: cover; display: inline-flex; align-items: center; justify-content: center;
    background: color-mix(in srgb, var(--acc) 16%, #fff); border: 2.5px solid color-mix(in srgb, var(--acc) 60%, transparent); color: var(--acc); font-size: 26px; font-weight: 900; }
.cm-mile-disc.is-long { font-size: 18px; }
.cm-mile-txt { position: relative; flex: 1; min-width: 0; display: flex; flex-direction: column; }
.cm-mile-txt em { font-style: normal; color: var(--acc); font-size: 10px; font-weight: 800; letter-spacing: 1.5px; }
.cm-mile-txt b { margin-top: 6px; font-size: 18px; font-weight: 900; line-height: 22px; color: var(--ink); }
.cm-mile-txt span { margin-top: 7px; font-size: 12.5px; font-weight: 600; color: var(--ink2); }
</style>
@endsection
