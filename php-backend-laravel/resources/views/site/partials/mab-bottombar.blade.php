{{--
    The app's CrexBottomBar — the ONE bottom bar, shared by every app-parity mobile
    screen (the board, the Home feed, Chat). It used to be inline in actionboard.blade
    with its own copy of these rules; a second screen wanting the same bar is exactly
    how two bars start drifting apart.

    Usage: @include('site.partials.mab-bottombar', ['active' => 'Matches'])
    `active` is one of Home | Matches | Chat | Alerts | Player.

    Rules this bar arrived at the hard way (they came from the app, which had already
    been through them):
      · Icons are FILLED even when idle, on a solid slate (#5B6472). Outline-on-idle
        over a washed grey is what made it read thin and cheap.
      · Active is a PILL behind the icon (46x26, #E8F0FE) plus a small scale lift —
        not the icon translating upward, which pulls it out of the row's rhythm.
      · The last slot is the player's own face. A generic silhouette in the one place
        that stands for "you" is the cheapest thing a bar like this can do.

    Home and Chat are the app's destinations, not the website's nearest equivalents:
    Home is the social feed (photo posts + stories), Chat is player-to-player messaging.
    Pointing them at Pulse and the support desk made them look like the same product
    wearing the app's labels.
--}}
@php
    $active = $active ?? '';
    $barMe = auth()->user();
    $barAvatar = $barMe->avatar ?? null;
    if (!empty($barAvatar) && !preg_match('~^https?://~i', $barAvatar) && !str_starts_with($barAvatar, '/')) {
        $barAvatar = asset('storage/' . ltrim($barAvatar, '/'));
    }
    // Real first name only — never derive a letter from placeholder text.
    $barFirst = trim(strtok(trim($barMe->name ?? ''), ' '));
    $barInitial = $barFirst !== '' ? mb_strtoupper(mb_substr($barFirst, 0, 1)) : '';
@endphp

@once
<style>
/* ── Floating bottom bar (CrexBottomBar) ───────────────────────────────── */
.mab__nav {
    position: fixed; left: 8px; right: 8px; bottom: 8px; z-index: 70;
    height: 72px; background: #fff; border-radius: 26px; border: 1px solid #EDEFF3;
    box-shadow: 0 7px 22px rgba(0, 0, 0, .09);
    display: flex; align-items: center; justify-content: space-around;
    padding: 0 4px;
    padding-bottom: env(safe-area-inset-bottom, 0);
}
.mab__navi {
    /* Equal columns, not `width: 19%` inside space-around — that left the five
       slots on an uneven rhythm you can see once you look for it. */
    flex: 1 1 0; min-width: 0;
    background: none; border: 0; cursor: pointer; text-decoration: none;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
    /* A solid slate, NOT a washed light grey: the glyphs are filled, so the ink has
       to carry real weight or the whole bar reads faint. */
    color: #5B6472; font-family: inherit;
    -webkit-tap-highlight-color: transparent;
}
/* The selection indicator is a pill BEHIND the icon, sized so it can never crowd
   the label — the icon itself stays put on the row's baseline. */
.mab__navpill {
    width: 46px; height: 26px; border-radius: 13px;
    display: inline-flex; align-items: center; justify-content: center;
    background: transparent; transition: background .22s ease;
}
.mab__navpill > svg { width: 23px; height: 23px; display: block; transition: transform .28s cubic-bezier(.2, 1.5, .5, 1); }
.mab__navl { font-size: 10.5px; font-weight: 500; line-height: 12px; letter-spacing: .1px; }
.mab__navi.is-on { color: #2563EB; }
.mab__navi.is-on .mab__navpill { background: #E8F0FE; }
.mab__navi.is-on .mab__navpill > svg { transform: scale(1.14); }
.mab__navi.is-on .mab__navl { font-weight: 700; }
/* The paper plane flies nose-up-and-forward; the tilt is part of the mark, so the
   active scale has to carry it too or the icon snaps upright on selection. */
.mab__navi--chat .mab__navpill > svg { transform: rotate(-24deg); }
.mab__navi--chat.is-on .mab__navpill > svg { transform: rotate(-24deg) scale(1.14); }
@media (hover: hover) {
    .mab__navi:hover { color: #2563EB; }
    .mab__navi:not(.is-on):hover .mab__navpill { background: #F2F5FA; }
}
@media (prefers-reduced-motion: reduce) {
    .mab__navpill > svg, .mab__navpill, .mab__ava { transition: none; }
}

/* Player slot — the user's own face, with the initial underneath as the fallback. */
.mab__ava {
    /* 24px, and a gentler lift than the glyphs get: the ring is drawn OUTSIDE the
       circle on the web, so the same 1.14 scale pushes it past the 26px pill. */
    position: relative; width: 24px; height: 24px; border-radius: 50%;
    background: #F1F5F9; border: 1.5px solid #CBD2DC; overflow: hidden;
    display: inline-flex; align-items: center; justify-content: center;
    transition: border-color .22s ease, border-width .22s ease, transform .28s cubic-bezier(.2, 1.5, .5, 1);
}
.mab__navi.is-on .mab__ava { border-width: 2px; border-color: #2563EB; transform: scale(1.06); }
.mab__avai { font-size: 12px; font-weight: 800; line-height: 1; color: inherit; }
.mab__ava .mab__avag { width: 16px; height: 16px; }
.mab__avaimg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
</style>
@endonce

<nav class="mab__nav" aria-label="ActionBoard">
    <a class="mab__navi {{ $active === 'Home' ? 'is-on' : '' }}" href="{{ route('site.feed') }}"
       @if ($active === 'Home') aria-current="page" @endif>
        <span class="mab__navpill">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.05 3.3 4 8.7a2.4 2.4 0 0 0-.95 1.9v7.9A2.5 2.5 0 0 0 5.55 21H8.4a1.1 1.1 0 0 0 1.1-1.1v-4.05a1.3 1.3 0 0 1 1.3-1.3h2.4a1.3 1.3 0 0 1 1.3 1.3V19.9A1.1 1.1 0 0 0 15.6 21h2.85a2.5 2.5 0 0 0 2.5-2.5v-7.9a2.4 2.4 0 0 0-.95-1.9L12.95 3.3a1.55 1.55 0 0 0-1.9 0Z"></path></svg>
        </span>
        <span class="mab__navl">Home</span>
    </a>
    <a class="mab__navi {{ $active === 'Matches' ? 'is-on' : '' }}" href="{{ route('site.gamehub.actionboard') }}"
       @if ($active === 'Matches') aria-current="page" @endif>
        <span class="mab__navpill">
            {{-- Scoreboard: two score cells over a wide bar, knocked out of the slab
                 with evenodd so the mark stays one solid shape. --}}
            <svg viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd" aria-hidden="true"><path d="M5.8 3.4h12.4a3 3 0 0 1 3 3v7.4a3 3 0 0 1-3 3H5.8a3 3 0 0 1-3-3V6.4a3 3 0 0 1 3-3ZM6.2 7h4.9v3.6H6.2zm6.7 0h4.9v3.6h-4.9zM11.1 16.8h1.8v2.4h-1.8zM7.6 19.2h8.8v1.6H7.6z"></path></svg>
        </span>
        <span class="mab__navl">Matches</span>
    </a>
    <a class="mab__navi mab__navi--chat {{ $active === 'Chat' ? 'is-on' : '' }}" href="{{ auth()->check() ? route('site.chat') : '#' }}"
       @guest data-login-open @endguest @if ($active === 'Chat') aria-current="page" @endif>
        <span class="mab__navpill">
            {{-- The plane is tilted nose-up-and-forward, as in the app. --}}
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.3 3 2.9 11.05l6.6 2.6 2.6 6.6z"></path></svg>
        </span>
        <span class="mab__navl">Chat</span>
    </a>
    <a class="mab__navi {{ $active === 'Alerts' ? 'is-on' : '' }}" href="{{ auth()->check() ? route('site.notifications') : '#' }}"
       @guest data-login-open @endguest @if ($active === 'Alerts') aria-current="page" @endif>
        <span class="mab__navpill">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 22.2a2.25 2.25 0 0 0 2.2-1.8h-4.4a2.25 2.25 0 0 0 2.2 1.8Zm7-5.4-1.45-1.4v-4.25a5.8 5.8 0 0 0-4.4-5.65v-.72a1.15 1.15 0 1 0-2.3 0v.72a5.8 5.8 0 0 0-4.4 5.65v4.25L5 16.8a1.05 1.05 0 0 0 .74 1.8h12.52a1.05 1.05 0 0 0 .74-1.8Z"></path></svg>
        </span>
        <span class="mab__navl">Alerts</span>
    </a>
    <a class="mab__navi {{ $active === 'Player' ? 'is-on' : '' }}" href="{{ auth()->check() ? '/profile' : '#' }}" @guest data-login-open @endguest
       aria-label="{{ auth()->check() ? ($barMe->name ?? 'Account') : 'Log in' }}">
        <span class="mab__navpill">
            {{-- The initial is drawn underneath and the photo laid over it, so a
                 missing or broken avatar shows a letter rather than an empty disc. --}}
            <span class="mab__ava">
                @if ($barInitial !== '')
                    <span class="mab__avai">{{ $barInitial }}</span>
                @else
                    <svg class="mab__avag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8.4" r="3.6"></circle><path d="M4.8 20.4c0-4 3.2-6.6 7.2-6.6s7.2 2.6 7.2 6.6"></path></svg>
                @endif
                @if (!empty($barAvatar))
                    <img class="mab__avaimg" src="{{ $barAvatar }}" alt="" loading="lazy" onerror="this.remove()">
                @endif
            </span>
        </span>
        <span class="mab__navl">Player</span>
    </a>
</nav>
