<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->login()
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
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
                Authenticate::class,
            ]);
    }
}
