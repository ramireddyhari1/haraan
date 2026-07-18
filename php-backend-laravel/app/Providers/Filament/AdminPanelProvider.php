<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Pages\Dashboard;
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
                Authenticate::class,
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
            ->plugin(FilamentShieldPlugin::make());
    }
}
