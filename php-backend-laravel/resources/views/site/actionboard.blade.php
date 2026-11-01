@extends('site.layout')

@section('content')
@include('site.partials.match-helpers')
@php
    $featuredMatch = $activeMatch ?? ($matches[0] ?? null);
    $liveMatches = array_values(array_filter($matches, fn ($match) => strcasecmp($match['status'], 'Live') === 0));
    $upcomingMatches = array_values(array_filter($matches, fn ($match) => strcasecmp($match['status'], 'Scheduled') === 0));
    $recentMatches = array_values(array_filter($matches, fn ($match) => strcasecmp($match['status'], 'Live') !== 0 && strcasecmp($match['status'], 'Scheduled') !== 0));
    
    $liveCount = count($liveMatches);
    $upcomingCount = count($upcomingMatches);
    $recentCount = count($recentMatches);
    $headlineMatch = $matches[0] ?? null;

    // Custom stats parser to extract live wickets/overs from summary & text
    $getMatchStats = function($m) {
        if (!$m) return null;
        $homeScore = $m['home_score'] ?? 0;
        $awayScore = $m['away_score'] ?? 0;
        $overs = $m['overs'] ?? '0.0';
        $scoreText = $m['score_text'] ?? '';
        
        $overSummary = isset($m['over_summary']) 
            ? (is_array($m['over_summary']) ? $m['over_summary'] : (json_decode($m['over_summary'], true) ?: []))
            : [];
            
        $lastOver = !empty($overSummary) ? end($overSummary) : null;
        $battingTeam = isset($lastOver['batting']) ? $lastOver['batting'] : 'home';
        
        $homeSummaryWickets = 0;
        $awaySummaryWickets = 0;
        $innings1OversCount = 0;
        $innings2OversCount = 0;
        
        foreach ($overSummary as $o) {
            $oBatting = $o['batting'] ?? 'home';
            if ($oBatting === 'home') {
                $innings1OversCount++;
                if (isset($o['balls']) && is_array($o['balls'])) {
                    foreach ($o['balls'] as $ball) {
                        if (strtoupper(trim((string)$ball)) === 'W') {
                            $homeSummaryWickets++;
                        }
                    }
                }
            } else {
                $innings2OversCount++;
                if (isset($o['balls']) && is_array($o['balls'])) {
                    foreach ($o['balls'] as $ball) {
                        if (strtoupper(trim((string)$ball)) === 'W') {
                            $awaySummaryWickets++;
                        }
                    }
                }
            }
        }
        
        $wicketsCount = 0;
        if (preg_match('/-(\d+)/', $scoreText, $matchWkts)) {
            $wicketsCount = (int)$matchWkts[1];
        } else {
            $wicketsCount = ($battingTeam === 'home') ? $homeSummaryWickets : $awaySummaryWickets;
        }
        
        $homeWkts = ($battingTeam === 'home') ? $wicketsCount : $homeSummaryWickets;
        $awayWkts = ($battingTeam === 'away') ? $wicketsCount : $awaySummaryWickets;
        
        $homeOversVal = ($battingTeam === 'home') ? $overs : ($innings1OversCount . '.0');
        $awayOversVal = ($battingTeam === 'away') ? $overs : ($innings2OversCount . '.0');
        
        $isLive = strcasecmp($m['status'], 'Live') === 0;
        $isScheduled = strcasecmp($m['status'], 'Scheduled') === 0;
        
        if ($isScheduled) {
            return [
                'home_runs' => '--',
                'home_wickets' => '',
                'home_overs' => '',
                'away_runs' => '--',
                'away_wickets' => '',
                'away_overs' => '',
                'batting_team' => 'none',
                'is_live' => false,
                'is_scheduled' => true,
                'home_display' => '--',
                'away_display' => '--',
                'home_score_only' => '--',
                'away_score_only' => '--'
            ];
        }
        
        return [
            'home_runs' => $homeScore,
            'home_wickets' => '/' . $homeWkts,
            'home_overs' => '(' . $homeOversVal . ' ov)',
            'away_runs' => $awayScore,
            'away_wickets' => '/' . $awayWkts,
            'away_overs' => '(' . $awayOversVal . ' ov)',
            'batting_team' => $battingTeam,
            'is_live' => $isLive,
            'is_scheduled' => $isScheduled,
            'home_display' => "$homeScore/$homeWkts ($homeOversVal ov)",
            'away_display' => "$awayScore/$awayWkts ($awayOversVal ov)",
            'home_score_only' => "$homeScore/$homeWkts",
            'away_score_only' => "$awayScore/$awayWkts"
        ];
    };
@endphp

<div class="actionboard-root-theme">

{{-- ================================================================= --}}
{{-- MOBILE APP-STYLE ACTIONBOARD (mirrors the Android app; ≤720px)     --}}
{{--                                                                    --}}
{{-- A port of MainScreen.kt CrexMatchesScreen: header (logo tile +     --}}
{{-- wordmark, search / join-by-code / Create) → Live·Finished·District --}}
{{-- ·State tabs with a sliding indicator → grouped live-feed cards     --}}
{{-- ("Live in your district" / "Featured matches") → District Home     --}}
{{-- card + ranked-XP podium/list boards → floating bottom bar.         --}}
{{-- Values track the app's tokens (LightBackground #F5F5F5, accent     --}}
{{-- #2563EB, T.BgPage #EBEBF0 on board tabs); change both together.    --}}
{{-- ================================================================= --}}
@php
    // ONE list, already ranked by the server (starred → nearest → live → fresh).
    // Admin curation shows as a ⭐ on the card instead of its own section, so the
    // feed stays a single scannable column.
    $abFeedRows = collect($abFeed ?? []);
    $abSummary  = $abDistrictSummary ?? null;
    $abBoards   = [
        'district' => ['rows' => collect($abDistrictBoard ?? []), 'location' => $abSummary['district'] ?? (auth()->user()->district ?? null)],
        'state'    => ['rows' => collect($abStateBoard ?? []),    'location' => auth()->user()->state ?? null],
    ];

    // Port of CrexUI.teamShortCode — compact codes (KP, PP) instead of long names.
    $abCode = function (string $raw): string {
        $name = trim($raw);
        if ($name === '') return '?';
        if (mb_strlen($name) <= 4 && $name === mb_strtoupper($name)) return $name;
        $words = preg_split('/[\s\-_]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) >= 2) {
            return implode('', array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 4)));
        }
        $w = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $words[0]));
        foreach (['palle','palli','pally','halli','nagaram','nagar','puram','palem','valasa','cherla','konda','gudem','peta','pet','wada','vada','giri','puri','pur','bad'] as $suf) {
            if (mb_strlen($w) > mb_strlen($suf) + 1 && str_ends_with($w, $suf)) {
                return mb_strtoupper(mb_substr($w, 0, 1) . mb_substr($suf, 0, 1));
            }
        }
        return mb_strtoupper(mb_substr($w, 0, 3));
    };

    // Port of playerColor — stable identity hue per name (JVM String.hashCode).
    $abColor = function (string $name): string {
        $palette = ['#2563EB', '#6D28D9', '#0D9488', '#15803D', '#B45309', '#334155', '#0E7490', '#7C2D12'];
        $h = 0;
        foreach (preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $h = (int) ((($h * 31) + mb_ord($ch)) % 4294967296);
            if ($h > 2147483647) $h -= 4294967296;
        }
        return $palette[abs($h) % count($palette)];
    };

    $abInitials = function (string $name): string {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        return mb_strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2))));
    };

    // A side "hasn't batted" when it holds no runs and no balls faced.
    $abYetToBat = function (string $score, string $overs): bool {
        $s = trim($score); $o = trim($overs);
        return ($s === '' || $s === '0' || $s === '0-0' || $s === '0/0')
            && ($o === '' || $o === '0' || str_starts_with($o, '0.0'));
    };

    // ONE ranked list arrives, holding everything visible — in-progress, upcoming AND
    // completed. The Live tab used to render all of it, so finished matches sat under a
    // "Matches near you" heading forever; they belong on the Finished tab, which was a
    // hardcoded empty state even though the data was already in hand (the app fixed the
    // same bug on its side — see MainScreen.kt's Finished tab).
    // Finished = completed_at stamped or a result line ("X won by 6 wickets") — matching
    // only "completed" left every cricket result on the Live tab. Live = in play only (the
    // app's isInPlay): not-yet-started matches belong on Scheduled, not under LIVE.
    $abIsDone = fn (array $m): bool => (bool) ($m['isFinished'] ?? in_array(strtolower((string) ($m['status'] ?? '')), ['completed', 'finished'], true));
    $abLiveRows     = $abFeedRows->filter(fn (array $m): bool => ($m['isLive'] ?? false) && ! $abIsDone($m))->values();
    $abFinishedRows = $abFeedRows->filter($abIsDone)->values();

    // The empty Live tab names the next real fixture (yours first, then open ones near
    // you) rather than leaving a blank — only matches still ahead of now.
    $abNext = collect(array_merge($abScheduledMine ?? [], $abScheduledOpen ?? []))
        ->map(function ($m) {
            try {
                $at = ! empty($m['scheduledAt']) ? \Illuminate\Support\Carbon::parse($m['scheduledAt']) : null;
            } catch (\Throwable) {
                $at = null;
            }
            return $at && $at->gte(now()->subMinutes(30)) ? $m + ['_at' => $at] : null;
        })
        ->filter()
        ->sortBy(fn ($m) => $m['_at']->getTimestamp())
        ->map(fn ($m) => [
            // "Mine" rows name the sides teamA/teamB; "Open near me" rows say team1/team2.
            'label' => trim((($m['teamA'] ?? $m['team1'] ?? '') ?: 'Team A') . ' vs ' . (($m['teamB'] ?? $m['team2'] ?? '') ?: 'Team B')),
            'when' => \App\Support\BusinessClock::local($m['_at'])->isToday()
                ? 'Today · ' . \App\Support\BusinessClock::local($m['_at'])->format('g:i A')
                : \App\Support\BusinessClock::local($m['_at'])->format('D, j M · g:i A'),
            'sport' => strtolower((string) ($m['sport'] ?? 'cricket')),
        ])
        ->first();

    // Port of SportFilterRow. Sport is a FILTER over the one list, not a destination —
    // it used to be three slots in the bottom bar (Cricket / Badminton / Football), which
    // made the board's top-level structure "which sport am I looking at", left five of the
    // eight sports the app scores with no way in at all, and switched by hiding the feed
    // behind a fabricated "No live X matches yet" line rather than filtering anything.
    //
    // [key on the wire, label a human reads]. The KEY is what the cards carry: table
    // tennis is "table_tennis", so filtering on the pretty label would match nothing and
    // the board would look empty for that sport.
    $abSports = [
        ['All', 'All'],
        ['cricket', 'Cricket'],
        ['football', 'Football'],
        ['basketball', 'Basketball'],
        ['volleyball', 'Volleyball'],
        ['kabaddi', 'Kabaddi'],
        ['tennis', 'Tennis'],
        ['table_tennis', 'Table Tennis'],
        ['badminton', 'Badminton'],
    ];

    // Hand-drawn sport glyphs, one stroke family (1.9px, round caps) so the chips read as
    // one set. The app leans on Material, which has no shuttlecock and makes tennis, table
    // tennis and badminton share a single racket; drawn here, each sport gets its own mark.
    $abSportSvg = function (string $key): string {
        $a = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"';
        $paths = [
            // stumps + ball
            'cricket' => '<path d="M8.4 20V7.2M12 20V7.2M15.6 20V7.2"/><path d="M7.2 6.6h9.6"/><circle cx="19.4" cy="16.8" r="2.1"/>',
            // ball with the classic pentagon panel
            'football' => '<circle cx="12" cy="12" r="8.6"/><path d="M12 7.4l3.5 2.5-1.3 4H9.8l-1.3-4z"/><path d="M12 7.4V3.4M15.5 9.9l3.7-1.2M14.2 13.9l2.3 3.2M9.8 13.9l-2.3 3.2M8.5 9.9L4.8 8.7"/>',
            'basketball' => '<circle cx="12" cy="12" r="8.6"/><path d="M12 3.4v17.2M3.4 12h17.2"/><path d="M6.1 5.7c2.6 2 4.2 4.1 4.2 6.3s-1.6 4.3-4.2 6.3M17.9 5.7c-2.6 2-4.2 4.1-4.2 6.3s1.6 4.3 4.2 6.3"/>',
            'volleyball' => '<circle cx="12" cy="12" r="8.6"/><path d="M11.3 3.5c-2.4 3.5-3.3 7.4-2.5 11.6"/><path d="M20.4 9.8c-4 .3-7.3 1.8-9.9 4.5-1.4 1.5-2.5 3.4-3.1 5.4"/><path d="M18.3 19c-.7-3.9-2.5-7-5.3-9.4-1.6-1.4-3.6-2.4-5.7-3"/>',
            // two players locked in a raid — the one sport here that is people, not a ball
            'kabaddi' => '<circle cx="7.2" cy="6.2" r="2.1"/><path d="M7.2 8.6v4.2M7.2 12.8l-2.2 4.6M7.2 12.8l2.2 4.6M7.6 10.3l4 1.3"/><circle cx="16.8" cy="6.2" r="2.1"/><path d="M16.8 8.6v4.2M16.8 12.8l2.2 4.6M16.8 12.8l-2.2 4.6M16.4 10.3l-4 1.3"/>',
            // strung racket, angled, with its ball
            'tennis' => '<ellipse cx="13.6" cy="8.8" rx="4" ry="5.1" transform="rotate(45 13.6 8.8)"/><path d="M11.2 6.4l4.8 4.8M16 6.4l-4.8 4.8"/><path d="M10.4 12L5.6 16.8"/><circle cx="18.6" cy="17.2" r="2"/>',
            // paddle (solid face, flared grip) so it never reads as the tennis racket
            'table_tennis' => '<g transform="rotate(-20 11 12)"><circle cx="10.4" cy="8.6" r="5.2"/><path d="M10.4 13.9v5.5M8.9 19.6h3"/></g><circle cx="18.8" cy="13.2" r="1.8"/>',
            // shuttlecock: cork + skirt
            'badminton' => '<circle cx="17.2" cy="17.2" r="2.3"/><path d="M15.6 15.6L5.2 10.2M15.6 15.6L10.2 5.2M5.2 10.2A4.6 4.6 0 0 1 10.2 5.2M14.3 14.3L8.6 8.6"/>',
        ];
        return isset($paths[$key]) ? '<svg ' . $a . ' aria-hidden="true">' . $paths[$key] . '</svg>' : '';
    };

    $abMedals = [
        1 => ['grad' => 'linear-gradient(180deg,#FFF3C0,#F3CB57,#CF9A1C)', 'rim' => '#FFEBA6', 'ink' => '#5A3F00'],
        2 => ['grad' => 'linear-gradient(180deg,#F6F9FD,#D4DBE6,#A5AFC0)', 'rim' => '#FFFFFF', 'ink' => '#374052'],
        3 => ['grad' => 'linear-gradient(180deg,#F6CFA3,#D99457,#A75F2B)', 'rim' => '#F8D8B4', 'ink' => '#4A2A0E'],
    ];
@endphp

<div class="mab" id="mab">
    {{-- Fixed app bar — lifts (shadow) as the list scrolls beneath it. --}}
    <div class="mab__bar" id="mabBar">
        {{-- Desktop only: the site header already carries search and the app link,
             so the page opens with its name, what's live, and its one action. --}}
        <div class="mab__dintro">
            <div class="mab__dwords">
                <nav class="mab__dcrumbs" aria-label="Breadcrumb"><a href="/">Haraan</a><span aria-hidden="true">/</span><a href="/gamehub">Pulse</a><span aria-hidden="true">/</span>ActionBoard</nav>
                <h1>ActionBoard</h1>
                <p>
                    @if ($abLiveRows->isNotEmpty())<span class="mab__dlive"><i></i>{{ $abLiveRows->count() }} live now</span>@endif
                    <span>Matches scored ball by ball by players on Haraan.</span>
                </p>
            </div>
            <div class="mab__dacts">
                <a class="mab__dboard" href="/gamehub/leaderboard">Leaderboard</a>
                <a class="mab__dstart" href="{{ route('site.gamehub.actionboard.create') }}">Start a match<i aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M13 6l6 6-6 6"></path></svg></i></a>
            </div>
        </div>
        <div class="mab__head">
            <span class="mab__logo" role="img" aria-label="Haraan"></span>
            <a class="mab__search" href="/search" aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><line x1="20" y1="20" x2="16.2" y2="16.2"></line></svg>
                <span>Search matches, players…</span>
            </a>
            {{-- App-only actions (join-by-code / create) are removed from the mobile
                 web bar; the one CTA left is "get the app". --}}
            <a class="mab__get" href="https://play.google.com/store/apps/details?id=com.haraan.app" target="_blank" rel="noopener" aria-label="Get the Haraan app on Google Play">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#60A5FA" d="M4.1 1.9 14.6 12 4.1 22.1c-.4-.2-.7-.7-.7-1.3V3.2c0-.6.3-1.1.7-1.3z"/>
                    <path fill="#3DDCFF" d="M17.7 9.1 5.6 2l9 8.7z"/>
                    <path fill="#FFC933" d="m14.6 12 3.1-2.9 3 1.7c1 .6 1 1.9 0 2.4l-3 1.7z"/>
                    <path fill="#FF5C6C" d="m14.6 12-9 10 12.1-7.1z"/>
                </svg>
                <span>Get the app</span>
            </a>
        </div>

        {{-- Live / Finished / Scheduled / District / State strip with the sliding indicator. --}}
        <div class="mab__tabs" role="tablist">
            <button class="mab__tab is-on" role="tab" data-i="0" onclick="mabTab(0)">
                <span class="mab__tabic">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                    <span class="mab__pulse"></span>
                </span>
                Live
            </button>
            <button class="mab__tab" role="tab" data-i="1" onclick="mabTab(1)">
                <span class="mab__tabic"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8z"></path></svg></span>
                Finished
            </button>
            <button class="mab__tab" role="tab" data-i="2" onclick="mabTab(2)">
                <span class="mab__tabic"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"></path></svg></span>
                Scheduled
            </button>
            <button class="mab__tab" role="tab" data-i="3" onclick="mabTab(3)">
                <span class="mab__tabic"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 11V3H7v4H3v14h8v-4h2v4h8V11h-4zM7 19H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm4 4H9v-2h2v2zm0-4H9V9h2v2zm0-4H9V5h2v2zm4 8h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm4 12h-2v-2h2v2zm0-4h-2v-2h2v2z"></path></svg></span>
                District
            </button>
            <button class="mab__tab" role="tab" data-i="4" onclick="mabTab(4)">
                <span class="mab__tabic"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 2 7v2h20V7L12 2zM4 11v6h3v-6H4zm6.5 0v6h3v-6h-3zM17 11v6h3v-6h-3zM2 21h20v-2H2v2z"></path></svg></span>
                State
            </button>
            <span class="mab__ind" id="mabInd"></span>
        </div>

        {{-- Sport chips (SportFilterRow). Shown on the two feeds only: District and
             State are XP boards the web ranks without a sport, and a chip row that
             filtered nothing there would be a dead control. --}}
        <div class="mab__sports" id="mabSports" role="tablist" aria-label="Sport">
            @foreach ($abSports as [$abKey, $abLabel])
                @php $abGlyph = $abSportSvg($abKey); @endphp
                <button class="mab__sport{{ $loop->first ? ' is-on' : '' }}" type="button"
                        role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        data-sport="{{ $abKey }}" data-label="{{ $abLabel }}"
                        onclick="mabSport('{{ $abKey }}', this)">
                    {!! $abGlyph !!}{{ $abLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mab__body">
        {{-- ── TAB 0: LIVE — in progress, and what is coming up ───────── --}}
        <div class="mab__panel is-on" id="mabPanel0">
            <div class="mab__ltitle" @if ($abLiveRows->isEmpty()) hidden @endif>
                <span class="mab__ldot"></span><span class="mab__ltxt">Matches near you</span><span class="mab__lsee">See all</span>
            </div>
            <div class="mab__group">
                @foreach ($abLiveRows as $m)
                    @include('site.partials.actionboard-match-card', ['m' => $m])
                @endforeach
            </div>
            {{-- The sport must not leak into the sentence: with "All" active the line
                 would otherwise read "No live All matches right now". --}}
            @include('site.partials.actionboard-empty', [
                'kind' => 'live',
                'title' => 'No live matches right now',
                'tpl' => 'No live {sport} matches right now',
                'next' => $abNext,
                'results' => $abFinishedRows->isNotEmpty(),
                'hidden' => $abLiveRows->isNotEmpty(),
            ])
        </div>

        {{-- ── TAB 1: FINISHED — results, from the SAME feed the Live tab reads ── --}}
        <div class="mab__panel" id="mabPanel1">
            <div class="mab__ltitle" @if ($abFinishedRows->isEmpty()) hidden @endif>
                <span class="mab__ldot"></span><span class="mab__ltxt">Recent results</span><span class="mab__lsee">See all</span>
            </div>
            <div class="mab__group">
                @foreach ($abFinishedRows as $m)
                    @include('site.partials.actionboard-match-card', ['m' => $m])
                @endforeach
            </div>
            @include('site.partials.actionboard-empty', [
                'kind' => 'finished',
                'title' => 'No finished matches yet',
                'tpl' => 'No finished {sport} matches yet',
                'hidden' => $abFinishedRows->isNotEmpty(),
            ])
        </div>

        @include('site.partials.actionboard-scheduled', ['mine' => $abScheduledMine ?? [], 'open' => $abScheduledOpen ?? []])

        {{-- ── TAB 3 / 4: DISTRICT & STATE boards ──────────────────────── --}}
        @foreach (['district' => 3, 'state' => 4] as $scopeKey => $tabIndex)
            @php $board = $abBoards[$scopeKey]; $rows = $board['rows']; @endphp
            <div class="mab__panel" id="mabPanel{{ $tabIndex }}">
                @if ($scopeKey === 'district' && $abSummary !== null)
                    {{-- District Home — the local-identity snapshot. --}}
                    <div class="mab__dcard">
                        <div class="mab__drow1">
                            <div class="mab__dwho">
                                <span class="mab__dk">YOUR DISTRICT</span>
                                <span class="mab__dname">{{ $abSummary['district'] }}</span>
                            </div>
                            @if (($abSummary['districtRank'] ?? null) !== null && !empty($abSummary['state']))
                                <div class="mab__drank">
                                    <b>#{{ $abSummary['districtRank'] }}</b>
                                    <span>in {{ $abSummary['state'] }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="mab__dtiles">
                            <div class="mab__dtile"><b style="color:#2563EB">{{ $abSummary['liveMatches'] }}</b><span>Live</span></div>
                            <div class="mab__dtile"><b>{{ $abSummary['players'] }}</b><span>Players</span></div>
                            <div class="mab__dtile"><b>{{ $abSummary['totalMatches'] }}</b><span>Matches</span></div>
                        </div>
                        @if (($abSummary['topBatter'] ?? null) !== null || ($abSummary['topBowler'] ?? null) !== null)
                            <div class="mab__dsep"></div>
                            @if (($abSummary['topBatter'] ?? null) !== null)
                                <div class="mab__dleader"><span>Top Batter</span><b>{{ $abSummary['topBatter']['name'] ?: $abSummary['topBatter']['player_id'] }}</b><i>{{ $abSummary['topBatter']['value'] }} runs</i></div>
                            @endif
                            @if (($abSummary['topBowler'] ?? null) !== null)
                                <div class="mab__dleader"><span>Top Bowler</span><b>{{ $abSummary['topBowler']['name'] ?: $abSummary['topBowler']['player_id'] }}</b><i>{{ $abSummary['topBowler']['value'] }} wkts</i></div>
                            @endif
                        @endif
                    </div>
                @endif

                @php
                    $top3 = $rows->filter(fn ($r) => ($r['rank'] ?? 99) <= 3)->sortBy('rank')->values();
                    $rest = $rows->filter(fn ($r) => ($r['rank'] ?? 0) > 3)->sortBy('rank')->values();
                    $boardTitle = $board['location']
                        ? ($scopeKey === 'state' ? $board['location'] : $board['location'] . ' District')
                        : ($scopeKey === 'state' ? 'State Board' : 'District Board');
                @endphp

                @if ($top3->isNotEmpty())
                    {{-- Podium — 2nd | 1st | 3rd on metallic pedestals. --}}
                    <div class="mab__podium">
                        <div class="mab__phead">
                            <div>
                                <b>{{ $boardTitle }}</b>
                                <span class="mab__pfresh"><i></i>Updated just now</span>
                            </div>
                            <span class="mab__pcount">{{ $rows->count() }} ranked {{ $rows->count() === 1 ? 'player' : 'players' }}</span>
                        </div>
                        <div class="mab__phair"></div>
                        <div class="mab__pslots">
                            @foreach ([2, 1, 3] as $rk)
                                @php $p = $top3->firstWhere('rank', $rk); @endphp
                                @if ($p === null)
                                    <div class="mab__pslot"></div>
                                @else
                                    @php $medal = $abMedals[$rk]; $isFirst = $rk === 1; @endphp
                                    <a class="mab__pslot" href="{{ !empty($p['playerId']) ? '/player/' . $p['playerId'] : '#' }}">
                                        <span class="mab__pcrown">
                                            @if ($isFirst)
                                                <svg viewBox="0 0 28 16" fill="#F3CB57"><path d="M0 16 1.7 6.7 7.6 9.6 14 .6l6.4 9 5.9-2.9L28 16z"></path></svg>
                                            @endif
                                        </span>
                                        <span class="mab__pav {{ $isFirst ? 'is-first' : '' }}" style="background: {{ $abColor($p['name']) }}; border-color: {{ $medal['rim'] }}">
                                            {{ $abInitials($p['name']) }}
                                            <span class="mab__pcoin" style="background: {{ $medal['grad'] }}; color: {{ $medal['ink'] }}">{{ $rk }}</span>
                                        </span>
                                        <span class="mab__pname {{ $isFirst ? 'is-first' : '' }}">{{ $p['name'] }}</span>
                                        <span class="mab__pxp {{ $isFirst ? 'is-first' : '' }}" data-count="{{ (int) ($p['xp'] ?? 0) }}">{{ $p['xp'] ?? 0 }}</span>
                                        <span class="mab__psub">{{ ($p['matches'] ?? 0) > 0 ? $p['matches'] . ' matches' : 'XP' }}</span>
                                        <span class="mab__pped {{ $isFirst ? 'is-first' : '' }}" style="background: {{ $medal['grad'] }}"><i style="color: {{ $medal['ink'] }}">{{ $rk }}</i></span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($rest->isNotEmpty())
                    <div class="mab__list">
                        @foreach ($rest as $ri => $p)
                            @if ($ri > 0)<div class="mab__gdiv"></div>@endif
                            <a class="mab__lrow" href="{{ !empty($p['playerId']) ? '/player/' . $p['playerId'] : '#' }}">
                                <span class="mab__lrank">{{ $p['rank'] }}</span>
                                <span class="mab__lav" style="background: {{ $abColor($p['name']) }}">{{ $abInitials($p['name']) }}</span>
                                <span class="mab__lwho">
                                    <b>{{ $p['name'] }}</b>
                                    <span>{{ $scopeKey === 'state' ? ($p['district'] ?? '') : ($p['state'] ?? '') }}</span>
                                </span>
                                <span class="mab__lxp"><b>{{ $p['xp'] ?? 0 }}</b><span>XP</span></span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($rows->isEmpty())
                    <div class="mab__empty">No ranked players in this {{ $scopeKey }} yet</div>
                @endif
            </div>
        @endforeach
    </div>

    @include('site.partials.mab-bottombar', ['active' => 'Matches'])
</div>
</div>

<style>
/* Page frame for the ActionBoard. On phones the page wears the app's own bar
   (.mab__bar), so the site header steps aside and the board runs edge to edge;
   on desktop the site header stays and .mab lays itself out at container width. */
@media (max-width: 720px) {
    header.topbar { display: none !important; }
}
main.container {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}
.actionboard-root-theme {
    background-color: #F5F5F5 !important;
    color: #1E293B !important;
    min-height: 100vh;
    overflow-x: clip; /* clip, not hidden: hidden would stop the sticky tab bar */
}
</style>

<style>
/* =============================================================================
   APP-PARITY ACTIONBOARD (.mab) — port of MainScreen.kt CrexMatchesScreen, on
   every screen size. The rules below are the phone; the min-width: 721px block at
   the end lays the same page out wide under the site header. Tokens track
   the app: LightBackground #F5F5F5, accent #2563EB, tabs idle #94A3B8, T.BgPage
   #EBEBF0 (District/State), card radius 14, bevel border #F3F6FA→#D9DFEA.
   ========================================================================== */
@media all {
    .actionboard-root-theme { background: #F5F5F5 !important; padding-bottom: 0; }
    .actionboard-root-theme:has(.mab.is-board) { background: #EBEBF0 !important; }

    .mab {
        /* The site's face (Pulse, venues, events) — Inter here made this tab read as a
           different product. Antialiased for crisp weights on high-DPI phones. */
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
        color: #0F172A;
        min-height: 100vh;
        padding-bottom: 108px; /* clears the floating bottom bar */
        overflow-x: clip;
        max-width: 100vw;
    }

    /* ── App bar (CrexHeaderSection) ─────────────────────────────────── */
    .mab__bar {
        position: sticky; top: 0; z-index: 60;
        background: #F5F5F5;
        padding: 12px 16px 0;
        transition: box-shadow .25s ease, background .25s ease;
    }
    .mab.is-board .mab__bar { background: #EBEBF0; }
    .mab__bar.is-lifted { box-shadow: 0 4px 14px rgba(15, 23, 42, .10); }
    .mab__head { display: flex; align-items: center; gap: 8px; padding: 8px 0 12px; min-width: 0; }
    /* Blue "H" mark only (no wordmark). The navy PNG is used as a mask over a blue
       fill, so the icon is a crisp brand-blue regardless of the source colour. */
    .mab__logo {
        width: 26px; height: 32px; flex: 0 0 auto; background-color: #2563EB;
        -webkit-mask: url("{{ asset('images/haraan-mark.png') }}") center / contain no-repeat;
        mask: url("{{ asset('images/haraan-mark.png') }}") center / contain no-repeat;
    }
    .mab__sp { flex: 1; min-width: 0; }
    /* Full-width search bar filling the gap between the logo and the app CTA. */
    .mab__search {
        flex: 1; min-width: 0; display: inline-flex; align-items: center; gap: 8px;
        height: 38px; padding: 0 14px; border-radius: 19px;
        background: #F1F5FA; border: 1px solid #E4EAF1; color: #94A0B0;
        text-decoration: none; font-size: 13px; overflow: hidden; white-space: nowrap;
    }
    .mab__search svg { width: 18px; height: 18px; flex: 0 0 auto; }
    .mab__search span { overflow: hidden; text-overflow: ellipsis; }
    .mab__search:hover { background: #E9EFF6; }
    .mab__ic {
        width: 38px; height: 38px; border-radius: 12px; background: #F1F5F9; border: 0;
        display: inline-flex; align-items: center; justify-content: center;
        color: #6B7280; cursor: pointer; flex: 0 0 auto; padding: 0; text-decoration: none;
    }
    .mab__ic svg { width: 18px; height: 18px; }
    .mab__get {
        height: 38px; border-radius: 22px; background: #111827; color: #fff;
        display: inline-flex; align-items: center; gap: 6px; padding: 0 14px;
        font-size: 12.5px; font-weight: 700; text-decoration: none; flex: 0 0 auto;
        white-space: nowrap;
    }
    .mab__get svg { width: 16px; height: 16px; }

    /* ── Tabs strip (CrexTabsSection) ────────────────────────────────── */
    .mab__tabs { position: relative; display: flex; padding-bottom: 0; }
    .mab__tab {
        flex: 1; background: none; border: 0; cursor: pointer;
        display: flex; flex-direction: column; align-items: center; gap: 5px;
        padding: 8px 0 10px; font-size: 12.5px; font-weight: 600; color: #94A3B8;
        transition: color .2s ease;
    }
    .mab__tab.is-on { color: #2563EB; font-weight: 700; }
    .mab__tabic { position: relative; width: 17px; height: 17px; }
    .mab__tabic svg { width: 17px; height: 17px; display: block; }
    .mab__pulse {
        position: absolute; top: -3px; right: -5px; width: 7px; height: 7px;
        border-radius: 50%; background: #E11D2A;
        animation: mabPulse 1.5s ease-in-out infinite;
    }
    @keyframes mabPulse { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }
    .mab__ind {
        position: absolute; bottom: 0; left: 0; width: 10%; height: 2.5px;
        background: #2563EB; border-radius: 2px 2px 0 0;
        transform: translateX(50%); /* centred in slot 0 at rest: (20% − 10%)/2 = 5% of track = 50% of self */
        transition: transform .3s cubic-bezier(.2, 0, 0, 1);
    }
    .mab__tabs::after {
        content: ""; position: absolute; left: -16px; right: -16px; bottom: -1px;
        height: 1px; background: #E2E8F0;
    }

    /* ── Sport chips (SportFilterRow) ────────────────────────────────── */
    .mab__sports {
        display: flex; align-items: center; gap: 9px;
        margin: 0 -16px; padding: 10px 16px 8px;
        overflow-x: auto; -webkit-overflow-scrolling: touch;
        scrollbar-width: none; -ms-overflow-style: none;
        /* The Android edge-stretch has no orientation, so a horizontal strip inside a
           vertical page bounces the whole board sideways; contain it here. */
        overscroll-behavior-x: contain;
    }
    .mab__sports::-webkit-scrollbar { display: none; }
    .mab__sport {
        flex: 0 0 auto; display: inline-flex; align-items: center; gap: 6px;
        height: 34px; padding: 0 16px; border-radius: 50px; cursor: pointer;
        background: #fff; border: 1px solid #E7ECF3; color: #0F172A;
        font: inherit; font-size: 13px; font-weight: 600; white-space: nowrap;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
        transition: background .18s ease, color .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .mab__sport svg { width: 16px; height: 16px; flex: 0 0 auto; color: #2563EB; transition: color .18s ease; }
    /* Selected chip carries the blue glow the app's chips lift with. */
    .mab__sport.is-on { background: #2563EB; border-color: transparent; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .38); }
    .mab__sport.is-on svg { color: #fff; }
    .mab.is-board .mab__sports { display: none; }

    /* `hidden` must actually hide: every one of these carries a `display` of its own,
       which outranks the UA sheet's [hidden] { display: none } and leaves the element
       on screen. Filtering the feed depends on this rule. */
    .mab__sports[hidden], .mab__sport[hidden], .mab__match[hidden],
    .mab__ltitle[hidden], .mab__empty[hidden], .mab__group[hidden] { display: none !important; }

    /* ── Panels ──────────────────────────────────────────────────────── */
    .mab__body { padding: 4px 16px 0; }
    .mab__panel { display: none; }
    .mab__panel.is-on { display: block; }
    .mab__empty { text-align: center; color: #94A3B8; font-size: 13px; padding: 40px 0; }

    /* ── Drawn empty state (Live / Finished) — same piece as the app's BoardEmpty ── */
    .mab__nil {
        display: flex; flex-direction: column; align-items: center; margin: 14px 16px 0; padding: 12px 12px 18px;
        background: #fff; border: 1px solid #E6ECF4; border-radius: 22px;
        box-shadow: 0 1px 0 #fff inset, 0 10px 24px -16px rgba(15, 23, 42, .28), 0 1px 2px rgba(15, 23, 42, .05);
        color: #0F172A;
    }
    .mab__nil-art { display: block; width: 100%; max-width: 360px; height: auto; border-radius: 16px; }
    .mab__nil-title { display: block; margin-top: 14px; font-size: 16px; font-weight: 800; letter-spacing: -.015em; color: #0F172A; text-wrap: balance; }
    .mab__nil-sub { margin: 4px auto 0; max-width: 270px; font-size: 13px; line-height: 1.45; color: #64748B; text-wrap: balance; }
    .mab__nil-next {
        display: flex; align-items: center; gap: 8px; width: 100%; max-width: 320px; margin-top: 12px; padding: 10px 10px 10px 12px;
        border: 1px solid #DCE6F8; border-radius: 14px; background: #F5F8FF; color: #0F172A; text-align: left; font: inherit; cursor: pointer;
        -webkit-tap-highlight-color: transparent; transition: transform .09s ease;
    }
    .mab__nil-next[hidden] { display: none; }
    .mab__nil-next:active { transform: translateY(1px) scale(.99); }
    .mab__nil-next-k { flex: none; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #2563EB; }
    .mab__nil-next-v { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13.5px; font-weight: 800; }
    .mab__nil-next-w { flex: none; font-size: 12px; font-weight: 600; color: #64748B; }
    .mab__nil-next svg { flex: none; color: #94A3B8; }
    .mab__nil-actions { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 16px; }
    /* The one action, blue with a lip a press closes (same key as Book / Checkout). */
    .mab__nil-go {
        display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 18px 0 6px; border-radius: 999px;
        background: #2563EB; color: #fff !important; font-size: 14px; font-weight: 800; text-decoration: none;
        box-shadow: 0 1px 0 rgba(255, 255, 255, .25) inset, 0 2px 0 #1D4ED8, 0 8px 16px -8px rgba(37, 99, 235, .75);
        transition: transform .08s, box-shadow .08s; -webkit-tap-highlight-color: transparent;
    }
    .mab__nil-go i { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%; background: #fff; color: #2563EB; }
    .mab__nil-go:active { transform: translateY(2px); box-shadow: 0 0 0 #1D4ED8, 0 2px 4px -2px rgba(37, 99, 235, .6); }
    .mab__nil-alt { border: 0; background: none; padding: 8px 2px; font: inherit; font-size: 13.5px; font-weight: 700; color: #1E40AF; cursor: pointer; }
    .mab__nil-alt:active { opacity: .6; }
    /* Quiet life in the drawing: the board's dashes wait, the ball settles. */
    @media (prefers-reduced-motion: no-preference) {
        .mab__nil-dash { animation: mab-nil-wait 2.4s ease-in-out infinite; }
        .mab__nil-ball { transform-box: fill-box; transform-origin: 50% 100%; animation: mab-nil-rock 3.2s ease-in-out infinite; }
        .mab__nil-pencil { animation: mab-nil-pencil 3.6s ease-in-out infinite; }
        @keyframes mab-nil-wait { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
        @keyframes mab-nil-rock { 0%, 100% { transform: rotate(0); } 30% { transform: translateX(1.5px) rotate(14deg); } 60% { transform: translateX(-.5px) rotate(-5deg); } }
        @keyframes mab-nil-pencil { 0%, 100% { translate: 0 0; } 50% { translate: 0 -2px; } }
    }

    /* League title (CrexLeagueTitle) */
    .mab__ltitle { display: flex; align-items: center; padding: 10px 0; }
    .mab__ldot { width: 8px; height: 8px; border-radius: 50%; background: #2563EB; margin-right: 9px; }
    .mab__ltxt { flex: 1; font-size: 15px; font-weight: 800; letter-spacing: -.4px; color: #0F172A; }
    .mab__lsee { font-size: 12px; font-weight: 600; color: #2563EB; }

    /* Match group — a plain stack; each match is its own separate card (with a gap)
       rather than rows merged into one surface. */
    .mab__group {
        background: transparent; border: 0; box-shadow: none; border-radius: 0;
        margin-bottom: 8px; overflow: visible;
        display: flex; flex-direction: column; gap: 10px;
    }
    .mab__group .mab__gdiv { display: none; } /* separate cards → no inter-row hairline */
    .mab__gdiv { height: 1px; background: #EEF0F4; margin: 0 14px; }

    /* Match card (MatchLiveContent) */
    .mab__match {
        display: block; text-decoration: none; color: inherit;
        background: #fff; border-radius: 14px;
        border: 1px solid #E2E8F0;
        box-shadow: 0 4px 12px rgba(15, 23, 42, .06), 0 1px 2px rgba(15, 23, 42, .06);
        overflow: hidden;
    }
    .mab__match:active { transform: scale(.98); transition: transform .12s ease; }
    .mab__mctx { display: flex; align-items: center; gap: 6px; padding: 9px 14px; min-width: 0; }
    .mab__beacon {
        width: 10px; height: 10px; border-radius: 50%; background: rgba(225, 29, 42, .2);
        display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
    }
    .mab__beacon span { width: 6px; height: 6px; border-radius: 50%; background: #E11D2A; }
    .mab__mlive { font-size: 10px; font-weight: 800; letter-spacing: .8px; color: #E11D2A; }
    .mab__msched { font-size: 10px; font-weight: 700; letter-spacing: .6px; color: #94A3B8; }
    .mab__mdot { width: 3px; height: 3px; border-radius: 50%; background: #CBD5E1; flex: 0 0 auto; }
    .mab__mcomp { font-size: 10px; font-weight: 700; letter-spacing: .6px; color: #2563EB; white-space: nowrap; }
    .mab__mloc {
        display: inline-flex; align-items: center; gap: 3px; min-width: 0; flex: 1;
        font-size: 11px; color: #94A3B8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .mab__mloc svg { width: 12px; height: 12px; flex: 0 0 auto; }
    /* Measured distance — quieter than the place name it follows. */
    .mab__mkm { font-size: 11px; color: #94A3B8; white-space: nowrap; flex: 0 0 auto; }
    /* Admin-featured star. Amber reads as "picked", not as a live/status colour. */
    .mab__star { display: inline-flex; align-items: center; color: #F59E0B; flex: 0 0 auto; }
    .mab__star svg { width: 13px; height: 13px; }
    .mab__you {
        display: inline-flex; align-items: center; gap: 2px; margin-left: auto;
        background: #2563EB; color: #fff; border-radius: 6px; padding: 2px 6px;
        font-size: 9px; font-weight: 800; letter-spacing: .6px; flex: 0 0 auto;
    }
    .mab__you svg { width: 11px; height: 11px; }
    .mab__hair { height: 1px; background: #F0F2F5; margin: 0 14px; }
    .mab__mbody { display: flex; align-items: center; padding: 14px; }
    .mab__mteams { flex: 1; display: flex; flex-direction: column; gap: 14px; min-width: 0; }
    .mab__trow { display: flex; align-items: center; min-width: 0; }
    .mab__tlogo {
        width: 30px; height: 30px; border-radius: 50%; flex: 0 0 auto;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-size: 10px; font-weight: 800;
        border: 1px solid rgba(15, 23, 42, .1); margin-right: 10px;
    }
    .mab__tlogo { overflow: hidden; }
    .mab__tlogo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .mab__tlogo.is-dim { opacity: .4; }
    /* A real crest image must stay clearly visible — the .4 dim is only for monogram tiles. */
    .mab__tlogo.is-dim.has-img { opacity: 1; }
    .mab__tname { flex: 1; font-size: 16px; font-weight: 600; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mab__tname.is-bat { font-weight: 800; }
    .mab__tname.is-dim, .mab__tscore.is-dim { color: #B0BAC8; }
    .mab__tscore {
        display: flex; flex-direction: column; align-items: flex-end;
        font-size: 18px; letter-spacing: -.5px; color: #0F172A;
        font-variant-numeric: tabular-nums;
    }
    .mab__tscore.is-bat { font-weight: 800; }
    .mab__tscore small { font-size: 11px; font-weight: 400; color: #B0BAC8; }
    .mab__tyet { font-size: 13px; font-weight: 500; color: #B0BAC8; }
    .mab__vdiv { width: 1px; height: 60px; background: #F0F2F5; margin: 0 14px; flex: 0 0 auto; }
    .mab__mstat { width: 90px; flex: 0 0 auto; text-align: center; }
    .mab__mstat b { display: block; font-size: 13px; font-weight: 800; color: #1D4ED8; }
    .mab__mstat span { display: block; margin-top: 3px; font-size: 11px; line-height: 15px; color: #94A3B8; }

    /* ── Match card, sharpened (2026-10-21) ─────────────────────────────────
       Same layout as before (codes, score column, status column on the right).
       Sharpness comes from edges and contrast, not new parts: the Pulse cards'
       fine blue edge, a tight contact shadow instead of a soft blur, crisper
       hairlines, and dimmed text that is still legible (#B0BAC8 read as blur).
       A press sinks the card 1px and closes its shadow. */
    .mab__group { gap: 12px; }
    .mab__match {
        border: 1.5px solid #2563EB; border-radius: 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .08), 0 8px 18px -12px rgba(37, 99, 235, .5);
        transition: transform .1s ease, box-shadow .12s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .mab__match:active { transform: translateY(1px); box-shadow: 0 1px 1px rgba(15, 23, 42, .1); transition: transform .08s ease, box-shadow .08s ease; }
    .mab__match.is-done { border-color: #BFD0F3; box-shadow: 0 1px 2px rgba(15, 23, 42, .07), 0 6px 14px -12px rgba(15, 23, 42, .35); }
    .mab__mctx { padding: 10px 14px; }
    .mab__mlive { font-weight: 800; letter-spacing: .08em; }
    .mab__msched { font-weight: 800; letter-spacing: .08em; color: #64748B; }
    .mab__mcomp { font-weight: 800; letter-spacing: .06em; }
    .mab__mloc, .mab__mkm { color: #8592A6; }
    .mab__hair { background: #E8EDF4; }
    .mab__tlogo { box-shadow: 0 1px 2px rgba(15, 23, 42, .1); }
    .mab__tlogo.is-dim { opacity: .55; }
    .mab__tname { letter-spacing: -.01em; }
    .mab__tname.is-dim, .mab__tscore.is-dim { color: #8592A6; }
    .mab__tscore { font-size: 19px; letter-spacing: -.02em; }
    .mab__tscore.is-bat { font-weight: 800; color: #0B1220; }
    .mab__tscore small { font-size: 11px; font-weight: 600; letter-spacing: 0; color: #94A3B8; }
    .mab__tyet { font-weight: 600; color: #94A3B8; }
    .mab__vdiv { background: #E8EDF4; }
    .mab__mstat b { font-size: 12.5px; letter-spacing: .02em; }
    .mab__mstat span { color: #64748B; font-weight: 500; }
    /* A finished match's right column carries its result line — ink, not "live" blue. */
    .mab__match.is-done .mab__mstat b {
        color: #0F172A; font-size: 12px; line-height: 1.3; letter-spacing: 0;
        display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }

    /* ── District Home card (DistrictHomeCard) ───────────────────────── */
    .mab__dcard {
        background: #fff; border-radius: 16px; padding: 16px; margin: 8px 0;
        border: 1px solid #D9DFEA; border-top-color: #F3F6FA;
        box-shadow: 0 8px 16px rgba(15, 23, 42, .06), 0 1px 2px rgba(15, 23, 42, .08);
    }
    .mab__drow1 { display: flex; align-items: center; }
    .mab__dwho { flex: 1; min-width: 0; }
    .mab__dk { display: block; font-size: 11px; font-weight: 700; letter-spacing: .8px; color: #2563EB; }
    .mab__dname { display: block; margin-top: 2px; font-size: 22px; font-weight: 800; color: #0A0A0A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mab__drank { border-radius: 12px; background: rgba(37, 99, 235, .08); padding: 8px 14px; text-align: center; flex: 0 0 auto; }
    .mab__drank b { display: block; font-size: 20px; font-weight: 800; color: #2563EB; }
    .mab__drank span { display: block; font-size: 10px; font-weight: 500; color: #9A9AA8; }
    .mab__dtiles { display: flex; gap: 10px; margin-top: 14px; }
    .mab__dtile { flex: 1; border-radius: 12px; background: #F5F6FA; padding: 12px 0; text-align: center; }
    .mab__dtile b { display: block; font-size: 20px; font-weight: 800; color: #0A0A0A; }
    .mab__dtile span { display: block; margin-top: 2px; font-size: 11px; font-weight: 500; color: #9A9AA8; }
    .mab__dsep { height: 1px; background: #EEF0F4; margin: 14px 0 12px; }
    .mab__dleader { display: flex; align-items: center; margin-top: 10px; }
    .mab__dleader:first-of-type { margin-top: 0; }
    .mab__dleader span { width: 84px; flex: 0 0 auto; font-size: 12px; font-weight: 600; color: #9A9AA8; }
    .mab__dleader b { flex: 1; min-width: 0; font-size: 14px; font-weight: 700; color: #0A0A0A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mab__dleader i { font-style: normal; font-size: 13px; font-weight: 600; color: #2563EB; }

    /* ── Podium (LeaderboardPodium) ──────────────────────────────────── */
    .mab__podium {
        background: #fff; border-radius: 20px; margin: 12px 0 10px;
        box-shadow: 0 6px 14px rgba(0, 0, 0, .07);
        overflow: hidden;
    }
    .mab__phead { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px 10px; }
    .mab__phead b { display: block; font-size: 13px; font-weight: 700; letter-spacing: -.2px; color: #0A0A0A; }
    .mab__pfresh { display: inline-flex; align-items: center; gap: 4px; margin-top: 3px; font-size: 9.5px; font-weight: 500; color: #9A9AA8; }
    .mab__pfresh i { width: 5px; height: 5px; border-radius: 50%; background: #2563EB; }
    .mab__pcount { font-size: 10.5px; font-weight: 500; color: #9A9AA8; }
    .mab__phair { height: 1px; background: #F2F2F5; }
    .mab__pslots { display: flex; align-items: flex-end; padding: 20px 8px 0; }
    .mab__pslot { flex: 1; display: flex; flex-direction: column; align-items: center; text-decoration: none; color: inherit; min-width: 0; }
    .mab__pcrown { height: 18px; display: flex; align-items: center; margin-bottom: 4px; }
    .mab__pcrown svg { width: 28px; height: 16px; }
    .mab__pav {
        position: relative; width: 48px; height: 48px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 13px; font-weight: 800;
        border: 2.5px solid; box-shadow: 0 4px 10px rgba(0, 0, 0, .16);
    }
    .mab__pav.is-first { width: 62px; height: 62px; font-size: 17px; border-width: 3px; }
    .mab__pcoin {
        position: absolute; right: -2px; bottom: -2px; width: 19px; height: 19px;
        border-radius: 50%; border: 1.5px solid #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 9.5px; font-weight: 900;
    }
    .mab__pav.is-first .mab__pcoin { width: 22px; height: 22px; font-size: 11px; }
    .mab__pname {
        margin-top: 7px; max-width: 100%; padding: 0 4px;
        font-size: 11px; font-weight: 600; letter-spacing: -.2px; color: #0A0A0A;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center;
    }
    .mab__pname.is-first { font-size: 12.5px; font-weight: 700; }
    .mab__pxp { margin-top: 1px; font-size: 20px; font-weight: 800; letter-spacing: -1.5px; color: #0A0A0A; font-variant-numeric: tabular-nums; }
    .mab__pxp.is-first { font-size: 27px; }
    .mab__psub { margin-top: 3px; font-size: 10px; font-weight: 500; color: #9A9AA8; }
    .mab__pped {
        width: 100%; margin-top: 10px; height: 48px; border-radius: 10px 10px 0 0;
        display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;
    }
    .mab__pped::before {
        content: ""; position: absolute; inset: 0 0 auto; height: 46px;
        background: linear-gradient(180deg, rgba(255, 255, 255, .45), rgba(255, 255, 255, 0));
    }
    .mab__pslot:nth-child(1) .mab__pped { height: 64px; }
    .mab__pped.is-first { height: 90px; }
    .mab__pped i { font-style: normal; font-size: 30px; font-weight: 900; letter-spacing: -2px; opacity: .5; position: relative; }
    .mab__pped.is-first i { font-size: 40px; }

    /* ── List group (LeaderboardListGroup / LeaderboardRowContent) ───── */
    .mab__list {
        background: #fff; border-radius: 16px; margin-bottom: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
        overflow: hidden;
    }
    .mab__list .mab__gdiv { margin: 0 16px; background: #EBEBEF; }
    .mab__lrow { display: flex; align-items: center; padding: 13px 12px; text-decoration: none; color: inherit; }
    .mab__lrank { width: 24px; flex: 0 0 auto; text-align: center; font-size: 13px; font-weight: 700; color: #B0B0BE; font-variant-numeric: tabular-nums; }
    .mab__lav {
        width: 42px; height: 42px; border-radius: 50%; margin: 0 12px; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 12.5px; font-weight: 700;
        border: 2px solid rgba(37, 99, 235, .16); box-shadow: 0 2px 6px rgba(0, 0, 0, .12);
    }
    .mab__lwho { flex: 1; min-width: 0; }
    .mab__lwho b { display: block; font-size: 14px; font-weight: 700; letter-spacing: -.2px; color: #0A0A0A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mab__lwho span { display: block; margin-top: 2px; font-size: 11px; color: #9A9AA8; }
    .mab__lxp { text-align: right; flex: 0 0 auto; }
    .mab__lxp b { display: block; font-size: 18px; font-weight: 800; letter-spacing: -.5px; color: #0A0A0A; font-variant-numeric: tabular-nums; }
    .mab__lxp span { display: block; margin-top: 1px; font-size: 8.5px; font-weight: 600; letter-spacing: .5px; color: #9A9AA8; }

}

/* The intro row exists for desktop only. */
.mab__dintro { display: none; }

/* ── Desktop: the same board, laid out wide ────────────────────────────────
   The site header stays (it carries search and the app link, so the app bar's
   head row steps aside), the tabs and chips sit in a sticky band at container
   width, and the cards flow into a grid instead of one phone column. Every
   card, empty state, podium and list is the phone's own piece. */
@media (min-width: 721px) {
    .mab { min-height: 0; max-width: none; padding-bottom: 56px; }
    .mab__head, .mab__nav { display: none !important; }

    .mab__bar {
        top: var(--mab-top, 0px); padding: 0;
        background: rgba(245, 245, 245, .94); backdrop-filter: saturate(1.4) blur(10px);
    }
    .mab.is-board .mab__bar { background: rgba(235, 235, 240, .94); }
    .mab__bar > *, .mab__body { width: min(1280px, calc(100% - 32px)); margin-left: auto; margin-right: auto; }

    .mab__dintro { display: flex; align-items: flex-end; gap: 24px; padding: 26px 0 18px; }
    .mab__dwords { flex: 1; min-width: 0; }
    .mab__dcrumbs { display: flex; gap: 8px; font-size: 13px; font-weight: 600; color: #64748B; }
    .mab__dcrumbs a { color: #64748B; text-decoration: none; }
    .mab__dcrumbs a:hover { color: #0F172A; }
    .mab__dcrumbs span { color: #CBD5E1; }
    .mab__dintro h1 { margin: 8px 0 0; font-size: clamp(30px, 2.6vw, 40px); line-height: 1.1; font-weight: 800; letter-spacing: -.032em; color: #0F172A; }
    .mab__dintro p { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 8px 0 0; font-size: 15px; color: #64748B; }
    .mab__dlive { display: inline-flex; align-items: center; gap: 7px; font-weight: 700; color: #DC2626; }
    .mab__dlive i { width: 7px; height: 7px; border-radius: 50%; background: #E11D2A; animation: mabPulse 1.5s ease-in-out infinite; }
    .mab__dlive + span::before { content: ""; display: inline-block; width: 3px; height: 3px; margin: 0 10px 3px 0; border-radius: 50%; background: #CBD5E1; }
    .mab__dacts { flex: none; display: flex; align-items: center; gap: 18px; }
    .mab__dboard { font-size: 14px; font-weight: 700; color: #2563EB; text-decoration: none; }
    .mab__dboard:hover { text-decoration: underline; }
    .mab__dstart {
        display: inline-flex; align-items: center; gap: 9px; height: 44px; padding: 0 5px 0 18px; border-radius: 999px;
        background: #2563EB; color: #fff; font-size: 14.5px; font-weight: 800; text-decoration: none;
        box-shadow: 0 1px 0 rgba(255, 255, 255, .25) inset, 0 2px 0 #1D4ED8, 0 8px 16px -8px rgba(37, 99, 235, .8);
        transition: transform .08s, box-shadow .08s;
    }
    .mab__dstart i { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: #fff; color: #2563EB; transition: transform .22s cubic-bezier(.2, .9, .3, 1.2); }
    .mab__dstart:hover i { transform: translateX(3px); }
    .mab__dstart:active { transform: translateY(2px); box-shadow: 0 0 0 #1D4ED8; }

    /* Tabs: the phone's five, side by side with their icons, left-aligned. The
       indicator math assumes equal slots, so the strip keeps equal widths. */
    .mab__tabs { width: min(660px, calc(100% - 32px)); margin-left: calc((100% - min(1280px, 100% - 32px)) / 2); margin-right: auto; }
    .mab__tabs::after { left: -100vw; right: -100vw; }
    .mab__tab { flex-direction: row; justify-content: center; gap: 8px; padding: 13px 0 14px; font-size: 14px; }
    .mab__tab:hover:not(.is-on) { color: #475569; }
    .mab__sports { margin: 0 auto; padding: 14px 0; gap: 10px; }
    .mab__sport { height: 38px; padding: 0 18px; font-size: 13.5px; }
    .mab__sport:hover:not(.is-on) { border-color: #C7D2E3; }

    /* Cards flow into columns. */
    .mab__body { padding: 8px 0 0; }
    .mab__ltitle { padding: 14px 0 12px; }
    .mab__ltxt { font-size: 18px; letter-spacing: -.02em; }
    .mab__lsee { display: none; } /* the whole list is already on screen */
    .mab__group { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 18px; margin-bottom: 18px; }
    .mab__match { transition: transform .16s cubic-bezier(.2, .7, .3, 1), box-shadow .16s; }
    .mab__match:hover { transform: translateY(-3px); box-shadow: 0 1px 2px rgba(15, 23, 42, .08), 0 16px 28px -16px rgba(37, 99, 235, .55); }
    .mabs__card { transition: transform .16s cubic-bezier(.2, .7, .3, 1); }
    .mabs__card:hover { transform: translateY(-2px); }
    .mab__nil { max-width: 620px; margin: 24px auto 0; padding: 18px 18px 24px; }
    .mabs__sub { max-width: 420px; }

    /* District / State boards: a reading column, centred. */
    .mab__dcard, .mab__podium, .mab__list, .mab__panel > .mab__empty { max-width: 760px; margin-left: auto; margin-right: auto; }
    .mab__dcard { margin-top: 16px; }
}
@media (min-width: 721px) and (prefers-reduced-motion: reduce) {
    .mab__match, .mabs__card, .mab__dstart i { transition: none; }
    .mab__dlive i { animation: none; }
}
</style>

<script>
(function () {
    var mab = document.getElementById('mab');
    if (!mab) return;

    var currentTab = 0;
    var currentSport = 'All';       // wire key of the active chip
    var currentSportLabel = 'All';  // what a human reads, for the empty line

    // App bar lifts (frost + elevation) once content scrolls beneath it.
    var bar = document.getElementById('mabBar');
    window.addEventListener('scroll', function () {
        bar.classList.toggle('is-lifted', window.scrollY > 8);
    }, { passive: true });

    // Desktop: the band sticks under the site header, and its intro row scrolls
    // away first — a negative offset of the intro's height leaves the tabs and
    // chips pinned. Both heights change with the viewport, so they're measured.
    var topbar = document.querySelector('.topbar');
    var intro = bar.querySelector('.mab__dintro');
    var setTop = function () {
        var shown = intro && getComputedStyle(intro).display !== 'none';
        var head = topbar && getComputedStyle(topbar).display !== 'none' ? topbar.offsetHeight : 0;
        mab.style.setProperty('--mab-top', shown ? (head - intro.offsetHeight) + 'px' : '0px');
    };
    setTop();
    window.addEventListener('resize', setTop, { passive: true });

    // Sliding tab indicator — emphasized easing lives in the CSS transition.
    window.mabTab = function (i) {
        currentTab = i;
        mab.querySelectorAll('.mab__tab').forEach(function (t) {
            t.classList.toggle('is-on', +t.dataset.i === i);
        });
        for (var p = 0; p < 5; p++) {
            document.getElementById('mabPanel' + p).classList.toggle('is-on', p === i);
        }
        // Indicator is 10% wide; each slot 20% → offset = slot*i + (slot-ind)/2.
        var ind = document.getElementById('mabInd');
        ind.style.transform = 'translateX(' + (i * 200 + 50) + '%)';
        // District/State sit on the app's cooler board background (T.BgPage).
        mab.classList.toggle('is-board', i >= 3);
        if (i >= 3) mabCountUp();
        window.scrollTo({ top: 0 });
    };

    // Sport chip switch. This used to hide the whole feed and print "No live X matches
    // yet" for anything that wasn't Cricket — the board never filtered, it just pretended
    // the other sports had nothing in them. Now the rows carry their sport and the chips
    // filter what is actually there.
    window.mabSport = function (key, btn) {
        currentSport = key;
        currentSportLabel = (btn && btn.dataset.label) || key;
        mab.querySelectorAll('.mab__sport').forEach(function (b) {
            var on = b === btn;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        mabFilter();
        // Keep the chosen chip in view: the strip scrolls, so one picked at its far end
        // would otherwise sit half off-screen once the list underneath redraws.
        if (btn && btn.scrollIntoView) {
            btn.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        }
    };

    // Show the cards that match the chip, and let each panel's own empty line speak when
    // none do — one filter over the Live and Finished feeds, which share a card partial.
    function mabFilter() {
        ['mabPanel0', 'mabPanel1', 'mabPanel2'].forEach(function (id) {
            var panel = document.getElementById(id);
            if (!panel) return;
            var shown = 0;
            panel.querySelectorAll('[data-sport]:not(.mab__sport)').forEach(function (card) {
                var ok = currentSport === 'All' || card.dataset.sport === currentSport;
                card.hidden = !ok;
                if (ok) shown++;
            });
            var title = panel.querySelector('.mab__ltitle');
            if (title) title.hidden = shown === 0;
            var empty = panel.querySelector('.mab__empty');
            if (empty) {
                empty.hidden = shown > 0;
                var line = currentSport === 'All'
                    ? empty.dataset.all
                    : empty.dataset.tpl.replace('{sport}', currentSportLabel);
                // The drawn empty state keeps its art and actions; only the sentence changes.
                var t = empty.querySelector('.mab__nil-title');
                if (t) t.textContent = line; else empty.textContent = line;
                // "Next up" only when it's the sport being looked at.
                var nx = empty.querySelector('[data-next-sport]');
                if (nx) nx.hidden = currentSport !== 'All' && nx.dataset.nextSport !== currentSport;
            }
        });
    }

    // Join a private match by its share code (the code itself is the grant).
    window.mabJoinByCode = function () {
        var code = prompt('Enter the match share code');
        if (!code || !code.trim()) return;
        fetch('/api/live-matches/code/' + encodeURIComponent(code.trim().toUpperCase()))
            .then(function (r) { if (!r.ok) throw 0; return r.json(); })
            .then(function (j) {
                var id = j.id || (j.data && j.data.id);
                if (id) window.location = '/gamehub/actionboard/match/' + id;
                else throw 0;
            })
            .catch(function () { alert('No match found for that code.'); });
    };

    // Count-up the podium headline scores so the board feels alive (PodiumSlot).
    var counted = false;
    function mabCountUp() {
        if (counted) return;
        counted = true;
        mab.querySelectorAll('.mab__pxp[data-count]').forEach(function (el) {
            var target = +el.dataset.count || 0;
            var t0 = null;
            function step(ts) {
                if (t0 === null) t0 = ts;
                var k = Math.min((ts - t0) / 850, 1);
                el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3)));
                if (k < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }
})();

// Location-wise sorting: grab one precise device fix so "Matches near you" ranks by
// real distance. Ask once (never nag), store a rounded lat/lng cookie, then reload so
// the server re-sorts. If denied/unavailable, the server falls back to the chosen
// city (geocoded via Google Maps) or district/state names.
(function () {
    try {
        if (document.cookie.indexOf('hb_geo=') !== -1) return;
        if (localStorage.getItem('hb_geo_asked') === '1') return;
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(function (pos) {
            localStorage.setItem('hb_geo_asked', '1');
            var lat = pos.coords.latitude.toFixed(3), lng = pos.coords.longitude.toFixed(3);
            document.cookie = 'hb_geo=' + lat + ',' + lng + ';path=/;max-age=86400;SameSite=Lax';
            location.reload();
        }, function () {
            localStorage.setItem('hb_geo_asked', '1');
        }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 });
    } catch (e) {}
})();
</script>
@endsection
