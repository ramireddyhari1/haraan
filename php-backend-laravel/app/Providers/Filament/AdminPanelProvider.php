<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Pages\Dashboard;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('control')
            ->path('control')
            ->brandName('Haraan Control')
            ->brandLogo(asset('images/haraan-logo.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('images/haraan-logo.png'))
            // Inter across the whole panel — the single biggest lift from stock
            // Filament's system font. Compiled theme (viteTheme) carries the rest
            // of the design system so tables/forms/dashboard inherit it too.
            ->font('Inter')
            ->viteTheme('resources/css/filament/control/theme.css')
            ->login()
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
            // Haraan logo in the mobile topbar (the sidebar brand is hidden behind
            // the hamburger below the lg breakpoint). The partial reveals itself
            // only on mobile via CSS.
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.topbar-brand')->render(),
            )
            ->plugin(FilamentShieldPlugin::make());
    }
}
