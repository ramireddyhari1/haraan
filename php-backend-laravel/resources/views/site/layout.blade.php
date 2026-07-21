<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Haraan' }}</title>
    <meta name="theme-color" content="#ffffff">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Inter:wght@300..800&family=Great+Vibes&display=swap" rel="stylesheet">
    @php
        // Cache-bust assets by file mtime: browsers can cache them, but a new
        // deploy (changed file) yields a new URL. Beats ?v=time() which never caches.
        //
        // clearstatcache() is load-bearing in dev: `php artisan serve` is one
        // long-lived process and PHP caches stat results, so without this the mtime
        // goes stale, ?v= stops changing, and edits silently don't reach the browser
        // — which reads exactly like a CSS specificity bug. Costs one stat per asset.
        $assetVer = function (string $p): string {
            $abs = public_path($p);
            clearstatcache(true, $abs);

            return asset($p) . '?v=' . (is_file($abs) ? filemtime($abs) : '1');
        };
    @endphp
    <link rel="stylesheet" href="{{ $assetVer('css/site.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('css/site-theme-overrides.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('css/site-mobile-overrides.css') }}" media="(max-width:1024px)">
    <script src="https://cdn.lordicon.com/lordicon.js"></script>
    <style>
        /* NB: the desktop mobile-UI-hide safety rules live in site.css
           (@media min-width:1025px) — not duplicated here. */

        /* Brand lockup and mobile footer are phone-only (styled in the mobile
           overrides ≤720px); never render elsewhere. */
        @media (min-width: 721px) {
            .mbrandmark, .mfoot { display: none !important; }
        }

        /* Focused checkout: the booking review + payment pages hide the whole site
           header (greeting, search, section tabs) so nothing competes with the order.
           These pages carry their own "Back to event" link for navigation. */
        body.booking-page .topbar { display: none !important; }

        /* Event-section header accent, applied only on /events pages.
           NB: the location-pill label is intentionally left neutral — it is a
           secondary control and should not compete with search / the nav. */
        .topbar.topbar--events .brand__text strong,
        .topbar.topbar--events .topnav__link,
        .topbar.topbar--events .topnav__link.is-active,
        .topbar.topbar--events .topnav__link:hover {
            color: #0d6efd !important;
        }

        .topbar.topbar--events .topnav__link.is-active::after,
        .topbar.topbar--events .topnav__link:hover::after {
            background: #0d6efd !important;
        }

        .topbar.topbar--events .btn--solid {
            background: #0d6efd !important;
            border-color: #0d6efd !important;
            box-shadow: 0 10px 20px rgba(13, 110, 253, 0.12) !important;
        }

        /* GameHub section header accent, applied only on /gamehub pages.
           Location-pill label left neutral (see note above). */
        .topbar.topbar--gamehub .brand__text strong,
        .topbar.topbar--gamehub .topnav__link,
        .topbar.topbar--gamehub .topnav__link.is-active,
        .topbar.topbar--gamehub .topnav__link:hover {
            color: #16a34a !important;
        }

        .topbar.topbar--gamehub .topnav__link.is-active::after,
        .topbar.topbar--gamehub .topnav__link:hover::after {
            background: #16a34a !important;
        }

        .topbar.topbar--gamehub .btn--solid {
            background: #16a34a !important;
            border-color: #16a34a !important;
            box-shadow: 0 10px 20px rgba(22, 163, 74, 0.12) !important;
        }

        /* ── Brand splash loader (BookMyShow-style) ───────────────────────────
           Critical, inlined so it paints on the first frame — before site.css.
           Full-screen wash tuned to the logo's own #FEFEFE plate so the wordmark
           has no visible box; a light streak sweeps through the letters, a slim
           EventsBlue bar runs beneath. Shown once per browser session. */
        .hloader {
            position: fixed;
            inset: 0;
            z-index: 3000;
            display: grid;
            place-items: center;
            background: radial-gradient(120% 120% at 50% 42%, #ffffff 0%, #fdfefe 34%, #eef2f7 100%);
            opacity: 1;
            transition: opacity 0.55s ease, visibility 0.55s ease;
        }
        .hloader.is-done { opacity: 0; visibility: hidden; pointer-events: none; }
        .hloader.is-instant { display: none !important; }
        .hloader__stage {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 26px;
            transform: translateY(-2%);
        }
        .hloader__logo {
            position: relative;
            width: clamp(180px, 42vw, 300px);
            line-height: 0;
            animation: hl-pop 0.7s cubic-bezier(0.16, 1, 0.3, 1) both,
                       hl-float 2.8s ease-in-out 0.7s infinite;
        }
        .hloader__logo img { width: 100%; height: auto; display: block; }
        /* Shine sweep — a translucent white streak travels across the wordmark.
           Screen-blended, so it only lifts the blue letters (it's a no-op over the
           near-white plate): the gloss appears to run *through* the letterforms. */
        .hloader__shine {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.92) 50%, transparent 60%);
            background-size: 220% 100%;
            background-repeat: no-repeat;
            mix-blend-mode: screen;
            animation: hl-shine 1.9s ease-in-out 0.5s infinite;
        }
        .hloader__bar {
            position: relative;
            width: clamp(120px, 26vw, 190px);
            height: 4px;
            border-radius: 99px;
            background: rgba(37, 99, 235, 0.14);
            overflow: hidden;
        }
        .hloader__bar span {
            position: absolute;
            top: 0;
            left: -45%;
            height: 100%;
            width: 42%;
            border-radius: 99px;
            background: linear-gradient(90deg, #3b82f6, #2563EB);
            animation: hl-bar 1.25s cubic-bezier(0.65, 0.05, 0.36, 1) infinite;
        }
        @keyframes hl-pop { 0% { opacity: 0; transform: scale(0.86); } 100% { opacity: 1; transform: scale(1); } }
        @keyframes hl-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        @keyframes hl-shine { 0% { background-position: 130% 0; } 55%, 100% { background-position: -30% 0; } }
        @keyframes hl-bar { 0% { left: -45%; width: 42%; } 50% { width: 58%; } 100% { left: 100%; width: 42%; } }
        @media (prefers-reduced-motion: reduce) {
            .hloader__logo { animation: hl-pop 0.01s both; }
            .hloader__shine { display: none; }
            .hloader__bar span { animation-duration: 2s; }
        }
    </style>
</head>
<body class="@yield('body_class') {{ request()->is('gamehub*') ? 'aurora-hub' : '' }}">
    {{-- Brand splash loader — a premium first-open moment (BookMyShow-style).
         Shown once per browser session; later in-session navigations skip it so
         it never nags. Styles are inlined in <head> so it covers the first paint. --}}
    <div id="haraanLoader" class="hloader" role="status" aria-live="polite" aria-label="Loading Haraan">
        <div class="hloader__stage">
            <div class="hloader__logo">
                <img src="{{ $assetVer('images/haraan-loader.png') }}" alt="Haraan" width="300" height="100" fetchpriority="high">
                <span class="hloader__shine" aria-hidden="true"></span>
            </div>
            <div class="hloader__bar" aria-hidden="true"><span></span></div>
        </div>
    </div>
    <script>
        (function () {
            var el = document.getElementById('haraanLoader');
            if (!el) return;
            // Once per session: repeat navigations within the session skip the splash.
            if (sessionStorage.getItem('haraanSplashShown')) { el.classList.add('is-instant'); return; }
            sessionStorage.setItem('haraanSplashShown', '1');
            var start = Date.now(), MIN = 650, done = false;
            function hide() {
                if (done) return; done = true;
                var wait = Math.max(0, MIN - (Date.now() - start)); // let the animation breathe
                setTimeout(function () {
                    el.classList.add('is-done');
                    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 600);
                }, wait);
            }
            window.addEventListener('load', hide);
            setTimeout(hide, 4000); // safety: never trap the page behind the splash
        })();
    </script>
    <noscript><style>.hloader{display:none !important;}</style></noscript>
    <header class="topbar {{ request()->is('events*') || request()->is('/') ? 'topbar--events' : '' }} {{ request()->is('gamehub*') ? 'topbar--gamehub' : '' }}">
        <div class="topbar__inner container">
            <a href="/" class="brand">
                <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan" class="brand__img">
            </a>

            {{-- Mobile header lockup (the app's GreetingHeader). Hidden ≥1025px, where the
                 brand + location pill + nav row takes over. GameHub renders its own copy on
                 the green hero and hides this whole topbar — see site/partials/app-greet. --}}
            @include('site.partials.app-greet')

            <a class="location-pill" href="#" id="locationToggle" data-location-toggle>
                <span class="location-pill__pin">
                    {{-- Inline SVG so the pin inherits the section accent (body.mode-*
                         already tints this) instead of the lord-icon's hardcoded black. --}}
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </span>
                <span class="location-pill__label">
                    <strong>{{ $selectedCity ?? 'All India' }}</strong>
                    <small>{{ $selectedCity ? 'India' : 'Choose city' }}</small>
                </span>
            </a>

            <form action="/search" method="GET" class="topbar__search" role="search">
                <span class="search-icon">
                    {{-- Inline SVG (was a lord-icon with hardcoded black) so it inherits
                         the section accent — blue on Events, green on GameHub — and drops
                         the cdn.lordicon.com dependency. --}}
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input
                    type="text"
                    name="q"
                    class="topbar__search-input"
                    placeholder="Search events, venues, sports..."
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="searchSuggest"
                >
                <div class="search-suggest" id="searchSuggest" role="listbox" hidden></div>
            </form>

            <div class="mobile-action-buttons">
                <a href="/events" class="mobile-action-btn mobile-action-btn--events">Events</a>
                <a href="/gamehub" class="mobile-action-btn mobile-action-btn--gamehub">GameHub</a>
            </div>

            <nav class="topnav" aria-label="Primary">
                <a href="/events" class="topnav__link {{ request()->is('/') || request()->is('events*') ? 'is-active' : '' }}">Events</a>
                <a href="/gamehub" class="topnav__link {{ request()->is('gamehub*') && !request()->is('gamehub/leaderboard*') ? 'is-active' : '' }}">GameHub</a>
            </nav>

            <div class="topbar__actions">
                @if(auth()->check())
                    @php
                        $user = auth()->user();
                        $avatar = $user->avatar ?? null;
                        // normalize avatar URL
                        if (!empty($avatar) && !preg_match('/^(http|https):\/\//', $avatar) && strpos($avatar, '/') !== 0) {
                            $avatar = asset('storage/' . ltrim($avatar, '/'));
                        }
                    @endphp

                    {{-- Desktop reach into the same two inbox lanes the mobile lockup
                         exposes — without these the pages are URL-only up here. --}}
                    <a href="{{ route('site.support') }}" class="topbar__util"
                       aria-label="Support chat{{ ($headerSupportUnread ?? 0) > 0 ? ' — ' . $headerSupportUnread . ' unread' : '' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        @if(($headerSupportUnread ?? 0) > 0)<span class="topbar__util-dot" aria-hidden="true"></span>@endif
                    </a>
                    <a href="{{ route('site.notifications') }}" class="topbar__util"
                       aria-label="Notifications{{ ($headerBellUnread ?? 0) > 0 ? ' — ' . $headerBellUnread . ' unread' : '' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        @if(($headerBellUnread ?? 0) > 0)<span class="topbar__util-dot" aria-hidden="true"></span>@endif
                    </a>
                @endif
                @if(auth()->check())
                    <a href="/profile" class="topbar__account-link" title="{{ $user->name ?? 'Account' }}">
                        @if(!empty($avatar))
                            <img src="{{ $avatar }}" alt="Profile" class="topbar__account" />
                        @else
                            {{-- No uploaded photo: inline user glyph that inherits the section
                                 accent, instead of the black flaticon PNG. Real avatars above
                                 stay as photos. --}}
                            <span class="topbar__account topbar__account--placeholder" aria-label="Account">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4"></circle>
                                    <path d="M4 21c0-4.5 3.6-7.5 8-7.5s8 3 8 7.5"></path>
                                </svg>
                            </span>
                        @endif
                    </a>
                @else
                    <a href="#" class="btn btn--solid" id="loginBtn">Login</a>
                @endif
                <!-- ERP portal link intentionally hidden from public header -->
            </div>
        </div>
    </header>

    {{-- Account pages are their own place, not a stop on the feed: the app gives them
         a PageHeader (back + title) instead of the greeting/search/tabs, so the phone
         does too. Desktop keeps the topbar — it's the only nav a wide screen has, and
         CSS decides which of the two shows (see .pagebar). --}}
    @if(request()->is('profile', 'bookings', 'account/*', 'legal/*'))
        <header class="pagebar">
            <button type="button" class="pagebar__back" aria-label="Back" data-back>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </button>
            <h1 class="pagebar__title">{{ $title ?? 'Account' }}</h1>
        </header>
    @endif

    <main class="{{ request()->is('gamehub/actionboard/match/*') ? '' : 'container' }}" role="main">
        @yield('content')
    </main>

    {{-- Mobile footer (≤720px, main tabs only): app download + links + brand base.
         The Play badge targets the final package id — the link goes live the day
         the listing publishes; until then the direct-APK line below it works. --}}
    @if(request()->is('/', 'events', 'gamehub'))
    @php
        $footIsHub = request()->is('gamehub');
        $footHead  = $footIsHub ? 'Play on the go' : 'Take Haraan with you';
        $footBlurb = $footIsHub
            ? 'Follow live scores, track your stats, and climb the leaderboards from anywhere.'
            : 'Book faster, discover events near you, and keep your tickets in your pocket.';
        $footTag   = $footIsHub ? 'Play. Compete. Climb.' : 'Discover. Book. Play.';
    @endphp
    <footer class="mfoot">
        <div class="mfoot__app">
            <div>
                <strong>{{ $footHead }}</strong>
                <p>{{ $footBlurb }}</p>
            </div>
            <a class="mfoot__play" href="https://play.google.com/store/apps/details?id=com.haraan.app" target="_blank" rel="noopener" aria-label="Get the Haraan app on Google Play">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#00E3A5" d="M4.1 1.9 14.6 12 4.1 22.1c-.4-.2-.7-.7-.7-1.3V3.2c0-.6.3-1.1.7-1.3z"/>
                    <path fill="#3DDCFF" d="M17.7 9.1 5.6 2l9 8.7z"/>
                    <path fill="#FFC933" d="m14.6 12 3.1-2.9 3 1.7c1 .6 1 1.9 0 2.4l-3 1.7z"/>
                    <path fill="#FF5C6C" d="m14.6 12-9 10 12.1-7.1z"/>
                </svg>
                <span><small>Get it on</small><b>Google Play</b></span>
            </a>
            <a class="mfoot__apk" href="/haraan.apk">or download the Android APK directly</a>
        </div>
        <nav class="mfoot__links" aria-label="Footer">
            @if($footIsHub)
            <a href="/gamehub">GameHub</a>
            <a href="/gamehub/leaderboard">Leaderboard</a>
            @else
            <a href="/events">Events</a>
            <a href="/gamehub">GameHub</a>
            @endif
            <a href="/support">Support</a>
            <a href="/notifications">Notifications</a>
            <a href="/profile">My profile</a>
        </nav>
        <div class="mfoot__base">
            <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan">
            <span>{{ $footTag }}</span>
            <small>© {{ date('Y') }} Haraan. All rights reserved.</small>
        </div>
    </footer>
    @endif

    <!-- Premium Login Modal -->
    {{-- The floating login modal is hidden on the /login page itself — that page renders the
         same design inline, and two copies would collide on the shared element ids. --}}
    @unless(request()->routeIs('site.login'))
    <style>
        .auth-modal .pw-form .auth-field { margin-bottom: 13px; text-align: left; }
        .auth-modal .pw-form .auth-field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; letter-spacing: .01em; }
        .auth-modal .pw-form .auth-input { width: 100%; box-sizing: border-box; height: 46px; padding: 0 14px; font-size: 15px; color: #0F172A; background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; transition: border-color .15s, box-shadow .15s, background .15s; }
        .auth-modal .pw-form .auth-input::placeholder { color: #94A3B8; }
        .auth-modal .pw-form .auth-input:focus { outline: none; background: #fff; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,.14); }
        .auth-modal .pw-form .pw-meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: -2px 0 14px; }
        .auth-modal .pw-form .pw-meta a { font-size: 12.5px; font-weight: 600; color: #2563EB; text-decoration: none; }
        .auth-modal .pw-form .pw-meta a:hover { text-decoration: underline; }
        .auth-modal .pw-form .pw-switch { color: #0F172A; }
        .auth-modal .pw-form .auth-row { display: flex; gap: 10px; }
        /* `hidden` must beat the flex display above, or Age/Gender leak into login mode. */
        .auth-modal .pw-form .auth-row[hidden] { display: none; }
        .auth-modal .pw-form .auth-row .auth-field { flex: 1; margin-bottom: 13px; }
        .auth-modal .pw-form select.auth-input { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 13px center; padding-right: 34px; }
    </style>
    <div id="loginModal" class="auth-modal" aria-hidden="true"@if($errors->any() || session('error')) data-open-on-error="1"@endif>
        <div class="auth-modal__backdrop" id="loginBackdrop"></div>
        <div class="auth-modal__card" role="dialog" aria-modal="true" aria-label="Login to Haraan">
            <button class="auth-modal__close" id="closeLogin" aria-label="Close login">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            
            <div class="auth-modal__header">
                <div class="auth-modal__logo">
                    <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan">
                    <p>Book events, play sports, follow live scores — one account for both lanes.</p>
                </div>
            </div>

            <div class="auth-modal__body">
                @if(session('whatsapp_phone'))
                    <h3>Verify OTP</h3>
                    <p class="subtitle">Enter the 6-digit code sent to your WhatsApp.</p>
                    
                    @if(session('success'))
                        <div class="auth-alert auth-alert--ok" role="status">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                        <div class="auth-alert" role="alert">{{ session('error') }}</div>
                    @endif

                    <form method="post" action="{{ route('whatsapp.verify.submit') }}" class="phone-form">
                        @csrf
                        <div class="field" style="margin-bottom: 15px;">
                            <input
                                type="text"
                                name="otp"
                                class="otp-input"
                                placeholder="123456"
                                required
                                maxlength="6"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                            >
                        </div>
                        <button type="submit" class="btn btn--solid btn--full btn--large">Verify & Login</button>
                    </form>

                    <div class="auth-modal__footer" style="margin-top: 15px; text-align: center;">
                        <p><a href="{{ route('whatsapp.cancel') }}">Change Phone Number</a></p>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            setTimeout(() => {
                                const loginBtn = document.getElementById('loginBtn');
                                if(loginBtn) { loginBtn.click(); }
                            }, 50);
                        });
                    </script>
                @else
                    <h3>Log in or sign up</h3>
                    <p class="subtitle">If you don't have an account yet, we'll create one for you</p>

                    @if(session('error'))
                        <div class="auth-alert" role="alert">{{ session('error') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="auth-alert" role="alert">{{ $errors->first() }}</div>
                    @endif

                    {{-- Google first: one tap for returning users, no round-trip.
                         Hidden entirely when GOOGLE_CLIENT_ID isn't set, so a missing
                         config shows nothing rather than a button that always fails. --}}
                    @if(config('services.google.client_id'))
                        <div class="auth-google">
                            <div id="googleSignInBtn" class="auth-google__btn"></div>
                            <p class="auth-google__error" id="googleSignInError" role="alert" hidden></p>
                        </div>
                        <div class="auth-divider"><span>or</span></div>
                    @endif

                    <form class="pw-form" id="mAuthForm" method="POST" action="{{ route('site.password.login') }}" data-mode="login">
                        @csrf
                        <div class="auth-field auth-field--signup" id="mNameField" hidden>
                            <label for="mAuthName">Name</label>
                            <input type="text" name="name" id="mAuthName" class="auth-input" placeholder="Your name" autocomplete="name" maxlength="60" disabled>
                        </div>
                        <div class="auth-row auth-field--signup" hidden>
                            <div class="auth-field">
                                <label for="mAuthAge">Age</label>
                                <input type="number" name="age" id="mAuthAge" class="auth-input" placeholder="Age" min="5" max="120" inputmode="numeric" disabled>
                            </div>
                            <div class="auth-field">
                                <label for="mAuthGender">Gender</label>
                                <select name="gender" id="mAuthGender" class="auth-input" disabled>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="mAuthEmail">Email</label>
                            <input type="email" name="email" id="mAuthEmail" class="auth-input" placeholder="you@example.com" value="{{ old('email') }}" required autocomplete="email" autocapitalize="off" spellcheck="false">
                        </div>
                        <div class="auth-field">
                            <label for="mAuthPassword">Password</label>
                            <input type="password" name="password" id="mAuthPassword" class="auth-input" placeholder="Your password" required autocomplete="current-password" minlength="6">
                        </div>
                        <div class="pw-meta">
                            <a href="#" id="mSignupToggle" class="pw-switch">Create new account</a>
                            <a href="{{ route('site.password.request') }}" class="pw-forgot-link" id="mForgotLink">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn--solid btn--full btn--large" id="mAuthSubmit">Continue</button>
                    </form>

                    <script>
                        (function () {
                            var form = document.getElementById('mAuthForm');
                            if (!form) return;
                            var toggle = document.getElementById('mSignupToggle');
                            var nameField = document.getElementById('mNameField');
                            var nameInput = document.getElementById('mAuthName');
                            var pwInput = document.getElementById('mAuthPassword');
                            var submit = document.getElementById('mAuthSubmit');
                            var forgot = document.getElementById('mForgotLink');
                            var heading = form.closest('.auth-modal__body')?.querySelector('h3');

                            function setMode(signup) {
                                form.dataset.mode = signup ? 'signup' : 'login';
                                // Show/hide every sign-up-only field and toggle its inputs
                                // (disabled inputs are NOT submitted, so login mode stays clean).
                                form.querySelectorAll('.auth-field--signup').forEach(function (el) { el.hidden = !signup; });
                                form.querySelectorAll('.auth-field--signup input, .auth-field--signup select').forEach(function (inp) { inp.disabled = !signup; });
                                nameInput.required = signup;       // only name is required among sign-up fields
                                submit.textContent = signup ? 'Create account' : 'Continue';
                                toggle.textContent = signup ? 'Have an account? Log in' : 'Create new account';
                                if (forgot) forgot.hidden = signup;
                                if (heading) heading.textContent = signup ? 'Create your account' : 'Log in or sign up';
                                pwInput.setAttribute('autocomplete', signup ? 'new-password' : 'current-password');
                                if (signup) nameInput.focus();
                            }

                            toggle.addEventListener('click', function (e) {
                                e.preventDefault();
                                setMode(form.dataset.mode !== 'signup');
                            });
                        })();
                    </script>

                    <div class="auth-modal__footer">
                        <p>By continuing, you agree to our <br> <a href="#">Terms of Service</a> &nbsp; <a href="#">Privacy Policy</a></p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endunless

    {{-- One-time "tell us about you" prompt: age + gender for logged-in users who
         are missing both. Google can't supply these, so we ask once — the data
         feeds the per-event Audience analytics. Only fills blanks; never overwrites. --}}
    @auth
        @if(blank(auth()->user()->age) && blank(auth()->user()->gender) && ! session('demographics_prompt_dismissed'))
        <style>
            .demo-prompt { display: none; position: fixed; inset: 0; z-index: 1200; place-items: center; padding: 18px; }
            .demo-prompt__backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.55); backdrop-filter: blur(2px); }
            .demo-prompt__card { position: relative; width: 100%; max-width: 360px; background: #fff; border-radius: 20px; padding: 24px 22px; box-shadow: 0 24px 60px rgba(15,23,42,.28); }
            .demo-prompt__card h3 { margin: 0 0 6px; font-size: 19px; font-weight: 800; color: #0F172A; letter-spacing: -.02em; }
            .demo-prompt__card p { margin: 0 0 16px; font-size: 13px; color: #64748B; line-height: 1.5; }
            .demo-prompt__close { position: absolute; top: 12px; right: 12px; width: 30px; height: 30px; border: 0; background: #F1F5F9; border-radius: 50%; color: #64748B; cursor: pointer; font-size: 16px; line-height: 1; }
            .demo-row { display: flex; gap: 10px; }
            .demo-row .demo-field { flex: 1; margin-bottom: 14px; text-align: left; }
            .demo-field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; }
            .demo-field input, .demo-field select { width: 100%; box-sizing: border-box; height: 46px; padding: 0 14px; font-size: 15px; color: #0F172A; background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; }
            .demo-field select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 13px center; padding-right: 34px; }
            .demo-field input:focus, .demo-field select:focus { outline: none; background: #fff; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,.14); }
            .demo-skip { display: block; width: 100%; margin: 10px 0 0; padding: 6px; border: 0; background: none; color: #94A3B8; font-size: 12.5px; font-weight: 600; cursor: pointer; }
            .demo-skip:hover { color: #64748B; text-decoration: underline; }
        </style>
        <div id="demoPrompt" class="demo-prompt" aria-hidden="true">
            <div class="demo-prompt__backdrop" id="demoBackdrop"></div>
            <div class="demo-prompt__card" role="dialog" aria-modal="true" aria-label="Tell us a bit about you">
                <button type="button" class="demo-prompt__close" id="demoClose" aria-label="Close">&times;</button>
                <h3>Tell us a bit about you</h3>
                <p>It helps organisers plan better events for you. Takes 5 seconds.</p>
                <form method="POST" action="{{ route('site.account.demographics') }}">
                    @csrf
                    <div class="demo-row">
                        <div class="demo-field">
                            <label for="dpAge">Age</label>
                            <input type="number" name="age" id="dpAge" min="5" max="120" inputmode="numeric" placeholder="Age">
                        </div>
                        <div class="demo-field">
                            <label for="dpGender">Gender</label>
                            <select name="gender" id="dpGender">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn--solid btn--full btn--large">Save</button>
                    <button type="submit" name="skip" value="1" class="demo-skip">Skip for now</button>
                </form>
            </div>
        </div>
        <script>
            (function () {
                var p = document.getElementById('demoPrompt');
                if (!p) return;
                // Auto-open once per browser session; navigations within the session won't nag.
                if (!sessionStorage.getItem('demoPromptShown')) {
                    sessionStorage.setItem('demoPromptShown', '1');
                    setTimeout(function () { p.setAttribute('aria-hidden', 'false'); p.style.display = 'grid'; }, 700);
                }
                function hide() { p.setAttribute('aria-hidden', 'true'); p.style.display = 'none'; }
                document.getElementById('demoClose')?.addEventListener('click', hide);
                document.getElementById('demoBackdrop')?.addEventListener('click', hide);
            })();
        </script>
        @endif
    @endauth

    <!-- Location selector modal (improved) -->
    <div id="locationModal" class="location-modal" aria-hidden="true">
        <div class="location-modal__backdrop" id="locationBackdrop"></div>
        <div class="location-modal__card" role="dialog" aria-modal="true" aria-labelledby="locationTitle">
            <button id="closeLocation" class="location-modal__close" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>

            <div class="location-modal__titles">
                <h3 id="locationTitle" class="location-modal__title">Choose your city</h3>
                <p class="location-modal__sub" id="locationCurrent">Set where you want to play &amp; attend</p>
            </div>

            <div class="location-modal__header">
                <label class="location-search">
                    <svg class="location-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="search" id="locationSearch" placeholder="Search cities">
                </label>
                <button id="useCurrent" class="btn btn--use-location">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Use current location
                </button>
            </div>

            <section class="loc-section">
                <h4 class="loc-section__title">Popular Cities</h4>
                <div id="popularCities" class="popular-grid" role="list"></div>
            </section>

            <section class="loc-section">
                <h4 class="loc-section__title">All Cities</h4>
                <div class="all-cities-wrap">
                    <div id="allCities" class="all-list" role="list"></div>
                    <aside class="alpha-index" aria-hidden="false" id="alphaIndex" title="Jump to letter"></aside>
                </div>
            </section>
        </div>
    </div>

    {{-- Real-time content updates (Reverb). No-op unless BROADCAST_CONNECTION=reverb. --}}
    @php($reverb = config('broadcasting.connections.reverb'))
    <script>
        window.HaraanRealtime = {
            enabled: {{ config('broadcasting.default') === 'reverb' ? 'true' : 'false' }},
            key: @json($reverb['key'] ?? null),
            host: @json($reverb['options']['host'] ?? null),
            port: {{ (int) ($reverb['options']['port'] ?? 443) }},
            scheme: @json($reverb['options']['scheme'] ?? 'https'),
        };
    </script>
    @if(config('broadcasting.default') === 'reverb')
        <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
        <script src="{{ $assetVer('js/realtime.js') }}"></script>
    @endif

    {{-- "Continue with Google" (Google Identity Services). Loaded only when an OAuth
         client is configured. GIS renders its own button into #googleSignInBtn and hands
         us an ID token, which we post to the session-login route. --}}
    @if(config('services.google.client_id') && !auth()->check())
        <script>
            window.HaraanGoogleAuth = {
                clientId: @json(config('services.google.client_id')),
                postUrl: @json(route('google.web.login')),
            };
        </script>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    @endif

    <script src="{{ $assetVer('js/site.js') }}"></script>
    @if(session('show_login'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => {
                    const loginBtn = document.getElementById('loginBtn');
                    if(loginBtn) { loginBtn.click(); }
                }, 100);
            });
        </script>
    @endif
</body>
</html>
