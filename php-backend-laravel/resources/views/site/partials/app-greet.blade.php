{{-- The app's GreetingHeader (MainScreen.kt): avatar (left), "Hey <first name>!" over a
     tappable location line, utility icons (chat, bell, calendar) right.

     Used in two places, so it lives here rather than in either of them:
       · the sticky topbar, on white ($onDark = false)
       · the GameHub green hero, on dark ($onDark = true)
     The app passes the same `onDark` flag to the same composable for the same reason —
     on the GameHub tab it renders no outer header at all, and this row sits on the
     green band instead.

     @param bool $onDark  Render the white-ink variant for a dark background. --}}
@php
    $onDark = $onDark ?? false;
    $gUser = auth()->user();
    $gAvatar = $gUser->avatar ?? null;
    if (!empty($gAvatar) && !preg_match('/^(http|https):\/\//', $gAvatar) && strpos($gAvatar, '/') !== 0) {
        $gAvatar = asset('storage/' . ltrim($gAvatar, '/'));
    }
    $gFirstName = trim(strtok(trim($gUser->name ?? ''), ' ')) ?: 'there';
@endphp

<div class="app-greet {{ $onDark ? 'app-greet--dark' : '' }}">
    <a class="app-greet__avatar"
       href="{{ auth()->check() ? '/profile' : '#' }}"
       @guest data-login-open @endguest
       aria-label="{{ auth()->check() ? ($gUser->name ?? 'Account') : 'Log in' }}">
        {{-- Initial sits underneath so a missing or failed photo still shows a
             letter rather than an empty circle (same fallback as the app). --}}
        <span class="app-greet__initial">{{ mb_strtoupper(mb_substr($gFirstName, 0, 1)) }}</span>
        @if(!empty($gAvatar))
            <img src="{{ $gAvatar }}" alt="" class="app-greet__photo">
        @endif
    </a>

    <div class="app-greet__lockup">
        <span class="app-greet__hey">Hey {{ $gFirstName }}!</span>
        <a class="app-greet__loc" href="#" data-location-toggle>
            <span class="app-greet__loc-text">{{ $selectedCity ?? 'Set location' }}</span>
            <svg class="app-greet__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </a>
    </div>

    {{-- The app's three utility icons, in the app's order: chat, bell, calendar.
         Calendar opens the account (which is what the app's calendar does too).
         The bell's dot is a real unread count, never a decorative one. --}}
    <div class="app-greet__icons">
        <a class="app-greet__icon"
           href="{{ auth()->check() ? route('site.support') : '#' }}"
           @guest data-login-open @endguest
           aria-label="Support chat{{ ($headerSupportUnread ?? 0) > 0 ? ' — ' . $headerSupportUnread . ' unread' : '' }}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
            @if(($headerSupportUnread ?? 0) > 0)
                <span class="app-greet__dot" aria-hidden="true"></span>
            @endif
        </a>
        <a class="app-greet__icon"
           href="{{ auth()->check() ? route('site.notifications') : '#' }}"
           @guest data-login-open @endguest
           aria-label="Notifications{{ ($headerBellUnread ?? 0) > 0 ? ' — ' . $headerBellUnread . ' unread' : '' }}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            @if(($headerBellUnread ?? 0) > 0)
                <span class="app-greet__dot" aria-hidden="true"></span>
            @endif
        </a>
        <a class="app-greet__icon"
           href="{{ auth()->check() ? '/profile' : '#' }}"
           @guest data-login-open @endguest
           aria-label="My bookings">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </a>
    </div>
</div>
