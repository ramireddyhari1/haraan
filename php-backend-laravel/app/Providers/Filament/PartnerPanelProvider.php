<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Responses\PartnerLogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use App\Filament\Auth\PartnerLogin;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The partner-facing console for event hosts and venue owners.
 *
 * It reuses the very same resources, clusters and pages as the admin "control"
 * panel — no duplicate CRUD. Two mechanisms keep partners in their lane:
 *
 *   1. Every resource self-gates via canAccess()/canManage(), so partners only
 *      ever see the Events and GameHub (venue) clusters; People, Finance,
 *      Marketing and System resources return false and stay hidden.
 *   2. ScopesToOrganization restricts each query to the partner's own records
 *      (partner_id = auth id) whenever the current panel is "partner".
 *
 * FilamentShield is intentionally omitted here — access is driven purely by the
 * PARTNER role rather than fine-grained admin permissions.
 */
class PartnerPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        // Send partners back to the website login area on logout, instead of the
        // bare Filament panel "Sign in" page (the response self-scopes to /partner).
        $this->app->bind(LogoutResponse::class, PartnerLogoutResponse::class);

        // BookMyShow-style split brand panel on the left of the partner sign-in
        // screen. Scoped to PartnerLogin so /control — which shares the same
        // compiled theme — and every other simple page stay untouched.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_LAYOUT_START,
            fn (): string => Blade::render('@include(\'filament.partner.auth-brand\')'),
            scopes: PartnerLogin::class,
        );

        // The brand logo lives in the sidebar header, which is collapsed behind the
        // hamburger on mobile — so on a phone the console opened with no Haraan mark
        // at all. Paint it into the top bar too, but only below the desktop breakpoint
        // (where the sidebar logo already shows), so it never doubles up. On mobile
        // it's absolutely centred, so the layout reads: ☰ (far left) · Haraan (centre)
        // · search + profile (right).
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_START,
            fn (): string => Blade::render(<<<'BLADE'
                <a href="{{ filament()->getUrl() }}" class="hrn-topbar-logo" aria-label="Haraan Partner">
                    <img src="{{ asset('images/haraan-logo-blue.png') }}" alt="Haraan Partner">
                    <span class="hrn-topbar-tag">partner</span>
                </a>
                <style>
                    .hrn-topbar-logo{display:none;flex-direction:column;align-items:center;
                        justify-content:center;height:100%;line-height:1;text-decoration:none;}
                    .hrn-topbar-logo img{height:1.5rem;width:auto;display:block;}
                    /* Handwritten "partner" tucked under the Haraan wordmark. */
                    .hrn-topbar-tag{font-family:"Segoe Script","Bradley Hand","Snell Roundhand",
                        "Brush Script MT","Comic Sans MS",cursive;
                        font-size:12px;line-height:1;color:#0b1220;margin-top:2px;
                        letter-spacing:.02em;transform:rotate(-3deg);}
                    .dark .hrn-topbar-tag{color:#e6e9ef;}
                    @media (max-width:1023px){
                        .fi-topbar{position:relative;}
                        /* Centre the logo over the bar; the hamburger falls to the far
                           left in flow, the search icon + profile group sits on the right. */
                        .hrn-topbar-logo{display:flex;position:absolute;left:50%;top:50%;
                            transform:translate(-50%,-50%);z-index:5;margin:0;pointer-events:auto;}
                    }
                </style>
            BLADE),
        );

        // Desktop twin of the mobile tag: a handwritten "partner" under the Haraan
        // wordmark in the sidebar header. The header is a flex row holding just the
        // logo (its collapse buttons are skipped when a topbar exists), so flex-wrap
        // + a full-basis tag drops it onto its own line directly beneath the logo.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_LOGO_AFTER,
            fn (): string => Blade::render(<<<'BLADE'
                <span class="hrn-sidebar-tag">partner</span>
                <span class="hrn-sidebar-tag hrn-sidebar-brand">Haraan</span>
                <style>
                    .fi-sidebar-header{flex-wrap:wrap;}
                    .fi-sidebar-header-logo-ctn{flex:0 0 auto;}
                    .hrn-sidebar-tag{flex-basis:100%;font-size:13px;line-height:1;
                        color:#0b1220;margin-top:3px;letter-spacing:.02em;
                        font-family:"Segoe Script","Bradley Hand","Snell Roundhand",
                        "Brush Script MT","Comic Sans MS",cursive;
                        transform:rotate(-3deg);transform-origin:left center;}
                    /* Handwritten "Haraan" tucked under "partner", black. */
                    .hrn-sidebar-brand{font-size:15px;margin-top:4px;}
                    .dark .hrn-sidebar-tag{color:#e6e9ef;}
                    @media (max-width:1023px){
                        /* Mobile slide-out drawer: the fixed-height header can't fit the
                           logo + two handwritten lines, so it clipped the wordmark's top.
                           Drop the 2nd "Haraan" line here and let the header grow with a
                           little breathing room up top so the logo sits clean. */
                        .hrn-sidebar-brand{display:none!important;}
                        .fi-sidebar-header{height:auto;min-height:0;
                            padding-top:1rem;padding-bottom:.6rem;align-content:flex-start;}
                    }
                </style>
            BLADE),
        );

        // Premium sidebar pass: an identity card pinned to the footer (who + which
        // workspace + quiet sign-out) plus a nav polish sheet — accent-rail active
        // state, more breathing room, and clearer section labels.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn (): string => Blade::render(<<<'BLADE'
                @php
                    $u = auth()->user();
                    $name = $u?->name ?: 'Partner';
                    $parts = preg_split('/\s+/', trim($name)) ?: [$name];
                    $init = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
                    $init = $init !== '' ? $init : 'P';
                    $hue = crc32($name) % 360;
                    $lane = ($u?->partner_type === 'event') ? 'Event organiser' : 'Venue owner';
                    $photo = \App\Support\MediaUrl::resolve($u?->avatar);
                    $profileUrl = \Filament\Facades\Filament::getProfileUrl();
                    $tag = $profileUrl ? 'a' : 'span';
                @endphp
                <div class="hrn-acct">
                    <{{ $tag }} @if ($profileUrl) href="{{ $profileUrl }}" @endif class="hrn-acct-link" title="View profile">
                        @if ($photo)
                            <img src="{{ $photo }}" alt="{{ $name }}" class="hrn-acct-av hrn-acct-av-img">
                        @else
                            <span class="hrn-acct-av" style="background:hsl({{ $hue }} 52% 46%)">{{ $init }}</span>
                        @endif
                        <span class="hrn-acct-meta">
                            <span class="hrn-acct-name">{{ $name }}</span>
                            <span class="hrn-acct-lane">{{ $lane }}</span>
                        </span>
                    </{{ $tag }}>
                    <form method="POST" action="{{ route('filament.partner.auth.logout') }}" class="hrn-acct-form">
                        @csrf
                        <button type="submit" class="hrn-acct-out" title="Sign out" aria-label="Sign out">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 17l5-5-5-5M20 12H9M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/>
                            </svg>
                        </button>
                    </form>
                </div>
                <style>
                    /* ---- account card ---- */
                    .hrn-acct{display:flex;align-items:center;gap:8px;margin:8px;padding:9px 10px;
                        border-radius:13px;background:#f4f7fb;box-shadow:inset 0 0 0 1px #e9edf4;}
                    .hrn-acct-link{display:flex;align-items:center;gap:10px;flex:1;min-width:0;
                        text-decoration:none;border-radius:9px;}
                    .hrn-acct-av{width:34px;height:34px;border-radius:50%;flex:none;display:flex;
                        align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;
                        letter-spacing:.02em;overflow:hidden;}
                    .hrn-acct-av-img{object-fit:cover;display:block;}
                    .hrn-acct-meta{min-width:0;flex:1;display:flex;flex-direction:column;line-height:1.2;}
                    .hrn-acct-link:hover .hrn-acct-name{color:#1e50e6;}
                    .dark .hrn-acct-link:hover .hrn-acct-name{color:#7fb0ff;}
                    .hrn-acct-name{font-size:13px;font-weight:700;color:#0b1220;white-space:nowrap;
                        overflow:hidden;text-overflow:ellipsis;}
                    .hrn-acct-lane{font-size:11px;color:#7a8394;margin-top:1px;}
                    .hrn-acct-form{margin:0;flex:none;}
                    .hrn-acct-out{display:flex;align-items:center;justify-content:center;width:30px;height:30px;
                        border-radius:9px;color:#9aa2b1;background:transparent;border:0;cursor:pointer;
                        transition:background .15s,color .15s;}
                    .hrn-acct-out:hover{background:#e7ebf2;color:#c2410c;}
                    .hrn-acct-out svg{width:17px;height:17px;}
                    .dark .hrn-acct{background:#141b28;box-shadow:inset 0 0 0 1px #1e2633;}
                    .dark .hrn-acct-name{color:#eef1f6;} .dark .hrn-acct-lane{color:#8b94a5;}
                    .dark .hrn-acct-out:hover{background:#1e2633;color:#fb923c;}

                    /* ---- nav polish ---- */
                    .fi-sidebar-nav .fi-sidebar-item-btn{padding-top:.5rem;padding-bottom:.5rem;border-radius:11px;}
                    .fi-sidebar-nav .fi-sidebar-item-label{font-weight:500;}
                    .fi-sidebar-nav .fi-sidebar-group-label{font-size:10.5px;letter-spacing:.09em;
                        font-weight:700;text-transform:uppercase;opacity:.8;}
                    /* accent-rail active state */
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn{position:relative;
                        background:rgba(47,107,255,.11);font-weight:700;}
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::before{content:"";position:absolute;
                        inset-inline-start:5px;top:20%;bottom:20%;width:3px;border-radius:3px;
                        background:linear-gradient(#3b82f6,#1e50e6);}
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label{color:#1e50e6;font-weight:700;}
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon{color:#1e50e6;}
                    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn{background:rgba(59,130,246,.16);}
                    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
                    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon{color:#7fb0ff;}
                </style>
            BLADE),
        );

        // A prominent, lane-aware "+ Create" CTA at the top of the nav so the console
        // opens on an action, not just a menu. Uses the resource's own canCreate()
        // gate, so a desk person without listings access never sees it.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): string => Blade::render(<<<'BLADE'
                @php
                    $isEvent = auth()->user()?->partner_type === 'event';
                    if ($isEvent) {
                        $url = \App\Filament\Resources\Events\EventResource::canCreate()
                            ? \App\Filament\Resources\Events\EventResource::getUrl('create') : null;
                        $label = 'Create event';
                    } else {
                        $url = \App\Filament\Resources\Venues\VenueResource::canCreate()
                            ? \App\Filament\Resources\Venues\VenueResource::getUrl('create') : null;
                        $label = 'Add venue';
                    }
                @endphp
                @if ($url)
                    <a href="{{ $url }}" class="hrn-create-cta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        <span>{{ $label }}</span>
                    </a>
                    <style>
                        .hrn-create-cta{display:flex;align-items:center;justify-content:center;gap:8px;
                            margin:2px 8px 12px;padding:10px 14px;border-radius:12px;text-decoration:none;
                            background:linear-gradient(180deg,#2f6bff,#1e50e6);color:#fff;
                            font-size:13.5px;font-weight:600;letter-spacing:.01em;
                            box-shadow:0 8px 18px -8px rgba(37,99,235,.6);transition:filter .15s,transform .05s;}
                        .hrn-create-cta:hover{filter:brightness(1.06);}
                        .hrn-create-cta:active{transform:translateY(1px);}
                        .hrn-create-cta svg{width:17px;height:17px;}
                    </style>
                @endif
            BLADE),
        );

        // Dashboard: a warm greeting + a compact, right-aligned period control.
        // The control itself is the page's global filters form (one Select that
        // drives every money widget); this hook only adds the greeting row and the
        // CSS that shrinks the full-width form field into a growezy-style pill in
        // the top-right. Scoped to the Dashboard page and guarded to the partner
        // panel, so /control is never touched.
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_START,
            function (): string {
                if (\Filament\Facades\Filament::getCurrentPanel()?->getId() !== 'partner') {
                    return '';
                }

                return Blade::render(<<<'BLADE'
                    @php
                        $h = (int) now()->format('G');
                        $part = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
                        $first = \Illuminate\Support\Str::of(auth()->user()?->name ?? '')->trim()->explode(' ')->first();
                    @endphp
                    <div class="hrn-dash-hi">
                        <div>
                            <h2 class="hrn-dash-hi-h">{{ $part }}{{ $first ? ', ' . $first : '' }}</h2>
                            <p class="hrn-dash-hi-sub">Here's how your business is doing.</p>
                        </div>
                    </div>
                    <style>
                        .hrn-dash-hi{display:flex;align-items:flex-end;justify-content:space-between;
                            gap:1rem;margin-bottom:.25rem;}
                        .hrn-dash-hi-h{font-size:1.35rem;font-weight:800;letter-spacing:-.01em;
                            color:#0b1220;line-height:1.15;}
                        .hrn-dash-hi-sub{font-size:.85rem;color:#7a8394;margin-top:2px;}
                        .dark .hrn-dash-hi-h{color:#eef1f6;} .dark .hrn-dash-hi-sub{color:#8b94a5;}

                        /* Shrink the page filters form into a compact, right-aligned
                           period pill that visually sits beside the greeting. */
                        [wire\:partial="table-filters-form"]{display:flex;justify-content:flex-end;
                            margin-top:-3.4rem;margin-bottom:1rem;position:relative;z-index:1;}
                        [wire\:partial="table-filters-form"] > *{width:auto;min-width:11rem;max-width:15rem;}
                        [wire\:partial="table-filters-form"] .fi-fo-field-wrp-label{font-size:.7rem;
                            text-transform:uppercase;letter-spacing:.07em;font-weight:700;opacity:.7;}
                        @media (max-width:640px){
                            [wire\:partial="table-filters-form"]{margin-top:.25rem;justify-content:stretch;}
                            [wire\:partial="table-filters-form"] > *{width:100%;max-width:none;}
                        }
                    </style>
                BLADE);
            },
            scopes: \App\Filament\Pages\Dashboard::class,
        );

        // Mobile: collapse the global search into a magnifier icon that sits beside the
        // profile menu; tapping it drops the real search field down as a full-width bar
        // under the top bar (and auto-focuses it). Desktop keeps the inline search field.
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            fn (): string => Blade::render(<<<'BLADE'
                <button type="button" class="hrn-search-btn" aria-label="Search"
                        onclick="window.hrnToggleSearch(event)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20.5 20.5-4.2-4.2"/>
                    </svg>
                </button>
                <style>
                    .hrn-search-btn{display:none;align-items:center;justify-content:center;
                        width:2.25rem;height:2.25rem;border:0;background:transparent;color:inherit;
                        cursor:pointer;border-radius:.6rem;padding:0;}
                    .hrn-search-btn:hover{background:rgba(120,130,150,.14);}
                    .hrn-search-btn svg{width:1.35rem;height:1.35rem;}
                    @media (max-width:1023px){
                        .fi-topbar-ctn{position:relative;}
                        .hrn-search-btn{display:inline-flex;}
                        /* Hide the inline search field until the icon opens it. */
                        .fi-topbar .fi-global-search-ctn{display:none;}
                        html.hrn-search-open .fi-topbar .fi-global-search-ctn{
                            display:block;position:absolute;left:0;right:0;top:100%;z-index:40;
                            padding:.6rem .8rem;
                            background:var(--fi-color-white,#fff);
                            border-top:1px solid rgba(120,130,150,.18);
                            box-shadow:0 14px 26px -16px rgba(0,0,0,.4);}
                        html.hrn-search-open .fi-topbar .fi-global-search-field .fi-input-wrp{width:100%;}
                        html.hrn-search-open .hrn-search-btn{color:var(--fi-color-primary-600,#2563eb);}
                    }
                    @media (min-width:1024px){.hrn-search-btn{display:none!important;}}
                    .dark html.hrn-search-open .fi-topbar .fi-global-search-ctn,
                    :is(.dark) html.hrn-search-open .fi-topbar .fi-global-search-ctn{
                        background:var(--fi-color-gray-900,#111722);border-top-color:rgba(120,130,150,.24);}
                </style>
                <script>
                    (function () {
                        if (window.hrnToggleSearch) return; // guard against SPA re-inits
                        window.hrnToggleSearch = function (e) {
                            if (e) { e.preventDefault(); e.stopPropagation(); }
                            var open = document.documentElement.classList.toggle('hrn-search-open');
                            if (open) {
                                requestAnimationFrame(function () {
                                    var inp = document.querySelector('.fi-topbar .fi-global-search input[type=search]');
                                    if (inp) inp.focus();
                                });
                            }
                        };
                        document.addEventListener('click', function (e) {
                            if (!document.documentElement.classList.contains('hrn-search-open')) return;
                            if (e.target.closest('.hrn-search-btn') || e.target.closest('.fi-global-search-ctn')) return;
                            document.documentElement.classList.remove('hrn-search-open');
                        });
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') document.documentElement.classList.remove('hrn-search-open');
                        });
                    })();
                </script>
            BLADE),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('partner')
            ->path('partner')
            ->brandName('Haraan Partner')
            // Blue wordmark for the partner console (matches the "+ Create event"
            // CTA); /control keeps the navy mark via its own provider.
            ->brandLogo(asset('images/haraan-logo-blue.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('images/haraan-logo.png'))
            // Same design system as /control — Inter + the compiled theme. The
            // theme styles panel-agnostic fi-* hooks and follows each panel's own
            // primary colour, so the blue lane stays blue.
            ->font('Inter')
            ->viteTheme('resources/css/filament/control/theme.css')
            ->login(PartnerLogin::class)
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            // Day theme only — no dark mode, so the profile menu's light/dark/system
            // switch disappears and the console always renders on the light palette.
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\Partner\PartnerEarnings::class,
                \App\Filament\Pages\Partner\PartnerPublicProfile::class,
                \App\Filament\Pages\Partner\PartnerSupport::class,
                \App\Filament\Pages\Partner\PartnerNotifications::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // Same 403 as Filament's, but with a way out (see the middleware).
                \App\Http\Middleware\AuthenticateFilamentPanel::class,
            ]);
    }
}
