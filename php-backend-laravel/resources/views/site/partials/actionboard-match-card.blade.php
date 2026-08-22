{{--
    One match card in the ActionBoard feed (the app's LiveFeedGroup row). Shared by the
    Live and Finished tabs so both wear the same card — and so a fix lands on both.

    Expects: $m (a row from PublicWebController::actionBoard's feed) plus the page's
    $abCode / $abColor / $abYetToBat closures. `data-sport` is what the sport chips
    filter on; it carries the wire key ("table_tennis"), never the pretty label.
--}}
@php
    // Batting side on top (LiveFeedGroup) — top row is always the batting one.
    $swap = ($m['battingTeam'] ?? 1) === 2;
    $t1 = $swap ? $m['team2'] : $m['team1'];  $t2 = $swap ? $m['team1'] : $m['team2'];
    $s1 = $swap ? $m['score2'] : $m['score1']; $s2 = $swap ? $m['score1'] : $m['score2'];
    $o1 = $swap ? $m['overs2'] : $m['overs1']; $o2 = $swap ? $m['overs1'] : $m['overs2'];
    $c1 = $abCode($t1); $c2 = $abCode($t2);
    $icon1 = hrn_team_icon($swap ? ($m['team2Logo'] ?? '') : ($m['team1Logo'] ?? ''), $swap ? ($m['team2Emblem'] ?? '') : ($m['team1Emblem'] ?? ''));
    $icon2 = hrn_team_icon($swap ? ($m['team1Logo'] ?? '') : ($m['team2Logo'] ?? ''), $swap ? ($m['team1Emblem'] ?? '') : ($m['team2Emblem'] ?? ''));
    $yet2 = $abYetToBat($s2, $o2);
    $place = $m['locality'] !== '' ? $m['locality'] : (strcasecmp($m['venue'], 'Custom Match') !== 0 ? $m['venue'] : '');
    $loc = implode(' · ', array_filter([$place, $m['district']]));
@endphp
<a class="mab__match" data-sport="{{ $m['sport'] ?? 'cricket' }}" href="{{ route('site.gamehub.actionboard.match', ['id' => $m['id']]) }}">
    <div class="mab__mctx">
        {{-- Admin-featured: a star, not a section. Visible to everyone. --}}
        @if ($m['isFeatured'] ?? false)
            <span class="mab__star" title="Featured by Haraan" aria-label="Featured">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.6l2.9 5.9 6.5.95-4.7 4.58 1.11 6.47L12 17.45 6.19 20.5 7.3 14.03 2.6 9.45l6.5-.95L12 2.6z"></path></svg>
            </span>
        @endif
        @if ($m['isLive'])
            <span class="mab__beacon"><span></span></span>
            <span class="mab__mlive">LIVE</span>
        @else
            <span class="mab__msched">{{ strtoupper($m['status'] ?: 'SCHEDULED') }}</span>
        @endif
        <span class="mab__mdot"></span>
        <span class="mab__mcomp">{{ strtoupper($m['competition'] !== '' ? $m['competition'] : 'Match') }}</span>
        @if ($loc !== '')
            <span class="mab__mdot"></span>
            <span class="mab__mloc">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                {{ $loc }}
            </span>
        @endif
        {{-- Only ever a measured distance — never a guess. --}}
        @if (($m['distanceKm'] ?? null) !== null)
            <span class="mab__mdot"></span>
            <span class="mab__mkm">{{ $m['distanceKm'] < 1 ? 'Under 1 km' : $m['distanceKm'] . ' km' }}</span>
        @endif
        @if ($m['isMine'])
            <span class="mab__you">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="8" r="3.4"></circle><path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path></svg>
                YOU
            </span>
        @endif
    </div>
    <div class="mab__hair"></div>
    <div class="mab__mbody">
        <div class="mab__mteams">
            <div class="mab__trow">
                <span class="mab__tlogo {{ $icon1 !== '' ? 'has-img' : '' }}" style="background: {{ $icon1 !== '' ? '#fff' : $abColor($t1) }}">@if($icon1 !== '')<img src="{{ $icon1 }}" alt="{{ $c1 }}" loading="lazy" onerror="this.replaceWith(document.createTextNode('{{ mb_substr($c1,0,3) }}'))">@else{{ mb_substr($c1, 0, 3) }}@endif</span>
                <span class="mab__tname is-bat">{{ $c1 }}</span>
                <span class="mab__tscore is-bat">{{ $s1 }}@if ($o1 !== '')<small>{{ $o1 }}</small>@endif</span>
            </div>
            <div class="mab__trow">
                <span class="mab__tlogo is-dim {{ $icon2 !== '' ? 'has-img' : '' }}" style="background: {{ $icon2 !== '' ? '#fff' : $abColor($t2) }}">@if($icon2 !== '')<img src="{{ $icon2 }}" alt="{{ $c2 }}" loading="lazy" onerror="this.replaceWith(document.createTextNode('{{ mb_substr($c2,0,3) }}'))">@else{{ mb_substr($c2, 0, 3) }}@endif</span>
                <span class="mab__tname is-dim">{{ $c2 }}</span>
                @if ($yet2)
                    <span class="mab__tyet">Yet to bat</span>
                @else
                    <span class="mab__tscore is-dim">{{ $s2 }}@if ($o2 !== '')<small>{{ $o2 }}</small>@endif</span>
                @endif
            </div>
        </div>
        <div class="mab__vdiv"></div>
        <div class="mab__mstat">
            <b>{{ $m['isLive'] ? 'LIVE' : ($m['status'] !== '' ? $m['status'] : 'Scheduled') }}</b>
            <span>{{ $m['competition'] }}</span>
        </div>
    </div>
</a>
