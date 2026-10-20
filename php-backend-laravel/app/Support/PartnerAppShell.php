<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/**
 * The partner Android app's shell, on the web: on a phone (or the console installed to
 * an iPhone Home Screen) the partner console wears the app's header, floating blue
 * bottom bar and drawer, and its Home is the app's Home.
 *
 * The screens are drawn by public/js/partner/app-shell.js from the same /api/partner/*
 * endpoints the Android app reads, so a number on the web is the number in the app —
 * there is no second calculation to drift. The page carries a short-lived app token for
 * the partner already signed in to the console; the script swaps it for a fresh one at
 * {@see self::TOKEN_ROUTE} when it runs out.
 *
 * Desktop keeps the full console: the shell's CSS only applies below the phone breakpoint.
 */
final class PartnerAppShell
{
    public const TOKEN_ROUTE = 'partner.app.token';

    /** Two hours: long enough for a shift at the desk, short enough that a leaked page goes stale. */
    public const TOKEN_TTL = 7200;

    /**
     * Where each of the app's destinations lives on the web today. A destination whose
     * page doesn't exist, or that this partner can't open, is left out — the shell never
     * draws a door that only says no.
     *
     * @var array<string, string>
     */
    private const DESTINATIONS = [
        'home' => 'filament.partner.pages.dashboard',
        'venues' => 'filament.partner.game-hub.resources.venues.index',
        'matches' => 'filament.partner.game-hub.resources.live-matches.index',
        'bookings' => 'filament.partner.game-hub.pages.day-bookings',
        'sales' => 'filament.partner.events.resources.bookings.index',
        'events' => 'filament.partner.events.resources.events.index',
        'payments' => 'filament.partner.pages.partner-earnings',
        'scan' => 'filament.partner.pages.scan',
        'operations' => 'filament.partner.game-hub.pages.game-hub-overview',
        'settlement' => 'filament.partner.game-hub.resources.shifts.shift-sessions.index',
        'pricing' => 'filament.partner.game-hub.pages.game-hub-pricing-rules',
        'standing' => 'filament.partner.pages.standing-slots',
        'packages' => 'filament.partner.pages.packages',
        'academy' => 'filament.partner.pages.academy',
        'customers' => 'filament.partner.game-hub.pages.game-hub-members',
        'staff' => 'filament.partner.resources.partner-staff.index',
        'payouts' => 'filament.partner.pages.partner-payouts',
        'reports' => 'filament.partner.game-hub.pages.reports',
        'notifications' => 'filament.partner.pages.partner-notifications',
        'support' => 'filament.partner.pages.partner-support',
        'settings' => 'filament.partner.auth.profile',
    ];

    /** Where the fast phone app lives (PartnerWebAppController). */
    public const SPA_PATH = '/partner/app';

    /**
     * Destinations the fast app draws itself (registered screens in public/js/partner).
     * Everything else (the Bookings day desk, Events, Settings) still opens its console
     * page until it is ported.
     *
     * @var list<string>
     */
    public const SPA_SCREENS = [
        'home', 'venues', 'matches', 'payments', 'scan', 'operations', 'settlement', 'pricing',
        'standing', 'packages', 'academy', 'customers', 'staff', 'payouts', 'reports',
        'notifications', 'support',
    ];

    /**
     * Config for public/js/partner/pwa.js: install guide, booking-alert push, service
     * worker. Shared by the console pages and the fast app.
     *
     * @return array<string, mixed>
     */
    public static function pwaConfig(): array
    {
        $user = auth()->user();

        return [
            'signedIn' => $user !== null && $user->canAccessPanel(\Filament\Facades\Filament::getPanel('partner')),
            'userId' => $user?->id,
            'csrf' => csrf_token(),
            'swUrl' => asset('partner-sw.js'),
            'subscribeUrl' => route('partner.push.subscribe'),
            'icon' => asset('partner-app/icon-180.png'),
            'installPrompt' => \App\Support\PlatformRules::bool('partner_web_app.install_prompt'),
            'reshowDays' => \App\Support\PlatformRules::int('partner_web_app.install_reshow_days'),
            'pushEnabled' => \App\Support\PlatformRules::bool('partner_web_app.push_enabled'),
            'pushPrompt' => \App\Support\PlatformRules::string('partner_web_app.push_prompt'),
            'vapidKey' => config('services.firebase.web_push_vapid_key') ?: null,
            'firebase' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'messagingSenderId' => config('services.firebase.messaging_sender_id'),
                'appId' => config('services.firebase.app_id'),
            ],
        ];
    }

    /** The admin switch in /control → Platform rules → Partner web app. */
    public static function fastAppOn(): bool
    {
        return PlatformRules::bool('partner_web_app.fast_app');
    }

    /** The signed-in partner, or null on the login page / for anyone else. */
    public static function user(): ?User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $panel = Filament::getPanel('partner');

        return $user->canAccessPanel($panel) ? $user : null;
    }

    public static function token(User $user): string
    {
        return JwtService::issueForUser($user, (string) config('app.jwt_secret'), self::TOKEN_TTL);
    }

    /**
     * @param  bool  $spa  true for the fast app page itself
     * @return array<string, mixed>|null
     */
    public static function config(bool $spa = false): ?array
    {
        $user = self::user();

        if ($user === null) {
            return null;
        }

        $logout = Route::has('filament.partner.auth.logout') ? route('filament.partner.auth.logout') : null;

        return [
            'token' => self::token($user),
            'tokenUrl' => route(self::TOKEN_ROUTE),
            'csrf' => csrf_token(),
            'branch' => PartnerBranchContext::currentId(),
            'name' => $user->name ?: 'Partner',
            'logout' => $logout,
            'branchUrl' => Route::has('partner.branch.switch') ? route('partner.branch.switch') : null,
            'logo' => asset('images/haraan-logo-blue.png'),
            'logoWhite' => asset('images/haraan-logo-white.png'),
            'shareBase' => url('/gamehub'),
            'urls' => self::urls(self::destinations(), $spa || self::fastAppOn()),
            'spa' => $spa,
            'spaUrl' => self::fastAppOn() ? url(self::SPA_PATH) : null,
        ];
    }

    /**
     * With the fast app on, every destination it draws points into it, so tapping Home
     * on a console page (the Bookings desk, say) comes back to the fast app instead of
     * the console. Destinations it can't draw yet keep their console page.
     *
     * @param  array<string, string>  $dest
     * @return array<string, string>
     */
    private static function urls(array $dest, bool $fast): array
    {
        if (! $fast) {
            return $dest;
        }
        foreach ($dest as $key => $url) {
            if (in_array($key, self::SPA_SCREENS, true)) {
                $dest[$key] = url(self::SPA_PATH . ($key === 'home' ? '' : '/' . $key));
            }
        }

        return $dest;
    }

    /** @return array<string, string> key => URL, for the destinations this partner can open. */
    private static function destinations(): array
    {
        $out = [];

        foreach (self::DESTINATIONS as $key => $name) {
            $route = Route::getRoutes()->getByName($name);

            if ($route === null) {
                continue;
            }

            $class = $route->getControllerClass();

            try {
                if ($class !== null && method_exists($class, 'canAccess') && ! $class::canAccess()) {
                    continue;
                }
                $out[$key] = route($name);
            } catch (\Throwable) {
                // A page that can't answer is a page we don't link to.
                continue;
            }
        }

        return $out;
    }
}
