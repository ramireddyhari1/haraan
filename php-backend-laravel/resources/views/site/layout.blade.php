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
    </style>
</head>
<body class="@yield('body_class')">
    <header class="topbar {{ request()->is('events*') || request()->is('/') ? 'topbar--events' : '' }} {{ request()->is('gamehub*') ? 'topbar--gamehub' : '' }}">
        <div class="topbar__inner container">
            <a href="/" class="brand">
                <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan" class="brand__img">
            </a>

            @php
                $hUser = auth()->user();
                $hAvatar = $hUser->avatar ?? null;
                if (!empty($hAvatar) && !preg_match('/^(http|https):\/\//', $hAvatar) && strpos($hAvatar, '/') !== 0) {
                    $hAvatar = asset('storage/' . ltrim($hAvatar, '/'));
                }
                $hFirstName = trim(strtok(trim($hUser->name ?? ''), ' ')) ?: 'there';
            @endphp

            {{-- Mobile header lockup, mirroring the app's GreetingHeader: avatar (left),
                 "Hey <first name>!" over a tappable location line, utility icons (right).
                 Hidden ≥1025px, where the brand + location pill + nav row takes over. --}}
            <div class="app-greet">
                <a class="app-greet__avatar"
                   href="{{ auth()->check() ? '/profile' : '#' }}"
                   @guest data-login-open @endguest
                   aria-label="{{ auth()->check() ? ($hUser->name ?? 'Account') : 'Log in' }}">
                    {{-- Initial sits underneath so a missing or failed photo still shows a
                         letter rather than an empty circle (same fallback as the app). --}}
                    <span class="app-greet__initial">{{ mb_strtoupper(mb_substr($hFirstName, 0, 1)) }}</span>
                    @if(!empty($hAvatar))
                        <img src="{{ $hAvatar }}" alt="" class="app-greet__photo">
                    @endif
                </a>

                <div class="app-greet__lockup">
                    <span class="app-greet__hey">Hey {{ $hFirstName }}!</span>
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

            <a class="location-pill" href="#" id="locationToggle" data-location-toggle>
                <span class="location-pill__pin">
                    <lord-icon
                        src="https://cdn.lordicon.com/rbsqvtgo.json"
                        trigger="hover"
                        stroke="bold"
                        colors="primary:#000000,secondary:#e86830"
                        style="width:18px;height:18px">
                    </lord-icon>
                </span>
                <span class="location-pill__label">
                    <strong>{{ $selectedCity ?? 'All India' }}</strong>
                    <small>{{ $selectedCity ? 'India' : 'Choose city' }}</small>
                </span>
            </a>

            <form action="/search" method="GET" class="topbar__search" role="search">
                <span class="search-icon">
                    <lord-icon
                        src="https://cdn.lordicon.com/wjyqkiew.json"
                        trigger="hover"
                        stroke="bold"
                        colors="primary:#000000,secondary:#e86830"
                        style="width:20px;height:20px">
                    </lord-icon>
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
                            <img src="https://cdn-icons-png.flaticon.com/512/2815/2815428.png" alt="Profile" class="topbar__account" />
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
    <footer class="mfoot">
        <div class="mfoot__app">
            <div>
                <strong>Take Haraan with you</strong>
                <p>Book faster, follow live scores, and keep your tickets in your pocket.</p>
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
            <a href="/events">Events</a>
            <a href="/gamehub">GameHub</a>
            <a href="/support">Support</a>
            <a href="/notifications">Notifications</a>
            <a href="/profile">My profile</a>
        </nav>
        <div class="mfoot__base">
            <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan">
            <span>Discover. Book. Play.</span>
            <small>© {{ date('Y') }} Haraan. All rights reserved.</small>
        </div>
    </footer>
    @endif

    <!-- Premium Login Modal -->
    <div id="loginModal" class="auth-modal" aria-hidden="true">
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

                    {{-- Google first: one tap for returning users, no OTP round-trip.
                         Hidden entirely when GOOGLE_CLIENT_ID isn't set, so a missing
                         config shows nothing rather than a button that always fails. --}}
                    @if(config('services.google.client_id'))
                        <div class="auth-google">
                            <div id="googleSignInBtn" class="auth-google__btn"></div>
                            <p class="auth-google__error" id="googleSignInError" role="alert" hidden></p>
                        </div>
                        <div class="auth-divider"><span>or</span></div>
                    @endif

                    <form class="phone-form" id="phoneLoginForm" method="POST" action="{{ route('whatsapp.request') }}">
                        @csrf
                        <input type="hidden" name="phone" id="hiddenPhoneField" value="">
                        <div class="phone-input-group">
                            <div class="country-selector">
                                <img src="https://flagcdn.com/w20/in.png" alt="India">
                                <span>+91</span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </div>
                            <input type="tel" placeholder="Enter mobile number" id="phoneNumber" maxlength="10">
                        </div>
                        
                        <button type="submit" class="btn btn--solid btn--full btn--large">Continue</button>
                    </form>

                    <div class="auth-modal__footer">
                        <p>By continuing, you agree to our <br> <a href="#">Terms of Service</a> &nbsp; <a href="#">Privacy Policy</a></p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Location selector modal (improved) -->
    <div id="locationModal" class="location-modal" aria-hidden="true">
        <div class="location-modal__backdrop" id="locationBackdrop"></div>
        <div class="location-modal__card" role="dialog" aria-modal="true" aria-label="Select Location">
            <div>
                <div class="location-modal__header">
                    <label class="location-search" style="flex:1">
                        <input type="search" id="locationSearch" placeholder="Search cities">
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;margin-left:8px">
                        <button id="useCurrent" class="btn btn--ghost">Use Current Location</button>
                        <button id="closeLocation" class="btn btn--ghost">Close</button>
                    </div>
                </div>

                <section style="margin-top:12px">
                    <h4 style="margin:8px 0">Popular Cities</h4>
                    <div id="popularCities" class="popular-grid" role="list"></div>
                </section>

                <section style="margin-top:8px">
                    <h4 style="margin:8px 0">All Cities</h4>
                    <div id="allCities" class="all-list" role="list"></div>
                </section>
            </div>

            <aside class="alpha-index" aria-hidden="false" id="alphaIndex" title="Jump to letter"></aside>
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
