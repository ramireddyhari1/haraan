<?php

use App\Http\Middleware\EnforcePlatformOperations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The header city pill is set client-side (document.cookie) and read
        // server-side to scope listings, so it must not be encrypted.
        $middleware->encryptCookies(except: ['haraan_city', 'hb_geo']);

        // ETag/304 on API GETs so the app's auto-refresh polls re-download nothing
        // when data is unchanged. Backward-compatible (adds a header; 304 only when
        // the client opts in via If-None-Match).
        $middleware->api(append: [
            \App\Http\Middleware\SetConditionalHeaders::class,
        ]);

        // /control → Operations: maintenance mode and the minimum app version, enforced on
        // the server for the public site and the app API (staff consoles stay open).
        $middleware->api(prepend: [EnforcePlatformOperations::class]);
        $middleware->web(append: [EnforcePlatformOperations::class]);

        // Membership device limits for signed-in browsers (the app's are in auth.jwt).
        $middleware->web(append: [\App\Http\Middleware\EnforceMemberDevice::class]);

        $middleware->alias([
            'auth.jwt'         => \App\Http\Middleware\EnsureJwtAuthenticated::class,
            'auth.jwt.optional' => \App\Http\Middleware\OptionalJwtAuthenticated::class,
            'auth.partner'     => \App\Http\Middleware\EnsurePartner::class,
            'auth.admin'       => \App\Http\Middleware\EnsureSuperAdmin::class,
            'partner.can'      => \App\Http\Middleware\EnsurePartnerPermission::class,
            'erp.key'          => \App\Http\Middleware\EnsureErpPortalKey::class,
            'actionboard.profile' => \App\Http\Middleware\EnsureActionboardProfile::class,
            'member.entitled'  => \App\Http\Middleware\EnsureMemberEntitled::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('site.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
