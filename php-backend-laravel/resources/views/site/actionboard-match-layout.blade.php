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
    $ballInPlay = $isLive && (bool) ($d['ballInPlay'] ?? false);
    // Live commentary: the speaker shows only while someone is on air (see partials/match-commentary).
    $commentary = is_array($d['liveCommentary'] ?? null) ? $d['liveCommentary'] : ['onAir' => false, 'by' => null];
    $commentaryOnAir = $isLive && (bool) ($commentary['onAir'] ?? false);
    // What the pulse poll compares against: the page reloads only when this moves.
    $pulseV = '';
    if ($isLive && ($pulseMatch = \App\Models\LiveMatch::find($id)) !== null) {
        $pulseV = \App\Http\Controllers\Api\LiveMatchController::scoreVersion($pulseMatch);
    }

    $logo1 = hrn_team_icon($d['team1Logo'] ?? '', $d['team1Emblem'] ?? '');
    $logo2 = hrn_team_icon($d['team2Logo'] ?? '', $d['team2Emblem'] ?? '');

    // The scorebug the app draws (CrexUI LiveScoreCard): the batting side owns the card,
    // the fielding side sits on one quiet line under it. Innings replayed from the ball log
    // decide who is in — the flat fields only guess from the last over's tag.
    $cards = is_array($d['inningsCards'] ?? null) ? array_values($d['inningsCards']) : [];
    $current = $cards ? end($cards) : null;
    $battingIsTeam2 = (int) ($current['battingTeam'] ?? $battingTeam) === 2;
    $firstInnings = null;
    if ($current) {
        foreach ($cards as $c) {
            if ($c !== $current && (int) ($c['battingTeam'] ?? 0) !== (int) ($current['battingTeam'] ?? 0)) { $firstInnings = $c; break; }
        }
    }
    $batScore = $current ? ($current['runs'] . '/' . $current['wickets']) : $scoreBatting;
    $batOvers = $current && trim((string) ($current['overs'] ?? '')) !== '' ? (string) $current['overs'] : trim((string) ($d['overs'] ?? ''));
    [$batRuns, $batWkts] = array_pad(explode('/', $batScore, 2), 2, null);
    if ($firstInnings) {
        $fieldScore = $firstInnings['runs'] . '/' . $firstInnings['wickets'] . ' (' . $firstInnings['overs'] . ')';
    } else {
        $fieldScore = trim($scoreOpp);
        // One innings so far: the other side hasn't batted, whatever "0" the flat field carries.
        if (($current || (int) ($d['innings'] ?? 1) <= 1) && in_array($fieldScore, ['', '0', '0/0'], true)) $fieldScore = '';
    }
    $chase = null;
    if ($firstInnings && $current && $isLive) {
        $maxOvers = preg_match('/(\d+)/', $competition, $mo) && (int) $mo[1] > 0 ? (int) $mo[1] : 20;
        $ov = explode('.', (string) $current['overs']);
        $bowled = ((int) ($ov[0] ?? 0)) * 6 + ((int) ($ov[1] ?? 0));
        $need = max(0, (int) $firstInnings['runs'] + 1 - (int) $current['runs']);
        if ($need > 0) $chase = $need . ' off ' . max(0, $maxOvers * 6 - $bowled);
    }
    $batName = $battingIsTeam2 ? $team2 : $team1;
    $fieldName = $battingIsTeam2 ? $team1 : $team2;
    $batCode = $battingIsTeam2 ? $code2 : $code1;
    $fieldCode = $battingIsTeam2 ? $code1 : $code2;
    $batLogo = $battingIsTeam2 ? $logo2 : $logo1;
    $fieldLogo = $battingIsTeam2 ? $logo1 : $logo2;
    $batCol = $battingIsTeam2 ? $col2 : $col1;
    $fieldCol = $battingIsTeam2 ? $col1 : $col2;

    // The over so far — one disc per delivery, an empty ring for each ball still to come.
    $overBalls = array_slice(array_map('strval', is_array($d['thisOver'] ?? null) ? $d['thisOver'] : []), -7);
    $overSlots = max(6, count($overBalls));

    // What the umpire signals for the last ball (UmpireCall.of) and the caption under him.
    $lbU = strtoupper(trim($lastBall));
    $umpCall = match (true) {
        str_contains($lbU, 'WD') => 'WIDE',
        str_contains($lbU, 'NB') => 'NO BALL',
        str_contains($lbU, 'LB') => 'LEG BYE',
        str_starts_with($lbU, 'W') => 'OUT',
        $lbU !== '' && str_ends_with($lbU, 'B') => 'BYE',
        $lbU === '4' => 'FOUR',
        $lbU === '6' => 'SIX',
        default => '',
    };
    $umpCaption = match (true) {
        empty($d['thisOver']) => 'LAST BALL',
        $umpCall !== '' => $umpCall,
        in_array($lbU, ['•', '0'], true) => 'DOT BALL',
        $lbU === '1' => '1 RUN',
        default => $lbU . ' RUNS',
    };
    // The word crawling round the band: the last ball's moment, else a calm "HARAAN LIVE".
    $ribbonWord = match ($lbU) { '6' => 'six', '4' => 'four', 'W' => 'wicket', default => 'calm' };

    $active = $activeTab ?? 'commentary';
    $tabs = [
        ['key' => 'info',       'label' => 'Info',       'route' => route('site.gamehub.actionboard.match.info', $id)],
        ['key' => 'commentary', 'label' => 'Commentary', 'route' => route('site.gamehub.actionboard.match', $id)],
        ['key' => 'live',       'label' => 'Live',       'route' => route('site.gamehub.actionboard.match.live', $id)],
        ['key' => 'scorecard',  'label' => 'Scorecard',  'route' => route('site.gamehub.actionboard.match.scorecard', $id)],
        ['key' => 'mvp',        'label' => 'MVP',        'route' => route('site.gamehub.actionboard.match.mvp', $id)],
        ['key' => 'insights',   'label' => 'Insights',   'route' => route('site.gamehub.actionboard.match.insights', $id)],
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
        @if($commentaryOnAir)
            <button class="mdx-ic mdx-speaker" type="button" data-commentary-speaker data-match="{{ $id }}" aria-pressed="false" aria-label="Listen to live commentary" title="{{ ($commentary['by'] ?? '') !== '' ? $commentary['by'] . ' is calling it live' : 'Live commentary' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z" fill="currentColor" stroke-linejoin="round"/>
                    <path class="spk-w1" d="M15.5 9.2a4 4 0 0 1 0 5.6"/>
                    <path class="spk-w2" d="M18.3 6.6a7.6 7.6 0 0 1 0 10.8"/>
                </svg>
                <span class="spk-dot" aria-hidden="true"></span>
                <span class="spk-spin" aria-hidden="true"></span>
            </button>
        @endif
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
                <text dominant-baseline="central"><textPath href="#mdxRibPath" startOffset="0" dominant-baseline="central"></textPath></text>
            </svg>
            <div class="mdx-hero-card mdx-flash-{{ $isLive ? $ribbonWord : 'calm' }}">
                {{-- Format on the left, toss on the right — the small print above a TV score. --}}
                <div class="mdx-hero-top">
                    <span class="mdx-hero-fmt">{{ $metaLine }}</span>
                    @if($toss !== '')<span class="mdx-hero-toss">Toss: {{ $toss }}</span>@endif
                </div>

                <div class="mdx-hero-main">
                    {{-- The batting side owns the card. --}}
                    <div class="mdx-bat">
                        <div class="mdx-bat-who">
                            <span class="mdx-crest" @if($batLogo==='')style="background:{{ $batCol }};border-color:transparent;"@endif>
                                @if($batLogo !== '')<img src="{{ $batLogo }}" alt="">@else<span>{{ $batCode }}</span>@endif
                            </span>
                            <b>{{ $batCode }}</b>
                            @if($isLive)<span class="mdx-bat-tag">·&nbsp; batting</span>@endif
                        </div>
                        <div class="mdx-bat-score">
                            <span class="mdx-runs">{{ $batRuns }}</span>@if($batWkts !== null)<span class="mdx-wkts">/{{ $batWkts }}</span>@endif
                            @if($batOvers !== '')<span class="mdx-ovs">({{ $batOvers }})</span>@endif
                        </div>
                        @if($crr !== '' || $chase)
                        <div class="mdx-bat-stats">
                            @if($crr !== '')<span><em>CRR</em> {{ $crr }}</span>@endif
                            @if($chase)<span><em>NEED</em> {{ $chase }}</span>@endif
                        </div>
                        @endif
                    </div>

                    {{-- The umpire gives the signal for the last ball; the caption names it. --}}
                    <div class="mdx-lastball {{ $ballInPlay ? 'is-bowling' : '' }}">
                        <div class="mdx-lastball-lbl"><span class="lb-bowl">BOWLING</span></div>
                        <div class="mdx-ump-slot">
                            <svg class="mdx-ump" viewBox="0 0 100 118" data-call="{{ $umpCall }}" data-key="{{ ($d['overs'] ?? '') . '|' . count((array) ($d['thisOver'] ?? [])) . '|' . $lastBall }}" aria-label="{{ $umpCaption }}"></svg>
                            <div class="mdx-ump-cap">{{ $umpCaption }}</div>
                        </div>
                        {{-- The scorer tapped BALL: a delivery down the pitch, looping until the
                             result lands (same drawing as the app's hero). --}}
                        <svg class="mdx-delivery" viewBox="0 0 84 66" aria-label="Ball in play">
                            <path d="M30 8 H54 L70.5 64.7 H13.5 Z" fill="#C8A96B" opacity=".9"/>
                            <line x1="28.4" y1="12" x2="55.6" y2="12" stroke="#fff" stroke-opacity=".85" stroke-width="1.2"/>
                            <line x1="14.9" y1="59.4" x2="69.1" y2="59.4" stroke="#fff" stroke-opacity=".85" stroke-width="1.5"/>
                            <g stroke="#0F172A" stroke-width="1.6">
                                <line x1="38.6" y1="1.4" x2="38.6" y2="10.6"/><line x1="42" y1="1.4" x2="42" y2="10.6"/><line x1="45.4" y1="1.4" x2="45.4" y2="10.6"/>
                            </g>
                            <ellipse class="dl-shadow" cx="0" cy="0" rx="5" ry="1.8" fill="#000" fill-opacity=".18"/>
                            <circle class="dl-puff" cx="42" cy="26" r="4" fill="#C8A96B"/>
                            <g class="dl-ball">
                                <circle cx="0" cy="0" r="5.2" fill="#C62828"/>
                                <line x1="-3.6" y1="0" x2="3.6" y2="0" stroke="#fff" stroke-opacity=".8" stroke-width=".9"/>
                            </g>
                        </svg>
                    </div>
                </div>

                <div class="mdx-hero-rule"></div>

                {{-- The fielding side on one quiet line: crest, code, and its score or "Yet to bat". --}}
                <div class="mdx-field">
                    <span class="mdx-crest sm" @if($fieldLogo==='')style="background:{{ $fieldCol }};border-color:transparent;"@endif>
                        @if($fieldLogo !== '')<img src="{{ $fieldLogo }}" alt="">@else<span>{{ $fieldCode }}</span>@endif
                    </span>
                    <b>{{ $fieldCode }}</b>
                    <span class="mdx-field-score {{ $fieldScore === '' ? 'is-yet' : '' }}">{{ $fieldScore !== '' ? $fieldScore : 'Yet to bat' }}</span>
                </div>

                @if($isLive)
                <div class="mdx-thisover">
                    <span class="mdx-thisover-lbl">THIS OVER</span>
                    <span class="mdx-thisover-balls">
                        @for($i = 0; $i < $overSlots; $i++)
                            @php $b = $overBalls[$i] ?? null; @endphp
                            @if($b === null)
                                <i class="ob is-empty"></i>
                            @else
                                @php $bk = hrn_ball_kind($b); @endphp
                                <i class="ob ob-{{ $bk }} {{ strlen($b) > 2 ? 'is-long' : '' }}">{{ $b }}</i>
                            @endif
                        @endfor
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Tab bar ── --}}
    <nav class="mdx-tabs" role="tablist">
        @foreach($tabs as $t)
            <a href="{{ $t['route'] }}" class="mdx-tab {{ $active === $t['key'] ? 'is-on' : '' }} {{ $t['key'] === 'live' && $isLive ? 'is-live' : '' }}" role="tab">
                {{-- MatchTabIcons.kt on a 24 grid: idle is a quiet outline; selected is inked,
                     with a soft blue body and ONE red detail matching the underline. --}}
                <svg class="mdx-tab-ic" viewBox="0 0 24 24" aria-hidden="true">
                    @switch($t['key'])
                        @case('info')
                            <circle class="g-turf" cx="12" cy="12" r="9.8"/>
                            <circle class="g-ink" cx="12" cy="12" r="9.8"/>
                            <circle class="g-ring" cx="12" cy="12" r="6.4"/>
                            <rect class="g-pitch" x="10.7" y="7.6" width="2.6" height="8.8" rx=".6"/>
                            <line class="g-spark" x1="11.4" y1="8.6" x2="12.6" y2="8.6"/><line class="g-spark" x1="11.4" y1="15.4" x2="12.6" y2="15.4"/>
                            @break
                        @case('commentary')
                            <rect class="g-body g-ink" x="8.5" y="2" width="7" height="11.5" rx="3.5"/>
                            <line class="g-spark" x1="10.6" y1="5.6" x2="13.4" y2="5.6"/><line class="g-ink g-soft" x1="10.6" y1="8.2" x2="13.4" y2="8.2"/>
                            <path class="g-ink" d="M18.5 10.75 A6.5 6.25 0 0 1 5.5 10.75"/>
                            <line class="g-ink" x1="12" y1="17" x2="12" y2="20.5"/><line class="g-ink" x1="8.5" y1="21" x2="15.5" y2="21"/>
                            @break
                        @case('live')
                            <circle class="g-halo" cx="12" cy="12" r="4.4"/>
                            <circle class="g-dot" cx="12" cy="12" r="2.6"/>
                            <path class="g-ink" d="M16.9 8.2 A6.2 6.2 0 0 1 16.9 15.8 M7.1 8.2 A6.2 6.2 0 0 0 7.1 15.8"/>
                            <path class="g-ink g-soft" d="M19.6 6.1 A9.6 9.6 0 0 1 19.6 17.9 M4.4 6.1 A9.6 9.6 0 0 0 4.4 17.9"/>
                            @break
                        @case('scorecard')
                            <line class="g-ink" x1="7" y1="18" x2="7" y2="22"/><line class="g-ink" x1="17" y1="18" x2="17" y2="22"/>
                            <rect class="g-body" x="2.5" y="3" width="19" height="15" rx="2.4"/>
                            <path class="g-head" d="M2.5 7.6 V5.4 A2.4 2.4 0 0 1 4.9 3 H19.1 A2.4 2.4 0 0 1 21.5 5.4 V7.6 Z"/>
                            <rect class="g-ink" x="2.5" y="3" width="19" height="15" rx="2.4"/>
                            <line class="g-ink g-soft" x1="5.4" y1="10.6" x2="12.5" y2="10.6"/><rect class="g-ink g-thin" x="15" y="9.3" width="3.6" height="2.6" rx=".6"/>
                            <line class="g-ink g-soft" x1="5.4" y1="14.4" x2="12.5" y2="14.4"/><rect class="g-ink g-thin" x="15" y="13.1" width="3.6" height="2.6" rx=".6"/>
                            @break
                        @case('mvp')
                            <path class="g-body g-ink" d="M6.5 3 H17.5 C17.5 10 15.5 13.5 12 13.8 C8.5 13.5 6.5 10 6.5 3 Z"/>
                            <path class="g-ink" d="M5.2 4.2 A2.2 2.8 0 0 0 5.2 9.8 M18.8 4.2 A2.2 2.8 0 0 1 18.8 9.8"/>
                            <line class="g-ink" x1="12" y1="14" x2="12" y2="17.4"/>
                            <rect class="g-body g-ink" x="7.8" y="17.6" width="8.4" height="3.6" rx="1"/>
                            <path class="g-star" d="M12 5.2 L12.69 6.85 L14.47 7 L13.12 8.17 L13.53 9.9 L12 8.98 L10.47 9.9 L10.88 8.17 L9.53 7 L11.31 6.85 Z"/>
                            @break
                        @default
                            <circle class="g-body g-ink" cx="12" cy="12" r="9.5"/>
                            <path class="g-ink g-shot" d="M12 12 L14.9 6.9 M12 12 L19.3 10.7 M12 12 L16.3 15 M12 12 L8.7 17.8 M12 12 L7.4 13.2 M12 12 L6.9 8.4"/>
                            <line class="g-spark g-wide" x1="12" y1="12" x2="7.54" y2="3.61"/><circle class="g-dot" cx="7.54" cy="3.61" r="1.9"/>
                            <rect class="g-fill" x="11" y="10" width="2" height="4" rx=".5"/>
                    @endswitch
                </svg>
                <span>{{ $t['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <script>
        // Tabs behave like the app's pager rather than six separate pages:
        //  · the bar and tab strip stick as one block, lifting off the content once scrolled;
        //  · a tap gives a light tick and keeps the reader at the tab strip (no jump to the
        //    top and back down past the hero) on the next tab's page.
        (function () {
            var root = document.querySelector('.mdx'), bar = document.querySelector('.mdx-bar'), tabs = document.querySelector('.mdx-tabs');
            if (!root || !bar || !tabs) return;
            var KEY = 'mdxTabStick' + @json((string) $id);
            function stickTop() { tabs.style.top = bar.offsetHeight + 'px'; }
            // Measured once styles apply (they load after this markup), and on any resize.
            document.addEventListener('DOMContentLoaded', stickTop);
            window.addEventListener('load', stickTop);
            var ep = window.mdxEpoch || 0;
            window.addEventListener('resize', function () { if ((window.mdxEpoch || 0) === ep) stickTop(); });
            if ('ResizeObserver' in window) new ResizeObserver(stickTop).observe(bar);
            stickTop();
            function lift() { if ((window.mdxEpoch || 0) !== ep) return; root.classList.toggle('is-scrolled', tabs.getBoundingClientRect().top <= bar.offsetHeight + 0.5 && window.scrollY > 4); }
            window.addEventListener('scroll', lift, { passive: true });
            lift();
            var land = false;
            try { land = !!sessionStorage.getItem(KEY); sessionStorage.removeItem(KEY); } catch (e) {}
            // After the tab body is parsed — before then the page is too short to scroll to it.
            // The browser resets scroll for a fresh navigation after DOMContentLoaded, so the
            // landing is applied then and held through load.
            if (land) {
                if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
                // Instant: the site's smooth scrolling would otherwise glide down past the hero.
                var toStrip = function () { window.scrollTo({ top: tabs.offsetTop - bar.offsetHeight, behavior: 'instant' }); lift(); };
                document.addEventListener('DOMContentLoaded', toStrip);
                window.addEventListener('pagereveal', toStrip);
                window.addEventListener('load', function () { toStrip(); requestAnimationFrame(toStrip); });
            }
            tabs.addEventListener('click', function (e) {
                var a = e.target.closest('.mdx-tab');
                if (!a) return;
                if (navigator.vibrate) { try { navigator.vibrate(8); } catch (x) {} }
                if (a.classList.contains('is-on')) { e.preventDefault(); return; }
                if (window.HaraanCommentary && window.HaraanCommentary.go(a.href)) { e.preventDefault(); return; }
                // Read past the hero: land the next tab at the strip too.
                try { if (tabs.getBoundingClientRect().top <= bar.offsetHeight + 1) sessionStorage.setItem(KEY, '1'); } catch (x) {}
            });
        })();
    </script>
    {{-- Tab pages are fetched ahead on touch, so the switch lands without a white wait. --}}
    <script type="speculationrules">{"prefetch":[{"source":"document","where":{"selector_matches":".mdx-tab"},"eagerness":"moderate"}]}</script>

    <div class="mdx-content">
        @yield('match_content')
        {{-- The match sponsor (Marketing → Ads, “Match detail”), else AdSense (Ad networks). --}}
        @if(($matchAd ?? null) || ($matchAdsense ?? null))
            <div class="mdx-sponsor">
                @include('site.partials.actionboard-sponsor', ['house' => $matchAd ?? null, 'adsense' => $matchAdsense ?? null])
            </div>
        @endif
    </div>
</div>

{{-- The scorebug's figures are cut in Archivo Black, like the app's ArchivoDisplay. --}}
<link href="https://fonts.googleapis.com/css2?family=Archivo+Black&display=swap" rel="stylesheet">
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
    /* The app's one face (Plus Jakarta Sans); Inter made this read as another product. */
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
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
/* ── Physical feel: things you press give a little, like the app's pressScale ── */
.mdx-ic { transition: background .15s, transform .12s cubic-bezier(.2,0,0,1); }
.mdx-ic:active { transform: scale(.92); background: #E2E8F0; }
.mdx-tab .mdx-tab-ic { transition: transform .14s cubic-bezier(.2,0,0,1); }
.mdx-tab:active .mdx-tab-ic { transform: scale(.84); }
.mdx-tab:active span { opacity: .7; }
.cm-row, .cm-prow, .mdx-sc-row { transition: background .12s; }
.cm-row:active, .cm-prow:active, .mdx-sc-row:active { background: #F8FAFC; }
.mdx-overchip { transition: transform .12s cubic-bezier(.2,0,0,1); }
.mdx-overchip:active { transform: scale(.97); }
/* An AdSense spot says "Advertisement" only once Google actually fills it — an unfilled
   unit was leaving a bare label over empty space at the foot of every tab. */
.mdx-sponsor:has(.abs-gad):not(:has(ins[data-ad-status="filled"])) .abs-ad__label { display: none; }
.mdx-sponsor ins[data-ad-status="unfilled"] { display: none !important; }
/* The bar and tabs stick as one sheet and lift once content slides under them. */
.mdx-bar, .mdx-tabs { transition: box-shadow .2s ease; }
.mdx.is-scrolled .mdx-tabs { box-shadow: 0 8px 16px -10px rgba(15,23,42,.22); }
.mdx.is-scrolled .mdx-hairline { opacity: 0; }
/* Tab to tab, the bar, hero and tab strip hold still while only the body crossfades. */
@view-transition { navigation: auto; }
.mdx-bar { view-transition-name: mdx-bar; }
.mdx-hero-wrap { view-transition-name: mdx-hero; }
.mdx-tabs { view-transition-name: mdx-tabs; }
::view-transition-old(root) { animation: mdxFadeOut .14s ease both; }
::view-transition-new(root) { animation: mdxFadeIn .2s ease both; }
@keyframes mdxFadeOut { to { opacity: 0; } }
@keyframes mdxFadeIn { from { opacity: 0; transform: translateY(6px); } }
@media (prefers-reduced-motion: reduce) { ::view-transition-group(*), ::view-transition-old(*), ::view-transition-new(*) { animation: none !important; } }
.mdx-ic svg { width: 19px; height: 19px; }
.mdx-share.is-done { background: #DCFCE7; color: #16A34A; border-color: #BBF7D0; }
.mdx-bar-title { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
.mdx-bar-teams { font-size: 17px; font-weight: 800; letter-spacing: -.2px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mdx-vs-lite { color: var(--muted); font-weight: 600; }
.mdx-live { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 5px; background: #FCE6E6; color: var(--red); font-size: 10.5px; font-weight: 800; letter-spacing: .5px; padding: 4px 7px; border-radius: 7px; }
.mdx-live-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--red); animation: mdxPulse .85s ease-in-out infinite alternate; }
@keyframes mdxPulse { from { opacity: 1; } to { opacity: .35; } }
.mdx-hairline { height: 1px; background: #EAEEF3; }

/* ── Hero ── */
.mdx-hero-wrap { padding: 8px 12px; background: var(--bg); }
.mdx-hero-band { position: relative; background: #fff; border: 1px solid var(--border); border-radius: 22px; padding: 10px; overflow: hidden; }
.mdx-ribbon { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
.mdx-ribbon text { fill: var(--rib, rgba(100,116,139,.53)); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 10px; font-weight: 800; letter-spacing: 1.4px; dominant-baseline: central; transition: fill .3s; }
.mdx-ribbon .rib-h { font-family: 'Archivo Black', sans-serif; font-size: 11px; letter-spacing: 0; }
/* Brand blue thinned with white: a soft wash that still reads as Haraan (CrexUI hero). */
.mdx-hero-card {
    --hink: #0F2A5C;
    position: relative;
    border-radius: 12px;
    background: linear-gradient(135deg, #F3F7FF 0%, #E2ECFE 100%);
    border: 1px solid #CFDDF6;
    padding: 9px 14px;
    color: var(--hink);
}
/* A 4, 6 or wicket: the card blinks twice in that colour, then fades (1s), like the app. */
.mdx-hero-card::after { content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none; opacity: 0; }
.mdx-flash-four::after { background: rgba(37,99,235,.42); }
.mdx-flash-six::after { background: rgba(21,128,61,.42); }
.mdx-flash-wicket::after { background: rgba(239,68,68,.42); }
.mdx-hero-card.is-fresh::after { animation: mdxFlash 1s ease-out 1; }
@keyframes mdxFlash { 0% { opacity: 0; } 8% { opacity: 1; } 25% { opacity: .3; } 35% { opacity: 1; } 100% { opacity: 0; } }
.mdx-hero-top { display: flex; align-items: center; gap: 10px; }
.mdx-hero-fmt { flex: 0 0 auto; max-width: 60%; font-size: 10.5px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(15,42,92,.72); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mdx-hero-toss { font-size: 11.5px; font-weight: 500; color: rgba(15,42,92,.72); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; text-align: right; }
.mdx-hero-main { display: flex; align-items: center; margin-top: 4px; }
.mdx-bat { flex: 1; min-width: 0; }
.mdx-bat-who { display: flex; align-items: center; gap: 8px; }
.mdx-bat-who b { font-size: 16px; font-weight: 700; letter-spacing: .4px; }
.mdx-bat-tag { font-size: 12.5px; font-weight: 500; color: rgba(15,42,92,.6); white-space: nowrap; }
.mdx-crest { width: 26px; height: 26px; border-radius: 50%; background: #fff; border: 1.5px solid #CBD5E1; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; flex: 0 0 auto; }
.mdx-crest.sm { width: 20px; height: 20px; border-width: 1px; }
.mdx-crest img { width: 100%; height: 100%; object-fit: cover; }
.mdx-crest span { color: #fff; font-size: 9px; font-weight: 800; }
.mdx-bat-score { display: flex; align-items: flex-end; margin-top: 2px; line-height: 1; font-variant-numeric: tabular-nums; white-space: nowrap; }
.mdx-runs { font-family: 'Archivo Black', 'Plus Jakarta Sans', sans-serif; font-size: 50px; letter-spacing: -1.6px; color: var(--hink); }
.mdx-wkts { font-family: 'Archivo Black', 'Plus Jakarta Sans', sans-serif; font-size: 26px; color: rgba(15,42,92,.55); padding-bottom: 4px; }
.mdx-ovs { font-size: 16px; font-weight: 600; color: rgba(15,42,92,.75); padding: 0 0 8px 8px; }
.mdx-bat-stats { display: flex; gap: 14px; margin-top: 3px; font-size: 12.5px; font-weight: 700; }
.mdx-bat-stats em { font-style: normal; font-weight: 600; letter-spacing: .6px; color: rgba(15,42,92,.62); margin-right: 4px; }
.mdx-hero-rule { height: 1px; background: rgba(15,42,92,.16); margin: 7px 0; }
.mdx-field { display: flex; align-items: center; gap: 8px; }
.mdx-field b { font-size: 14.5px; font-weight: 600; letter-spacing: .4px; color: rgba(15,42,92,.82); }
.mdx-field-score { margin-left: auto; font-size: 14.5px; font-weight: 600; color: rgba(15,42,92,.85); font-variant-numeric: tabular-nums; }
.mdx-field-score.is-yet { color: rgba(15,42,92,.6); }
.mdx-thisover { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.mdx-thisover-lbl { white-space: nowrap; font-size: 10px; font-weight: 700; letter-spacing: 1.2px; color: rgba(15,42,92,.6); }
.mdx-thisover-balls { display: flex; gap: 6px; }
.mdx-thisover .ob { width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-style: normal; font-size: 11.5px; font-weight: 700; background: #fff; border: 1px solid #CFDDF6; color: #0F172A; }
.mdx-thisover .ob.is-long { font-size: 9px; }
.mdx-thisover .ob.is-empty { background: transparent; border-color: rgba(15,42,92,.28); }
.mdx-thisover .ob-four { color: var(--blue); border: 1.5px solid var(--blue); }
.mdx-thisover .ob-six { color: #15803D; border: 1.5px solid #15803D; }
.mdx-thisover .ob-wicket { color: var(--red); border: 1.5px solid var(--red); }
.mdx-thisover .ob-dot { color: #94A3B8; }
/* Umpire slot: he stands on a floor line with his reflection, and gives the signal. */
.mdx-lastball { display: flex; flex-direction: column; align-items: center; padding: 0 0 0 6px; flex: 0 0 auto; }
.mdx-lastball-lbl { color: var(--hink); font-size: 9px; font-weight: 700; letter-spacing: 1.5px; }
.mdx-ump-slot { display: flex; flex-direction: column; align-items: center; }
.mdx-ump { width: 68px; height: 80px; display: block; overflow: hidden; }
.mdx-ump-cap { margin-top: -1px; font-size: 13px; font-weight: 800; letter-spacing: 1.2px; color: var(--hink); white-space: nowrap; position: relative; }
.mdx-lastball .lb-bowl, .mdx-delivery, .mdx-lastball-lbl { display: none; }
.mdx-lastball.is-bowling .mdx-ump-slot { display: none; }
.mdx-lastball.is-bowling .mdx-lastball-lbl, .mdx-lastball.is-bowling .lb-bowl { display: inline; color: var(--hink); }
.mdx-lastball.is-bowling .mdx-delivery { display: block; width: 76px; height: 60px; margin: 6px 0 4px; }
@media (max-width: 340px) { .mdx-runs { font-size: 46px; } .mdx-wkts { font-size: 24px; } .mdx-ump { width: 62px; height: 73px; } .mdx-ump-cap { font-size: 12px; letter-spacing: 1px; } .mdx-thisover { gap: 8px; } .mdx-thisover-balls { gap: 4px; } .mdx-thisover .ob { width: 23px; height: 23px; font-size: 10.5px; } }
/* One delivery per 1.3s loop: release, dip to pitch at ~50%, carry to the stumps, gone for the
   last 20% (the run-up). Ball and shadow move together so the bounce reads as a bounce. */
.mdx-delivery .dl-ball { animation: mdxDeliver 1.3s linear infinite; }
.mdx-delivery .dl-shadow { animation: mdxShadow 1.3s linear infinite; }
.mdx-delivery .dl-puff { opacity: 0; transform-box: fill-box; transform-origin: center; animation: mdxPuff 1.3s linear infinite; }
@keyframes mdxDeliver {
    0%    { transform: translate(42px, 34.6px) scale(1);    opacity: 1; }
    25%   { transform: translate(42px, 27px)   scale(.86); }
    49.6% { transform: translate(42px, 26px)   scale(.74); }
    65%   { transform: translate(42px, 15px)   scale(.64); }
    79.9% { transform: translate(42px, 2.5px)  scale(.54);  opacity: 1; }
    80%, 100% { transform: translate(42px, 2.5px) scale(.54); opacity: 0; }
}
@keyframes mdxShadow {
    0%    { transform: translate(42px, 54.4px) scale(1);   opacity: 1; }
    49.6% { transform: translate(42px, 26px)   scale(.74); }
    79.9% { transform: translate(42px, 9px)    scale(.54); opacity: 1; }
    80%, 100% { opacity: 0; }
}
@keyframes mdxPuff {
    0%, 49.5% { opacity: 0; transform: scale(1); }
    49.6% { opacity: .55; transform: scale(1); }
    60%, 100% { opacity: 0; transform: scale(2.6); }
}
@media (prefers-reduced-motion: reduce) {
    .mdx-delivery .dl-ball, .mdx-delivery .dl-shadow { animation-duration: 2.6s; }
}
/* ── Tabs (MatchTabs.kt): six equal slots, glyph over an 11px label, red underline ── */
.mdx-tabs { display: flex; background: var(--bg); position: sticky; top: 58px; z-index: 20; border-bottom: 1px solid var(--border); }
.mdx-tab { flex: 1 0 auto; min-width: 0; padding: 10px 2px 9px; white-space: nowrap; font-size: 12px; font-weight: 500; color: var(--ink2); text-decoration: none; position: relative; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.mdx-tab span { letter-spacing: -.1px; }
.mdx-tab.is-on { color: var(--ink); font-weight: 600; }
.mdx-tab.is-on::after { content: ""; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%); width: 22px; height: 2px; border-radius: 2px; background: var(--red); }
.mdx-tab-ic { width: 21px; height: 21px; display: block; overflow: visible;
    --gi: var(--muted); --gb: transparent; --gs: var(--muted); }
.mdx-tab.is-on .mdx-tab-ic { --gi: var(--ink); --gb: rgba(37,99,235,.12); --gs: var(--red); }
.mdx-tab.is-live .mdx-tab-ic { --gs: var(--red); animation: mdxPulse .9s ease-in-out infinite alternate; }
.mdx-tab-ic * { fill: none; stroke-linecap: round; stroke-linejoin: round; }
.mdx-tab-ic .g-ink { stroke: var(--gi); stroke-width: 1.55; }
.mdx-tab-ic .g-soft { stroke-opacity: .6; stroke-width: 1.4; }
.mdx-tab-ic .g-thin { stroke-width: 1.2; stroke-opacity: .85; }
.mdx-tab-ic .g-shot { stroke-opacity: .55; stroke-width: 1.2; }
.mdx-tab-ic .g-body { fill: var(--gb); }
.mdx-tab-ic .g-spark { stroke: var(--gs); stroke-width: 1.2; }
.mdx-tab-ic .g-wide { stroke-width: 1.6; }
.mdx-tab-ic .g-dot { fill: var(--gs); }
.mdx-tab-ic .g-star { fill: var(--gs); }
.mdx-tab-ic .g-fill { fill: var(--gi); }
.mdx-tab-ic .g-halo { fill: transparent; }
.mdx-tab.is-on .mdx-tab-ic .g-halo { fill: rgba(239,68,68,.18); }
.mdx-tab-ic .g-ring { stroke: var(--gi); stroke-opacity: .45; stroke-width: 1; stroke-dasharray: 2.23 1.79; }
.mdx-tab-ic .g-turf { fill: transparent; }
.mdx-tab.is-on .mdx-tab-ic .g-turf { fill: rgba(34,160,107,.18); }
.mdx-tab-ic .g-pitch { stroke: var(--gi); stroke-width: 1.3; }
.mdx-tab.is-on .mdx-tab-ic .g-pitch { fill: #E8D3A6; }
.mdx-tab-ic .g-head { fill: var(--gi); fill-opacity: .18; }
.mdx-tab.is-on .mdx-tab-ic .g-head { fill: var(--gs); fill-opacity: .9; }

/* ── Content shell ── */
.mdx-content { padding: 14px 16px; display: flex; flex-direction: column; gap: 14px; max-width: 720px; margin: 0 auto; }
.mdx-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 10px 24px rgba(15,23,42,.05); }
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
.mdx-bat-name.striker { color: var(--blue); font-weight: 700; }
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
/* The over strip runs to the screen edges and snaps over by over, like the app's row. */
.mdx-overchips { display: flex; gap: 10px; overflow-x: auto; margin: 0 -16px; padding: 2px 16px 4px; scroll-padding: 0 16px;
    scroll-snap-type: x proximity; scrollbar-width: none; overscroll-behavior-x: contain; -webkit-overflow-scrolling: touch; }
.mdx-overchips::-webkit-scrollbar { display: none; }
.mdx-overchips > * { scroll-snap-align: start; }
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

/* Phones: the card stops at the fielding line. The over's balls are the first thing under the
   tabs on Commentary and Live, so repeating them here only pushed the feed down. */
@media (max-width: 720px) { .mdx-thisover { display: none; } }
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
        // The word the app's ScoringRibbon crawls: the last ball's moment in its colour,
        // else a calm grey "HARAAN LIVE" — each word followed by the brand H.
        var WORDS = {
            calm:    ['HARAAN  LIVE', 'rgba(100,116,139,.53)'],
            four:    ['FOUR', '#2563EB'],
            six:     ['SIX', '#16A34A'],
            wicket:  ['WICKET', '#D62828'],
            bowling: ['BALL  IN  PLAY', '#2563EB'],
        };
        var calmKey = @json($isLive ? $ribbonWord : 'calm');
        var key = document.querySelector('.mdx-lastball.is-bowling') ? 'bowling' : calmKey;
        var segLen = 140;
        function seg(n) {
            var w = WORDS[key], out = '';
            for (var i = 0; i < n; i++) out += '<tspan>' + w[0] + ' </tspan><tspan class="rib-h">H</tspan><tspan> </tspan>';
            return out;
        }
        // The pulse poll flips this while the bowler runs in, and back when it doesn't.
        window.mdxRibbonBowling = function (on) {
            var next = on ? 'bowling' : calmKey;
            if (next === key) return;
            key = next;
            layout();
        };

        function layout() {
            var w = Math.round(band.clientWidth), h = Math.round(band.clientHeight);
            if (!w || !h) return;
            svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
            var i = 5, r = 17, x0 = i, y0 = i, x1 = w - i, y1 = h - i;
            path.setAttribute('d',
                'M ' + (x0 + r) + ' ' + y0 +
                ' H ' + (x1 - r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + x1 + ' ' + (y0 + r) +
                ' V ' + (y1 - r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + (x1 - r) + ' ' + y1 +
                ' H ' + (x0 + r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + x0 + ' ' + (y1 - r) +
                ' V ' + (y0 + r) + ' A ' + r + ' ' + r + ' 0 0 1 ' + (x0 + r) + ' ' + y0 + ' Z');
            svg.style.setProperty('--rib', WORDS[key][1]);
            tp.innerHTML = seg(1);
            segLen = tp.getComputedTextLength() || 140;
            var per = path.getTotalLength() || (w * 2 + h * 2);
            var reps = Math.ceil((per + segLen) / segLen) + 2;
            tp.innerHTML = seg(reps);
        }

        layout();
        var off = 0, ep = window.mdxEpoch || 0;
        function tick() {
            if ((window.mdxEpoch || 0) !== ep) return;
            off -= 0.35;
            if (off <= -segLen) off += segLen;
            tp.setAttribute('startOffset', off);
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
        var rt;
        window.addEventListener('resize', function () { if ((window.mdxEpoch || 0) !== ep) return; clearTimeout(rt); rt = setTimeout(layout, 150); });
    })();
</script>
<script>
    // A reload the live pulse caused means a new ball just landed: flash the card for it.
    (function () {
        try {
            var k = 'mdxFresh' + @json((string) $id);
            if (sessionStorage.getItem(k)) {
                sessionStorage.removeItem(k);
                var card = document.querySelector('.mdx-hero-card');
                if (card) card.classList.add('is-fresh');
            }
        } catch (e) {}
    })();
</script>
<script>
    // The umpire for the last ball (UmpireSignal.kt), drawn as SVG: waist-up on a floor line
    // with his reflection under it. He rises for the ball and gives the real signal — a FOUR
    // waved across the waist, a SIX with both arms up, OUT with the finger — then breathes.
    (function () {
        var svg = document.querySelector('.mdx-ump');
        if (!svg) return;
        var NS = 'http://www.w3.org/2000/svg';
        var W = 100, H = 118, FH = H * 0.76;
        var C = { skin: '#F4A169', skinShade: '#E2874F', hair: '#1E2A44', shirt: '#F8F9FB', shirtShade: '#DADFE6',
                  shirtLine: '#C3CAD4', hat: '#F8F9FB', hatShade: '#D6DCE3', band: '#1E3A5F', blue: '#2563EB' };
        var REST = [100, 92, 80, 88];
        var POSES = {
            '': REST, 'LEG BYE': REST,
            'FOUR': [84, 2, 80, 88],
            'SIX': [-114, -100, -66, -80],
            'OUT': [100, 92, -60, -86],
            'BYE': [100, 92, -62, -90],
            'WIDE': [178, 182, 2, -2],
            'NO BALL': [100, 92, 4, 0],
        };
        var call = svg.getAttribute('data-call') || '';
        var target = POSES[call] || REST;

        function f(n) { return Math.round(n * 100) / 100; }
        function dir(deg) { var r = deg * Math.PI / 180; return [Math.cos(r), Math.sin(r)]; }
        function cap(a, b, w, c) { return '<line x1="' + f(a[0]) + '" y1="' + f(a[1]) + '" x2="' + f(b[0]) + '" y2="' + f(b[1]) + '" stroke="' + c + '" stroke-width="' + f(w) + '" stroke-linecap="round"/>'; }
        function rect(x, y, w, h, rx, ry, c) { return '<rect x="' + f(x) + '" y="' + f(y) + '" width="' + f(w) + '" height="' + f(h) + '" rx="' + f(rx) + '" ry="' + f(ry) + '" fill="' + c + '"/>'; }
        function poly(pts, c) { return '<path d="M' + pts.map(function (p) { return f(p[0]) + ' ' + f(p[1]); }).join(' L') + ' Z" fill="' + c + '"/>'; }

        function hand(wr, deg, open, finger, ts, u) {
            var pw = u * 1.25, ph = u * 1.05, top = wr[1] - ph * 0.95, x = wr[0], y = wr[1], s = '';
            if (open) {
                [-0.36, -0.12, 0.12, 0.36].forEach(function (g, i) {
                    var fx = x + g * pw;
                    s += cap([fx, top + u * 0.2], [fx + g * u * 0.25, top - u * [0.62, 0.78, 0.74, 0.58][i]], u * 0.27, C.skin);
                });
                s += cap([x + ts * pw * 0.35, y - ph * 0.35], [x + ts * pw * 0.85, y - ph * 0.85], u * 0.3, C.skin);
                s += rect(x - pw / 2, top, pw, ph, u * 0.35, u * 0.35, C.skin);
                s += cap([x - pw * 0.32, top + u * 0.2], [x + pw * 0.32, top + u * 0.2], u * 0.07, C.skinShade);
            } else {
                s += rect(x - pw / 2, y - ph * 0.9, pw, ph * 0.95, u * 0.45, u * 0.45, C.skin);
                s += cap([x - pw * 0.3, y - ph * 0.55], [x + pw * 0.3, y - ph * 0.55], u * 0.07, C.skinShade);
                if (finger > 0) { var fx2 = x - pw * 0.22; s += cap([fx2, y - ph * 0.8], [fx2, y - ph * 0.8 - u * 1.1 * finger], u * 0.3, C.skin); }
            }
            return '<g transform="rotate(' + f(deg + 90) + ' ' + f(x) + ' ' + f(y) + ')">' + s + '</g>';
        }

        function figure(p, finger) {
            var cx = W / 2, sh = W * 0.30, nh = W * 0.055, nb = FH * 0.56, sy = FH * 0.60, wh = W * 0.25, s = '';
            s += rect(cx - nh, FH * 0.46, nh * 2, nb - FH * 0.46 + FH * 0.04, 0, 0, C.skinShade);
            function body(i) {
                return '<path fill="' + (i ? C.shirt : C.shirtShade) + '" d="M' + f(cx - nh * 1.4) + ' ' + f(nb) +
                    ' Q' + f(cx - sh * 0.6) + ' ' + f(nb + FH * 0.005) + ' ' + f(cx - sh + 5 + i) + ' ' + f(sy) +
                    ' L' + f(cx - sh + 4 + i) + ' ' + f(sy + FH * 0.3) + ' L' + f(cx - wh + i) + ' ' + f(FH * 1.1) +
                    ' L' + f(cx + wh - i) + ' ' + f(FH * 1.1) + ' L' + f(cx + sh - 4 - i) + ' ' + f(sy + FH * 0.3) +
                    ' L' + f(cx + sh - 5 - i) + ' ' + f(sy) + ' Q' + f(cx + sh * 0.6) + ' ' + f(nb + FH * 0.005) + ' ' + f(cx + nh * 1.4) + ' ' + f(nb) + ' Z"/>';
            }
            s += body(0) + body(4.5);
            var up = 20, fo = 24, aw = 10, sr = 8.5, u = 7;
            var jl = [cx - sh + sr, sy + FH * 0.05], jr = [cx + sh - sr, sy + FH * 0.05];
            function arm(j, ud, fd, fing, ts) {
                var a = dir(ud), b = dir(fd);
                var el = [j[0] + a[0] * up, j[1] + a[1] * up], wr = [el[0] + b[0] * fo, el[1] + b[1] * fo];
                return cap(j, el, aw, C.skin) + cap(el, wr, aw * 0.88, C.skin) + hand(wr, fd, a[1] < 0.4 && fing === 0, fing, ts, u);
            }
            function sleeve(j, ud, side) {
                var a = dir(ud), n = [-a[1], a[0]], hem = [j[0] + a[0] * up * 0.55, j[1] + a[1] * up * 0.55], hh = sr * 1.02;
                function shape(h0, h1, sh2, c) {
                    var sx = n[0] * sh2, sy2 = n[1] * sh2;
                    return poly([[j[0] + n[0] * h0 + sx, j[1] + n[1] * h0 + sy2], [hem[0] + n[0] * h1 + sx, hem[1] + n[1] * h1 + sy2],
                                 [hem[0] - n[0] * h1 + sx, hem[1] - n[1] * h1 + sy2], [j[0] - n[0] * h0 + sx, j[1] - n[1] * h0 + sy2]], c);
                }
                return '<circle cx="' + f(j[0]) + '" cy="' + f(j[1]) + '" r="' + sr + '" fill="' + C.shirt + '"/>' +
                    shape(sr, hh, 0, C.shirtShade) + shape(sr * 0.72, hh * 0.72, -side * sr * 0.28, C.shirt);
            }
            s += arm(jl, p[0], p[1], 0, 1) + arm(jr, p[2], p[3], finger, -1) + sleeve(jl, p[0], 1) + sleeve(jr, p[2], -1);
            // Collar V, placket with two buttons, folded collar points.
            var cy = nb - FH * 0.005, pt = cy + FH * 0.065;
            s += poly([[cx - nh * 1.1, cy], [cx, cy + FH * 0.07], [cx + nh * 1.1, cy]], C.skinShade);
            s += rect(cx - 2.8, pt, 5.6, FH * 0.17, 0, 0, C.shirtLine) + rect(cx - 2.2, pt, 4.4, FH * 0.165, 0, 0, C.shirt);
            s += '<circle cx="' + cx + '" cy="' + f(pt + FH * 0.05) + '" r="1.1" fill="' + C.shirtLine + '"/><circle cx="' + cx + '" cy="' + f(pt + FH * 0.12) + '" r="1.1" fill="' + C.shirtLine + '"/>';
            [-1, 1].forEach(function (sd) {
                var pts = [[cx + sd * nh * 1.25, cy - FH * 0.015], [cx + sd * 13.5, cy + FH * 0.03], [cx + sd * 3, cy + FH * 0.085], [cx + sd * nh * 0.55, cy + FH * 0.01]];
                s += '<g transform="translate(0 ' + f(FH * 0.012) + ')">' + poly(pts, C.shirtShade) + '</g>' + poly(pts, C.shirt);
            });
            // The brand H on the left chest.
            s += '<text x="' + f(cx + 10) + '" y="' + f(sy + FH * 0.085 + FH * 0.085) + '" font-family="Archivo Black, sans-serif" font-size="' + f(FH * 0.1) + '" fill="' + C.blue + '">H</text>';
            // Head: hair at the sides, ears, a plain face.
            var fw = W * 0.21, ft = FH * 0.24, fh = FH * 0.26;
            s += rect(cx - fw * 0.62, ft, fw * 1.24, fh * 0.62, fw * 0.3, fw * 0.3, C.hair);
            s += '<ellipse cx="' + f(cx - fw / 2) + '" cy="' + f(ft + fh * 0.53) + '" rx="3" ry="' + f(fh * 0.13) + '" fill="' + C.skin + '"/>';
            s += '<ellipse cx="' + f(cx + fw / 2) + '" cy="' + f(ft + fh * 0.53) + '" rx="3" ry="' + f(fh * 0.13) + '" fill="' + C.skin + '"/>';
            s += rect(cx - fw / 2, ft + fh * 0.08, fw, fh * 0.92, fw * 0.5, fh * 0.45, C.skin);
            // Hat: crown with a navy band on a wide, gently curved brim.
            var by = ft + fh * 0.16, bh = W * 0.31;
            function brim(dy, c) {
                return '<path fill="' + c + '" d="M' + f(cx - bh) + ' ' + f(by + FH * 0.02 + dy) + ' Q' + cx + ' ' + f(by - FH * 0.05 + dy) + ' ' + f(cx + bh) + ' ' + f(by + FH * 0.02 + dy) +
                    ' Q' + cx + ' ' + f(by + FH * 0.055 + dy) + ' ' + f(cx - bh) + ' ' + f(by + FH * 0.02 + dy) + ' Z"/>';
            }
            s += brim(FH * 0.016, C.hatShade) + brim(0, C.hat);
            var cb = by - FH * 0.005, ct = by - FH * 0.15, hb = W * 0.14, ht = W * 0.115;
            var crown = 'M' + f(cx - hb) + ' ' + f(cb) + ' L' + f(cx - ht) + ' ' + f(ct + FH * 0.03) + ' Q' + f(cx - ht) + ' ' + f(ct) + ' ' + f(cx - ht + 3) + ' ' + f(ct) +
                ' L' + f(cx + ht - 3) + ' ' + f(ct) + ' Q' + f(cx + ht) + ' ' + f(ct) + ' ' + f(cx + ht) + ' ' + f(ct + FH * 0.03) + ' L' + f(cx + hb) + ' ' + f(cb) + ' Z';
            s += '<path d="' + crown + '" fill="' + C.hat + '"/><path d="' + crown + '" fill="' + C.hatShade + '" clip-path="url(#uCrownR)"/>';
            s += poly([[cx - hb, cb], [cx - hb + 0.8, cb - FH * 0.045], [cx + hb - 0.8, cb - FH * 0.045], [cx + hb, cb]], C.band);
            return s;
        }

        svg.innerHTML =
            '<defs>' +
              '<clipPath id="uTop"><rect x="0" y="0" width="' + W + '" height="' + f(FH) + '"/></clipPath>' +
              '<clipPath id="uBot"><rect x="0" y="' + f(FH) + '" width="' + W + '" height="' + f(H - FH) + '"/></clipPath>' +
              '<clipPath id="uCrownR"><rect x="' + f(W / 2 + W * 0.115 * 0.45) + '" y="0" width="' + W + '" height="' + H + '"/></clipPath>' +
              '<linearGradient id="uFade" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="' + H + '">' +
                '<stop offset="' + f(FH / H) + '" stop-color="#fff"/><stop offset="' + f(FH / H + 0.001) + '" stop-color="#fff" stop-opacity=".32"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>' +
              '</linearGradient>' +
              '<mask id="uMask"><rect width="' + W + '" height="' + H + '" fill="url(#uFade)"/></mask>' +
            '</defs>' +
            '<g mask="url(#uMask)">' +
              '<g clip-path="url(#uTop)"><g id="uFig"></g></g>' +
              '<g clip-path="url(#uBot)"><g transform="translate(0 ' + f(FH * 2) + ') scale(1 -1)"><use href="#uFig"/></g></g>' +
            '</g>' +
            '<line x1="8" y1="' + f(FH) + '" x2="92" y2="' + f(FH) + '" stroke="#0F2A5C" stroke-opacity=".14" stroke-width="1"/>';
        var fig = svg.querySelector('#uFig');

        // Motion state, as in the composable: rise 1 = below the floor, 0 = standing on it.
        var st = { rise: 1, p: REST.slice(), finger: 0, lift: 0, wave: 0 };
        function paint(t, moveOnly) {
            var breath = (Math.sin(t / 1800 * Math.PI) + 1) / 2;
            var dy = FH * 0.07 + st.rise * FH * 1.02 + breath * 0.7 - st.lift * 3.5;
            var p = st.p.slice(); p[1] += st.wave;
            fig.setAttribute('transform', 'translate(0 ' + f(dy) + ')');
            if (!moveOnly) fig.innerHTML = figure(p, st.finger);
        }
        function tween(ms, ease, step) {
            return new Promise(function (done) {
                var t0 = performance.now();
                (function fr(now) {
                    var k = Math.min(1, (now - t0) / ms);
                    step(ease(k)); paint(now);
                    if (k < 1) requestAnimationFrame(fr); else done();
                })(t0);
            });
        }
        var springy = function (k) { var c = 1.7, c3 = c + 1; return 1 + c3 * Math.pow(k - 1, 3) + c * Math.pow(k - 1, 2); };
        var smooth = function (k) { return k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2; };
        function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

        var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
        var umpEp = window.mdxEpoch || 0;
        // A ball he has already signalled (moving between tabs, a refetch) is not a new
        // moment: he is simply standing there with the signal, as the app keys on the ball.
        var seenKey = 'mdxUmp' + @json((string) $id), ballKey = svg.getAttribute('data-key') || '';
        var seen = false;
        try { seen = sessionStorage.getItem(seenKey) === ballKey; sessionStorage.setItem(seenKey, ballKey); } catch (e) {}
        if (reduced || seen) {
            st.rise = 0; st.p = target.slice(); st.finger = call === 'OUT' ? 1 : 0; paint(0);
            if (!reduced) (function idle(now) { if ((window.mdxEpoch || 0) !== umpEp) return; paint(now, true); requestAnimationFrame(idle); })(performance.now());
            return;
        }

        // A fresh 4, 6 or wicket: the card flashes first and he stays down until it has.
        var held = ['FOUR', 'SIX', 'OUT'].indexOf(call) >= 0 && document.querySelector('.mdx-hero-card.is-fresh');
        paint(0);
        (held ? wait(1000) : Promise.resolve())
            .then(function () { return tween(550, springy, function (k) { st.rise = 1 - k; }); })
            .then(function () { return wait(60); })
            .then(function () { return tween(500, springy, function (k) { for (var i = 0; i < 4; i++) st.p[i] = REST[i] + (target[i] - REST[i]) * k; }); })
            .then(function () {
                if (call === 'FOUR') {
                    var chain = Promise.resolve();
                    for (var i = 0; i < 3; i++) {
                        chain = chain.then(function () { return tween(230, smooth, function (k) { st.wave = -26 * k; }); })
                                     .then(function () { return tween(230, smooth, function (k) { st.wave = -26 + 40 * k; }); });
                    }
                    return chain.then(function () { return tween(180, smooth, function (k) { st.wave = 14 * (1 - k); }); });
                }
                if (call === 'SIX') {
                    return tween(200, smooth, function (k) { st.lift = k; }).then(function () { return tween(260, springy, function (k) { st.lift = 1 - k; }); })
                        .then(function () { return tween(200, smooth, function (k) { st.lift = k; }); }).then(function () { return tween(260, springy, function (k) { st.lift = 1 - k; }); });
                }
                if (call === 'OUT') return tween(260, smooth, function (k) { st.finger = k; });
            })
            .then(function () {
                // Between balls he breathes, so the slot never reads as a sticker.
                (function idle(now) { if ((window.mdxEpoch || 0) !== umpEp) return; paint(now, true); if (!document.hidden) requestAnimationFrame(idle); else setTimeout(function () { requestAnimationFrame(idle); }, 500); })(performance.now());
            });
    })();
</script>
@if($isLive)
<script>
    // Live heartbeat. Every 3s: is a ball in play (show the delivery), and has anything
    // been scored since this page rendered (reload to show it). Replaces a blind 30s
    // reload, which missed the moment the ball was bowled entirely.
    (function () {
        var url = @json(url('/api/live-matches/' . $id . '/pulse'));
        var v = @json($pulseV);
        var air = @json($commentaryOnAir);
        var slot = document.querySelector('.mdx-lastball');
        var fails = 0, timer = null, ep = window.mdxEpoch || 0;
        function refresh() {
            if (window.HaraanCommentary) window.HaraanCommentary.refresh(); else location.reload();
        }

        function bowling(on) {
            if (slot) slot.classList.toggle('is-bowling', on);
            if (window.mdxRibbonBowling) window.mdxRibbonBowling(on);
        }

        function beat() {
            if ((window.mdxEpoch || 0) !== ep) return;
            // A background tab doesn't need the ball; it catches up on return.
            if (document.hidden) { timer = setTimeout(beat, 3000); return; }
            fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (p) {
                    fails = 0;
                    if ((window.mdxEpoch || 0) !== ep) return;
                    var airNow = !!p.commentary;
                    if (p.v !== v || !p.live || airNow !== air) {
                        if (p.v !== v) { try { sessionStorage.setItem('mdxFresh' + @json((string) $id), '1'); } catch (e) {} }
                        refresh(); return;
                    }
                    bowling(!!p.ballInPlay);
                    timer = setTimeout(beat, 3000);
                })
                .catch(function () {
                    // Pulse unreachable (private match, network): fall back to the old
                    // behaviour rather than freezing the score.
                    if (++fails >= 10) { refresh(); return; }
                    timer = setTimeout(beat, 3000);
                });
        }

        timer = setTimeout(beat, 3000);
        document.addEventListener('visibilitychange', function () {
            if ((window.mdxEpoch || 0) !== ep) return;
            if (!document.hidden) { clearTimeout(timer); beat(); }
        });
    })();
</script>
@endif
@include('site.partials.match-commentary', ['id' => $id, 'commentary' => $commentary])
@endsection
