@extends('site.layout')

@section('content')
{{--
    The account screen — the web twin of the app's AccountProfileScreen.kt.
    Same proposition, same order: one identity hero, two lanes (Tickets / Play),
    a standing strip ONLY when there's something earned, then Account, Legal and
    Sign out. Structure and rules are ported from the app deliberately; don't
    "improve" the order here without changing it there too.
--}}
@php
    $avatar = $user->avatar;
    if (!empty($avatar) && !preg_match('#^(https?://|/)#', $avatar)) {
        $avatar = asset('storage/' . ltrim($avatar, '/'));
    }

    // Where you play and how long you've been here — the app's hero subtitle.
    $place = filled($user->district) ? $user->district : null;
    $since = $user->created_at ? 'Member since ' . $user->created_at->format('Y') : null;
    $subtitle = implode(' · ', array_filter([$place, $since]));

    // Standing is earned-only: an all-zero strip would be decoration, so absence
    // is the honest state (matches the app's `rankedXp > 0 || careerMatches > 0`).
    $xp = (int) ($user->ranked_xp ?? 0);
    $matches = (int) ($user->career_matches ?? 0);
    $showStanding = $xp > 0 || $matches > 0;
@endphp

<div class="aprof">

    {{-- ── Identity hero — the one moment that dominates the screen ── --}}
    <section class="aprof-hero">
        <div class="aprof-hero__row">
            {{-- The avatar is the hero's one action: upload a photo. --}}
            <form method="POST" action="{{ route('site.profile.avatar') }}" enctype="multipart/form-data" id="avatarForm">
                @csrf
                <label class="aprof-avatar" title="Upload photo">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="Profile photo">
                    @else
                        <span class="aprof-avatar__initial">{{ mb_strtoupper(mb_substr($user->name ?: '?', 0, 1)) }}</span>
                    @endif
                    <span class="aprof-avatar__badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                    </span>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" hidden
                           onchange="document.getElementById('avatarForm').submit()">
                </label>
            </form>

            <div class="aprof-hero__id">
                <h1 class="aprof-hero__name">{{ $user->name ?: 'Haraan user' }}</h1>
                @if($user->email)
                    <p class="aprof-hero__email">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <span>{{ $user->email }}</span>
                    </p>
                @endif
                @if($subtitle)
                    <p class="aprof-hero__sub">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        {{-- The member ID is the one genuinely distinctive thing here, so it gets its
             own band: labelled, monospaced, tappable to copy — the way an airline
             treats a frequent-flyer number. --}}
        @if($user->player_id)
            <div class="aprof-hero__rule"></div>
            <button type="button" class="aprof-memberid" data-copy="{{ $user->player_id }}">
                <span>
                    <small>MEMBER ID</small>
                    <b>{{ $user->player_id }}</b>
                </span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            </button>
        @endif
    </section>

    {{-- ── The two lanes: one identity, two things to do with it. Each owns its
         accent and its own number, so neither repeats what the hero already said. --}}
    <div class="aprof-lanes">
        <a class="aprof-lane" href="{{ route('site.bookings') }}">
            <span class="aprof-lane__icon aprof-lane__icon--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5v2m0 4v2m0 4v2M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7a2 2 0 0 1 2-2z"></path></svg>
            </span>
            <span class="aprof-lane__label">Tickets</span>
            @if($ticketsValue)
                <span class="aprof-lane__value">{{ $ticketsValue }}</span>
                <span class="aprof-lane__caption">{{ $ticketsCaption }}</span>
            @else
                {{-- An empty lane should read as a door, not as a zero. --}}
                <span class="aprof-lane__invite aprof-lane__invite--blue">
                    {{ $ticketsCaption }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </span>
            @endif
        </a>

        <a class="aprof-lane" href="{{ $user->player_id ? route('site.player.profile', ['player_id' => $user->player_id]) : route('site.gamehub.actionboard') }}">
            <span class="aprof-lane__icon aprof-lane__icon--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18.5 5.5 5.5 18.5"></path><circle cx="7" cy="17" r="3"></circle><path d="M14 3h7v7"></path><path d="M21 3 10 14"></path></svg>
            </span>
            <span class="aprof-lane__label">Play</span>
            @if($matchesValue)
                <span class="aprof-lane__value">{{ $matchesValue }}</span>
                <span class="aprof-lane__caption">matches played</span>
            @else
                <span class="aprof-lane__invite aprof-lane__invite--green">
                    Join a local match
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </span>
            @endif
        </a>
    </div>

    {{-- ── Standing — three numbers you can only get by playing. --}}
    @if($showStanding)
        <div class="aprof-standing">
            <div class="aprof-stat">
                <svg class="aprof-stat__icon" style="color:#2563EB" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                <b>{{ number_format($xp) }}</b>
                <small>Match XP</small>
            </div>
            <i class="aprof-stat__divider"></i>
            <div class="aprof-stat">
                <svg class="aprof-stat__icon" style="color:#00B140" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"></path><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"></path></svg>
                <b>{{ $user->rank_district ? '#' . $user->rank_district : '—' }}</b>
                <small>In district</small>
            </div>
            <i class="aprof-stat__divider"></i>
            <div class="aprof-stat">
                <svg class="aprof-stat__icon" style="color:#5A6473" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <b>{{ (int) ($user->trust_score ?? 100) }}</b>
                <small>Trust</small>
            </div>
        </div>
    @endif

    @if(session('success'))
        <div class="aprof-flash">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="aprof-flash aprof-flash--err">{{ $errors->first() }}</div>
    @endif

    {{-- ── Account ── --}}
    <h2 class="aprof-heading">Account</h2>
    <div class="aprof-card">
        <a class="aprof-row" href="{{ route('site.account.privacy') }}">
            <svg class="aprof-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span>Privacy</span>
            <svg class="aprof-row__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
        <i class="aprof-hr"></i>
        <a class="aprof-row" href="{{ route('site.support') }}">
            <svg class="aprof-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg>
            <span>Support</span>
            <svg class="aprof-row__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    {{-- Legal is its own group: these are documents you read, not settings you
         change, and they're the two links an app store review looks for. --}}
    <h2 class="aprof-heading">Legal</h2>
    <div class="aprof-card">
        <a class="aprof-row" href="{{ route('site.legal', ['slug' => 'terms']) }}">
            <svg class="aprof-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="13" y2="17"></line></svg>
            <span>Terms &amp; Conditions</span>
            <svg class="aprof-row__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
        <i class="aprof-hr"></i>
        <a class="aprof-row" href="{{ route('site.legal', ['slug' => 'privacy']) }}">
            <svg class="aprof-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span>Privacy Policy</span>
            <svg class="aprof-row__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    {{-- The destructive action gets its own container. It shouldn't share a card
         edge with a help link. --}}
    <form method="POST" action="{{ route('site.logout') }}" class="aprof-card aprof-card--signout">
        @csrf
        <button type="submit" class="aprof-row aprof-row--signout">
            <svg class="aprof-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            <span>Sign out</span>
        </button>
    </form>

    <p class="aprof-version">Haraan</p>
</div>
@endsection
