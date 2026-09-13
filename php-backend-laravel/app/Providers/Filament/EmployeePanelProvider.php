<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\EmployeeLogin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class EmployeePanelProvider extends PanelProvider
{

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('employee')
            ->path('employee')
            ->brandName('Haraan Employee')
            ->brandLogo(asset('images/haraan-logo.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('favicon-192.png'))
            ->font('Inter')
            ->viteTheme('resources/css/filament/control/theme.css')
            ->login(EmployeeLogin::class)
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
            ])
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->pages([
                \App\Filament\Pages\Employee\EmployeeDashboard::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages/Employee'), for: 'App\\Filament\\Pages\\Employee')
            ->discoverWidgets(in: app_path('Filament/Widgets/Employee'), for: 'App\\Filament\\Widgets\\Employee')
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
                \App\Http\Middleware\AuthenticateFilamentPanel::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.theme')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.topbar-brand')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.account-card')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.employee.shift-handover-guard')->render(),
            );
    }
}
