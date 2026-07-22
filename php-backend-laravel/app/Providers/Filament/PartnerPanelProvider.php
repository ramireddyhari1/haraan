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
                    <img src="{{ asset('images/haraan-logo.png') }}" alt="Haraan Partner">
                    <span class="hrn-topbar-tag">partner</span>
                </a>
                <style>
                    .hrn-topbar-logo{display:none;flex-direction:column;align-items:center;
                        justify-content:center;height:100%;line-height:1;text-decoration:none;}
                    .hrn-topbar-logo img{height:1.5rem;width:auto;display:block;}
                    /* Handwritten "partner" tucked under the Haraan wordmark. */
                    .hrn-topbar-tag{font-family:"Segoe Script","Bradley Hand","Snell Roundhand",
                        "Brush Script MT","Comic Sans MS",cursive;
                        font-size:12px;line-height:1;color:#2f6bff;margin-top:2px;
                        letter-spacing:.02em;transform:rotate(-3deg);}
                    .dark .hrn-topbar-tag{color:#7fb0ff;}
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
                <style>
                    .fi-sidebar-header{flex-wrap:wrap;}
                    .fi-sidebar-header-logo-ctn{flex:0 0 auto;}
                    .hrn-sidebar-tag{flex-basis:100%;font-size:13px;line-height:1;
                        color:#2f6bff;margin-top:3px;letter-spacing:.02em;
                        font-family:"Segoe Script","Bradley Hand","Snell Roundhand",
                        "Brush Script MT","Comic Sans MS",cursive;
                        transform:rotate(-3deg);transform-origin:left center;}
                    .dark .hrn-sidebar-tag{color:#7fb0ff;}
                </style>
            BLADE),
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
            ->brandLogo(asset('images/haraan-logo.png'))
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
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\Partner\PartnerEarnings::class,
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
