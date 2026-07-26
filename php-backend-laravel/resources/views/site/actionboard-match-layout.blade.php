@extends('site.layout')
@section('body_class', 'match-page-body')
@section('content')
@include('site.partials.match-helpers')
@php
    $d = $detail;
    $isLive = (bool) ($d['isLive'] ?? false);
    $battingTeam = (int) ($d['battingTeam'] ?? 1);

    $team1 = (string) ($d['team1'] ?? '');
    $team2 = (string) ($d['team2'] ?? '');
    $code1 = hrn_team_code($team1);
    $code2 = hrn_team_code($team2);
    $col1 = hrn_mono_color(trim((string) ($d['team1Full'] ?? '')) ?: $team1);
    $col2 = hrn_mono_color(trim((string) ($d['team2Full'] ?? '')) ?: $team2);

    $scoreBatting = hrn_sanitize_score((string) ($d['score'] ?? '0'));
    $scoreOpp     = hrn_sanitize_score((string) ($d['opponentScore'] ?? '0'));
    $team1Score = $battingTeam === 2 ? $scoreOpp : $scoreBatting;
    $team2Score = $battingTeam === 2 ? $scoreBatting : $scoreOpp;
    $oversLabel = trim((string) ($d['overs'] ?? '')) !== '' ? $d['overs'] . ' ov' : '';

    $competition = trim((string) ($d['formatLabel'] ?? ''));
    $status = trim((string) ($d['status'] ?? ''));
    $metaLine = $competition !== '' ? $competition : ($isLive ? 'Live' : ($status !== '' ? $status : 'Match'));

    $crr = trim((string) ($d['crr'] ?? ''));
    $toss = trim((string) ($d['toss'] ?? ''));

    $lastBall = '';
    if (!empty($d['thisOver']) && is_array($d['thisOver'])) {
        $lastBall = (string) end($d['thisOver']);
    }
    $lastBallKind = $lastBall !== '' ? hrn_ball_kind($lastBall) : 'dot';

    $logo1 = (string) ($d['team1Logo'] ?? '');
    $logo2 = (string) ($d['team2Logo'] ?? '');

    $active = $activeTab ?? 'live';
    $tabs = [
        ['key' => 'info',       'label' => 'Info',       'route' => route('site.gamehub.actionboard.match.info', $id)],
        ['key' => 'commentary', 'label' => 'Commentary', 'route' => route('site.gamehub.actionboard.match.commentary', $id)],
        ['key' => 'live',       'label' => 'Live',       'route' => route('site.gamehub.actionboard.match', $id)],
        ['key' => 'scorecard',  'label' => 'Scorecard',  'route' => route('site.gamehub.actionboard.match.scorecard', $id)],
    ];
@endphp

<div class="mdx">
    {{-- ── Top app bar ── --}}
    <header class="mdx-bar">
        <a href="{{ route('site.gamehub.actionboard') }}" class="mdx-ic" aria-label="Back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <div class="mdx-bar-title">
            <span class="mdx-bar-teams">{{ $team1 }} <span class="mdx-vs-lite">vs</span> {{ $team2 }}</span>
            @if($isLive)
                <span class="mdx-live"><span class="mdx-live-dot"></span>LIVE</span>
            @endif
        </div>
        <button class="mdx-ic mdx-share" type="button" onclick="navigator.clipboard.writeText(window.location.href); this.classList.add('is-done'); setTimeout(()=>this.classList.remove('is-done'),1200);" aria-label="Share">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.6" y1="10.5" x2="15.4" y2="6.5"></line><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"></line></svg>
        </button>
    </header>
    <div class="mdx-hairline"></div>

    {{-- ── Live score hero ── --}}
    <div class="mdx-hero-wrap">
        <div class="mdx-hero-band">
            {{-- Scrolling wordmark that crawls around the whole card border (SVG text-on-path,
                 mirrors the app's ribbon). Sized to the band by JS so it never distorts. --}}
            <svg class="mdx-ribbon" aria-hidden="true" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <defs><path id="mdxRibPath" fill="none"></path></defs>
                <text><textPath href="#mdxRibPath" startOffset="0"></textPath></text>
            </svg>
            <div class="mdx-hero-card">
                <div class="mdx-hero-meta">{{ $metaLine }}</div>
                <div class="mdx-hero-row">
                    {{-- Team 1 --}}
                    <div class="mdx-team mdx-team-l">
                        <div class="mdx-crest" @if($logo1==='')style="background:{{ $col1 }};border-color:transparent;"@endif>
                            @if($logo1 !== '')<img src="{{ $logo1 }}" alt="{{ $code1 }}">@else<span>{{ $code1 }}</span>@endif
                        </div>
                        <div class="mdx-team-code">{{ $code1 }}</div>
                        @php $s1 = explode('/', $team1Score); @endphp
                        <div class="mdx-team-score mdx-score-home">
                            <span class="mdx-runs">{{ $s1[0] }}</span>@if(isset($s1[1]))<span class="mdx-wkts">/{{ $s1[1] }}</span>@endif
                        </div>
                        <div class="mdx-team-ov">{{ $battingTeam === 1 ? $oversLabel : '' }}</div>
                    </div>

                    {{-- Last ball + VS --}}
                    <div class="mdx-lastball">
                        <div class="mdx-lastball-lbl">LAST BALL</div>
                        <div class="mdx-lastball-num mdx-ball-{{ $lastBallKind }}">{{ $lastBall !== '' ? $lastBall : '•' }}</div>
                        <div class="mdx-vs">VS</div>
                    </div>

                    {{-- Team 2 --}}
                    <div class="mdx-team mdx-team-r">
                        <div class="mdx-crest" @if($logo2==='')style="background:{{ $col2 }};border-color:transparent;"@endif>
                            @if($logo2 !== '')<img src="{{ $logo2 }}" alt="{{ $code2 }}">@else<span>{{ $code2 }}</span>@endif
                        </div>
                        <div class="mdx-team-code">{{ $code2 }}</div>
                        @php $s2 = explode('/', $team2Score); @endphp
                        <div class="mdx-team-score mdx-score-away">
                            <span class="mdx-runs">{{ $s2[0] }}</span>@if(isset($s2[1]))<span class="mdx-wkts">/{{ $s2[1] }}</span>@endif
                        </div>
                        <div class="mdx-team-ov">{{ $battingTeam === 2 ? $oversLabel : '' }}</div>
                    </div>
                </div>

                @if($crr !== '' || $toss !== '')
                <div class="mdx-hero-stats">
                    <div class="mdx-hero-stats-l">
                        @if($crr !== '')<span class="mdx-hstat"><em>CRR</em> {{ $crr }}</span>@endif
                    </div>
                    @if($toss !== '')<span class="mdx-hstat"><em>TOSS</em> {{ $toss }}</span>@endif
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Tab bar ── --}}
    <nav class="mdx-tabs" role="tablist">
        @foreach($tabs as $t)
            <a href="{{ $t['route'] }}" class="mdx-tab {{ $active === $t['key'] ? 'is-on' : '' }}" role="tab">
                @if($t['key'] === 'live' && $isLive)<span class="mdx-tab-dot"></span>@endif
                {{ $t['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="mdx-content">
        @yield('match_content')
    </div>
</div>

<style>
/* Hide the site chrome — this page owns the full screen like the app screen does. */
header.topbar { display: none !important; }
main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
.match-page-body .mfoot, .match-page-body footer { display: none !important; }

.mdx {
    --bg: #F4F7FB; --surface: #FFFFFF; --border: #E2E8F0;
    --green: #16A34A; --yellow: #F59E0B; --red: #EF4444; --blue: #2563EB;
    --ink: #0F172A; --ink2: #475569; --muted: #94A3B8;
    background: var(--bg);
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    padding-bottom: 40px;
    -webkit-tap-highlight-color: transparent;
}
.mdx * { box-sizing: border-box; }

/* ── App bar ── */
.mdx-bar {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; background: var(--bg);
    position: sticky; top: 0; z-index: 30;
}
.mdx-ic {
    width: 38px; height: 38px; flex: 0 0 auto;
    border-radius: 12px; background: #F1F5FA; border: 1px solid #E4EAF1;
    display: inline-flex; align-items: center; justify-content: center;
    color: #334155; cursor: pointer; text-decoration: none; transition: background .15s;
}
.mdx-ic:hover { background: #E9EFF6; }
.mdx-ic svg { width: 19px; height: 19px; }
.mdx-share.is-done { background: #DCFCE7; color: #16A34A; border-color: #BBF7D0; }
.mdx-bar-title { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
.mdx-bar-teams { font-size: 16px; font-weight: 800; letter-spacing: -.2px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mdx-vs-lite { color: var(--muted); font-weight: 600; }
.mdx-live { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 5px; background: #FCE6E6; color: var(--red); font-size: 10px; font-weight: 800; letter-spacing: .5px; padding: 4px 7px; border-radius: 7px; }
.mdx-live-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--red); animation: mdxPulse .85s ease-in-out infinite alternate; }
@keyframes mdxPulse { from { opacity: 1; } to { opacity: .35; } }
.mdx-hairline { height: 1px; background: #EAEEF3; }

/* ── Hero ── */
.mdx-hero-wrap { padding: 8px 14px; background: var(--bg); }
.mdx-hero-band { position: relative; background: #fff; border: 1px solid var(--border); border-radius: 26px; padding: 15px; overflow: hidden; }
.mdx-ribbon { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
.mdx-ribbon text { fill: rgba(71,85,105,.72); font-family: 'Inter', sans-serif; font-size: 11px; font-weight: 800; letter-spacing: 1px; }
.mdx-hero-card {
    position: relative;
    border-radius: 12px;
    background: linear-gradient(180deg, #D3EAF8 0%, #AFD2EC 100%);
    padding: 14px 18px;
}
.mdx-hero-meta { text-align: center; color: rgba(51,65,85,.78); font-size: 11px; font-weight: 600; }
.mdx-hero-row { display: flex; align-items: flex-start; justify-content: space-between; margin-top: 10px; gap: 6px; }
.mdx-team { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.mdx-team-r { align-items: flex-end; text-align: right; }
.mdx-crest { width: 42px; height: 42px; border-radius: 50%; background: #fff; border: 1.5px solid #CBD5E1; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.mdx-crest img { width: 100%; height: 100%; object-fit: cover; }
.mdx-crest span { color: #fff; font-size: 13px; font-weight: 800; }
.mdx-team-code { margin-top: 8px; font-size: 14px; font-weight: 600; color: #1E293B; }
.mdx-team-score { margin-top: 2px; display: flex; align-items: flex-end; line-height: 1; }
.mdx-team-r .mdx-team-score { justify-content: flex-end; }
.mdx-runs { font-size: 34px; font-weight: 800; letter-spacing: -1px; }
.mdx-wkts { font-size: 18px; font-weight: 800; padding-bottom: 3px; }
.mdx-score-home .mdx-runs { color: #0D47A1; } .mdx-score-home .mdx-wkts { color: rgba(13,71,161,.42); }
.mdx-score-away .mdx-runs { color: #475569; } .mdx-score-away .mdx-wkts { color: #94A3B8; }
.mdx-team-ov { margin-top: 2px; font-size: 11px; color: #64748B; min-height: 14px; }
.mdx-lastball { display: flex; flex-direction: column; align-items: center; padding: 0 6px; flex: 0 0 auto; }
.mdx-lastball-lbl { color: rgba(51,65,85,.5); font-size: 9px; font-weight: 700; letter-spacing: 1.5px; }
.mdx-lastball-num { font-size: 40px; font-weight: 900; line-height: 1; margin: 2px 0 6px; }
.mdx-ball-six { color: var(--green); } .mdx-ball-four { color: var(--blue); } .mdx-ball-wicket { color: var(--red); }
.mdx-ball-run { color: #0F172A; } .mdx-ball-dot, .mdx-ball-extra { color: rgba(15,23,42,.28); }
.mdx-vs { width: 30px; height: 30px; border-radius: 50%; background: #1E293B; color: #fff; font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
.mdx-hero-stats { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; gap: 12px; flex-wrap: wrap; }
.mdx-hero-stats-l { display: flex; gap: 16px; }
.mdx-hstat { font-size: 10px; color: #0F172A; font-weight: 700; }
.mdx-hstat em { color: #64748B; font-weight: 500; font-style: normal; }

/* ── Tabs ── */
.mdx-tabs { display: flex; background: var(--bg); position: sticky; top: 58px; z-index: 20; border-bottom: 1px solid var(--border); }
.mdx-tab { flex: 1; text-align: center; padding: 12px 4px; font-size: 12px; font-weight: 600; color: var(--ink2); text-decoration: none; position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
.mdx-tab.is-on { color: var(--ink); }
.mdx-tab.is-on::after { content: ""; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%); width: 22px; height: 2px; border-radius: 2px; background: var(--red); }
.mdx-tab-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--red); animation: mdxPulse .9s ease-in-out infinite alternate; }

/* ── Content shell ── */
.mdx-content { padding: 14px 16px; display: flex; flex-direction: column; gap: 14px; max-width: 720px; margin: 0 auto; }
.mdx-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 18px; }
.mdx-card--flush { padding: 0; overflow: hidden; }
.mdx-eyebrow { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.mdx-divider { height: 1px; background: var(--border); margin: 14px 0; }

/* ── Generic rows ── */
.mdx-row { display: flex; align-items: center; justify-content: space-between; }
.mdx-muted { color: var(--muted); }
.mdx-sec-title { color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: 1px; }

/* ── Ball chips ── */
.mdx-chip { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex: 0 0 auto; }
.mdx-chip.six { background: var(--green); color: #fff; }
.mdx-chip.four { background: var(--blue); color: #fff; }
.mdx-chip.wicket { background: var(--red); color: #fff; }
.mdx-chip.dot, .mdx-chip.run { background: #E2E8F0; color: var(--muted); }
.mdx-chip.extra { background: #FEF3C7; color: #92400E; }
.mdx-chip.sm { width: 22px; height: 22px; font-size: 10px; }
.mdx-chip.lg { width: 28px; height: 28px; }

/* ── Live tab ── */
.mdx-bat-head, .mdx-bat-line { display: flex; align-items: center; }
.mdx-bat-head .h-name, .mdx-bat-line .b-name { flex: 1; min-width: 0; }
.mdx-col-rb { width: 64px; } .mdx-col-sr { width: 44px; text-align: right; }
.mdx-bat-head span { color: var(--muted); font-size: 10px; font-weight: 700; }
.mdx-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--bg); border: 1px solid var(--border); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--ink2); flex: 0 0 auto; }
.mdx-bat-line .b-name { display: flex; align-items: center; gap: 10px; }
.mdx-bat-name { font-size: 14px; font-weight: 600; color: var(--ink); }
.mdx-bat-name.striker { color: var(--green); font-weight: 700; }
.mdx-bat-rb { width: 64px; font-size: 13px; font-weight: 600; }
.mdx-bat-sr { width: 44px; text-align: right; font-size: 13px; color: var(--ink2); }
.mdx-overbox-row { display: flex; gap: 10px; }
.mdx-overbox { flex: 1; border: 1px solid var(--border); border-radius: 12px; background: var(--bg); padding: 12px 0; text-align: center; }
.mdx-overbox b { font-size: 18px; font-weight: 800; display: block; }
.mdx-overbox i { display: block; width: 20px; height: 2px; background: var(--red); border-radius: 1px; margin: 3px auto; }
.mdx-overbox span { font-size: 10px; color: var(--muted); }
.mdx-chase-stats { display: flex; justify-content: space-between; margin-top: 12px; }
.mdx-chase-stat { text-align: center; }
.mdx-chase-stat b { font-size: 16px; font-weight: 900; display: block; }
.mdx-chase-stat span { font-size: 9px; color: var(--muted); font-weight: 700; letter-spacing: .5px; }
.mdx-winbar { height: 8px; border-radius: 4px; background: #CBD5E1; overflow: hidden; margin-top: 12px; }
.mdx-winbar i { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, var(--blue), #1D4ED8); }

/* ── Info tab ── */
.mdx-team-line { display: flex; align-items: center; gap: 10px; }
.mdx-logo-sm { width: 34px; height: 34px; border-radius: 50%; background: #fff; border: 1px solid #CBD5E1; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; font-size: 11px; font-weight: 800; color: var(--blue); flex: 0 0 auto; }
.mdx-logo-sm img { width: 100%; height: 100%; object-fit: cover; }
.mdx-fact { display: flex; align-items: flex-start; }
.mdx-fact .k { width: 88px; color: var(--muted); font-size: 13px; flex: 0 0 auto; }
.mdx-fact .v { flex: 1; font-size: 13px; font-weight: 600; }
.mdx-squad-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mdx-squad-cell { display: flex; align-items: center; gap: 8px; }
.mdx-guest { background: var(--border); color: var(--ink2); font-size: 8px; font-weight: 700; padding: 1px 5px; border-radius: 5px; }

/* ── Scorecard tab ── */
.mdx-inn-head { display: flex; align-items: center; gap: 10px; background: rgba(37,99,235,.06); padding: 12px 16px; cursor: pointer; }
.mdx-inn-body { padding: 16px; display: flex; flex-direction: column; gap: 18px; }
.mdx-chevron { transition: transform .2s; color: var(--muted); }
.mdx-inn.collapsed .mdx-inn-body { display: none; }
.mdx-inn.collapsed .mdx-chevron { transform: rotate(0); }
.mdx-inn .mdx-chevron { transform: rotate(180deg); }
.mdx-table-head { display: flex; background: var(--bg); border-radius: 8px; padding: 7px 10px; }
.mdx-table-head .th-name { flex: 1; }
.mdx-table-head span { color: var(--muted); font-size: 10px; font-weight: 700; text-align: right; }
.mdx-tc { width: 30px; text-align: right; } .mdx-tc-sr { width: 48px; text-align: right; } .mdx-tc-er { width: 46px; text-align: right; }
.mdx-sc-row { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid rgba(226,232,240,.5); }
.mdx-sc-row:last-child { border-bottom: 0; }
.mdx-sc-name { flex: 1; min-width: 0; }
.mdx-sc-name b { font-size: 14px; font-weight: 600; display: block; }
.mdx-sc-name span { font-size: 11px; color: var(--muted); }
.mdx-sc-name span.notout { color: var(--green); font-weight: 600; }
.mdx-sc-row .num { font-size: 13px; color: var(--ink2); }
.mdx-sc-row .num.runs { color: var(--ink); font-weight: 800; }
.mdx-sc-extras, .mdx-sc-total { display: flex; justify-content: space-between; align-items: center; padding: 10px; }
.mdx-sc-total { border-top: 1px solid var(--border); }
.mdx-fow { display: flex; flex-wrap: wrap; gap: 8px; }
.mdx-fow-chip { background: rgba(239,68,68,.06); border: 1px solid rgba(239,68,68,.25); border-radius: 10px; padding: 6px 10px; text-align: center; }
.mdx-fow-chip b { color: var(--red); font-size: 13px; font-weight: 800; display: block; }
.mdx-fow-chip span { color: var(--muted); font-size: 10px; }

/* ── Commentary tab ── */
.mdx-overchips { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; }
.mdx-overchip { display: flex; align-items: center; gap: 9px; border: 1px solid var(--border); background: #fff; border-radius: 12px; padding: 7px 10px; flex: 0 0 auto; }
.mdx-overchip.current { background: rgba(37,99,235,.06); border-color: rgba(37,99,235,.4); }
.mdx-overchip .ol { text-align: center; min-width: 26px; }
.mdx-overchip .ol em { display: block; color: var(--muted); font-size: 7px; font-weight: 700; font-style: normal; letter-spacing: .5px; }
.mdx-overchip .ol b { font-size: 13px; font-weight: 900; }
.mdx-overchip .odiv { width: 1px; align-self: stretch; background: var(--border); }
.mdx-overchip .oballs { display: flex; gap: 5px; }
.mdx-overchip .osum { font-size: 13px; font-weight: 900; }
.mdx-comm-row { display: flex; align-items: center; gap: 12px; background: #fff; border-bottom: 1px solid var(--border); padding: 12px 16px; }
.mdx-comm-row .cov { width: 34px; color: var(--muted); font-size: 11px; font-weight: 700; flex: 0 0 auto; }
.mdx-comm-row .ctext { flex: 1; font-size: 13px; color: var(--ink); }
.mdx-comm-row.wkt .ctext { color: var(--red); font-weight: 700; }
.mdx-comm-row.boundary .ctext { font-weight: 700; }
.mdx-comm-head { background: rgba(37,99,235,.08); color: var(--blue); font-size: 12px; font-weight: 700; padding: 8px 16px; border-radius: 10px; }
.mdx-banner { border-radius: 14px; padding: 14px; color: #fff; }
.mdx-banner.wkt { background: linear-gradient(90deg, #E23B3B, #B91C1C); display: flex; align-items: center; gap: 12px; }
.mdx-banner.newbat { background: linear-gradient(90deg, #2563EB, #1D4ED8); }
.mdx-banner-av { width: 42px; height: 42px; border-radius: 50%; background: rgba(255,255,255,.18); display: inline-flex; align-items: center; justify-content: center; font-size: 17px; font-weight: 900; flex: 0 0 auto; }
.mdx-banner .bn-tag { font-size: 10px; font-weight: 900; letter-spacing: 1px; opacity: .9; }
.mdx-banner .bn-name { font-size: 15px; font-weight: 900; }
.mdx-banner .bn-sub { font-size: 12px; opacity: .85; }
.mdx-career { display: flex; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.15); }
.mdx-career > div { text-align: center; }
.mdx-career b { display: block; font-size: 15px; font-weight: 900; }
.mdx-career span { font-size: 9px; opacity: .7; font-weight: 600; }

.mdx-empty { text-align: center; color: var(--muted); font-size: 13px; padding: 28px 12px; }

@media (min-width: 721px) {
    .mdx-bar, .mdx-hero-wrap, .mdx-tabs { max-width: 720px; margin-left: auto; margin-right: auto; }
    .mdx-tabs { top: 58px; }
}
</style>

<script>
    // Scrolling border ribbon — fit an SVG text path to the card border and crawl it.
    (function () {
        var band = document.querySelector('.mdx-hero-band');
        var svg = document.querySelector('.mdx-ribbon');
        if (!band || !svg) return;
        var path = svg.querySelector('path');
        var tp = svg.querySelector('textPath');
        var seg = 'HARAAN  ·  LIVE  ·  ';
        var segLen = 140;

        function layout() {
            var w = Math.round(band.clientWidth), h = Math.round(band.clientHeight);
            if (!w || !h) return;
            svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
            var i = 8, r = 18, x0 = i, y0 = i, x1 = w - i, y1 = h - i;
            path.setAttribute('d',
                'M ' + (x0 + r) + ' ' + y0 +
                ' H ' + (x1 - r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + x1 + ' ' + (y0 + r) +
                ' V ' + (y1 - r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + (x1 - r) + ' ' + y1 +
                ' H ' + (x0 + r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + x0 + ' ' + (y1 - r) +
                ' V ' + (y0 + r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + (x0 + r) + ' ' + y0 + ' Z');
            tp.textContent = seg;
            segLen = tp.getComputedTextLength() || 140;
            var per = path.getTotalLength() || (w * 2 + h * 2);
            var reps = Math.ceil((per + segLen) / segLen) + 2;
            tp.textContent = new Array(reps + 1).join(seg);
        }

        layout();
        var off = 0;
        function tick() {
            off -= 0.35;
            if (off <= -segLen) off += segLen;
            tp.setAttribute('startOffset', off);
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
        var rt;
        window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(layout, 150); });
    })();
</script>
@if($isLive)
<script>
    // Mirror the app's live auto-refresh — a live match ticks without a manual reload.
    setTimeout(function(){ location.reload(); }, 30000);
</script>
@endif
@endsection
