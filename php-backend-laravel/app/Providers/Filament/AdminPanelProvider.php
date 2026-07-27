<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Pages\Dashboard;
use App\Filament\Auth\ControlLogin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        // BookMyShow-style split brand panel (blue aurora) on the left of the
        // /control sign-in screen — the admin twin of the partner console's
        // PartnerLogin. Scoped to ControlLogin so the rest of the panel (and the
        // shared compiled theme) stay untouched.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_LAYOUT_START,
            fn (): string => Blade::render('@include(\'filament.control.auth-brand\')'),
            scopes: ControlLogin::class,
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('control')
            ->path('control')
            ->brandName('Haraan Control')
            ->brandLogo(asset('images/haraan-logo.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('favicon-192.png'))
            // Inter across the whole panel — the single biggest lift from stock
            // Filament's system font. Compiled theme (viteTheme) carries the rest
            // of the design system so tables/forms/dashboard inherit it too.
            ->font('Inter')
            ->viteTheme('resources/css/filament/control/theme.css')
            ->login(ControlLogin::class)
            ->profile()
            ->multiFactorAuthentication([
                \Filament\Auth\MultiFactor\App\AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Haraan Control'),
            ])
            ->colors([
                'primary' => Color::Green,
                'gray' => Color::Slate,
            ])
            // Deliberate sidebar order with group icons. Without this Filament
            // renders groups in discovery order (effectively random); a stable,
            // labelled hierarchy is most of what makes a console read "organized".
            // Day-to-day content up top; admin/plumbing collapsed at the bottom.
            ->navigationGroups([
                NavigationGroup::make('App Content')
                    ->icon('heroicon-o-rectangle-group'),
                NavigationGroup::make('People')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make('Platform')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
                NavigationGroup::make('System')
                    ->icon('heroicon-o-server-stack')
                    ->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ])
            // Real-time refresh: subscribe the panel to the Reverb "content" channel so
            // dashboard widgets + live pages update in seconds when content changes. No-op
            // unless BROADCAST_CONNECTION=reverb (the partial guards itself).
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.realtime-head')->render(),
            )
            // Shared design system: one source of truth for the panel's custom
            // design tokens (--hrn-*) and reusable component classes (.hrn-*),
            // so custom pages/widgets stop redefining their own palettes inline.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.theme')->render(),
            )
            // Premium visual theme for the Event create/edit wizard — scoped to those
            // two pages so it restyles the ticketing/authoring experience without
            // touching the rest of the panel. See the blade for the exact overrides.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.forms.event-form-theme')->render(),
                scopes: [
                    \App\Filament\Resources\Events\Pages\CreateEvent::class,
                    \App\Filament\Resources\Events\Pages\EditEvent::class,
                ],
            )
            // Haraan logo in the mobile topbar (the sidebar brand is hidden behind
            // the hamburger below the lg breakpoint). The partial reveals itself
            // only on mobile via CSS.
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.topbar-brand')->render(),
            )
            // Sidebar footer identity card (who + role + quiet sign-out), the
            // control twin of the partner console's account card. Fills the
            // previously-empty footer so the shell reads finished.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.account-card')->render(),
            )
            // Dashboard hero band: a time-aware greeting that leads the page (and
            // hides the redundant "Dashboard" H1), the control twin of the partner
            // console's launchpad. Scoped to the Dashboard page — Command Center
            // users are redirected away, so only limited staff (marketing/ops) see it.
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): string => view('filament.dashboard-hero')->render(),
                scopes: Dashboard::class,
            )
            ->plugin(FilamentShieldPlugin::make());
    }
}
