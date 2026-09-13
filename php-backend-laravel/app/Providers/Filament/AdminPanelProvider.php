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
            ->darkModeBrandLogo(asset('images/haraan-logo-white.png'))
            ->brandLogoHeight('1.875rem')
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
                // The console's indigo primary is shared with the partner and
                // employee panels; green remains reserved for positive states.
                'primary' => Color::Indigo,
                'gray' => Color::Zinc,
                'info' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            // Linear / Stripe tier-based information architecture:
            // Core Operations (Events, Venues, Finance, Marketing) -> People & Directory ->
            // Workforce/HRMS -> Collapsed Platform & Security infrastructure.
            ->navigationGroups([
                NavigationGroup::make('Events')
                    ->icon('heroicon-o-ticket'),
                NavigationGroup::make('GameHub')
                    ->icon('heroicon-o-building-storefront'),
                NavigationGroup::make('Finance')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Marketing')
                    ->icon('heroicon-o-megaphone'),
                NavigationGroup::make('App Content')
                    ->icon('heroicon-o-rectangle-stack'),
                NavigationGroup::make('Support & Moderation')
                    ->icon('heroicon-o-chat-bubble-left-right'),
                NavigationGroup::make('People')
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Workforce & HR')
                    ->icon('heroicon-o-identification'),
                NavigationGroup::make('Platform')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsed(),
                NavigationGroup::make('System')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('20rem')
            ->collapsedSidebarWidth('4.5rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\CommandCenter::class,
                \App\Filament\Pages\BrandingSettings::class,
                \App\Filament\Pages\Cities::class,
                \App\Filament\Pages\MessagingUsagePage::class,
                \App\Filament\Pages\ServerStatus::class,
                \App\Filament\Pages\WhatsAppConnection::class,
            ])
            ->widgets([
                \App\Filament\Widgets\ActiveUsersTrendWidget::class,
                \App\Filament\Widgets\ActiveUsersWidget::class,
                \App\Filament\Widgets\HaraanStatsWidget::class,
                \App\Filament\Widgets\LatestBookingsWidget::class,
                \App\Filament\Widgets\RevenueOverviewWidget::class,
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
