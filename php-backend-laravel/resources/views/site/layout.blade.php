<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'Haraan' }}</title>
    <meta name="theme-color" content="#f3f5f7">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Inter:wght@300..800&display=swap" rel="stylesheet">
    @php
        // Cache-bust assets by file mtime: browsers can cache them, but a new
        // deploy (changed file) yields a new URL. Beats ?v=time() which never caches.
        $assetVer = fn (string $p) => asset($p) . '?v=' . (is_file(public_path($p)) ? filemtime(public_path($p)) : '1');
    @endphp
    <link rel="stylesheet" href="{{ $assetVer('css/site.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('css/site-theme-overrides.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('css/site-mobile-overrides.css') }}" media="(max-width:1024px)">
    <script src="https://cdn.lordicon.com/lordicon.js"></script>
    <style>
        /* NB: the desktop mobile-UI-hide safety rules live in site.css
           (@media min-width:1025px) — not duplicated here. */

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

            <a class="location-pill" href="#" id="locationToggle">
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

    <main class="{{ request()->is('gamehub/actionboard/match/*') ? '' : 'container' }}" role="main">
        @yield('content')
    </main>

    <!-- Premium Login Modal -->
    <div id="loginModal" class="auth-modal" aria-hidden="true">
        <div class="auth-modal__backdrop" id="loginBackdrop"></div>
        <div class="auth-modal__card" role="dialog" aria-modal="true" aria-label="Login to Haraan">
            <button class="auth-modal__close" id="closeLogin" aria-label="Close login">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            
            <div class="auth-modal__header">
                <div class="auth-modal__logo">
                    <img src="{{ asset('bv-white.png') }}" alt="Haraan">
                    <h2>Haraan</h2>
                    <p>Experience the best in Dining, Movies, and Events.</p>
                </div>
            </div>

            <div class="auth-modal__body">
                @if(session('whatsapp_phone'))
                    <h3>Verify OTP</h3>
                    <p class="subtitle">Enter the 6-digit code sent to your WhatsApp.</p>
                    
                    @if(session('success'))
                        <div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="post" action="{{ route('whatsapp.verify.submit') }}" class="phone-form">
                        @csrf
                        <div class="field" style="margin-bottom: 15px;">
                            <input 
                                type="text" 
                                name="otp" 
                                placeholder="123456"
                                required
                                maxlength="6"
                                style="text-align: center; font-size: 24px; letter-spacing: 5px; width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"
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
                    <h3>Enter your mobile number</h3>
                    <p class="subtitle">If you don't have an account yet, we'll create one for you</p>
                    
                    @if(session('error'))
                        <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                            {{ session('error') }}
                        </div>
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
                        <input type="search" id="locationSearch" placeholder="Search city, area or locality">
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
