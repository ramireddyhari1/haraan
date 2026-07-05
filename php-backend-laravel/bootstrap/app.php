<?php

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
        $middleware->encryptCookies(except: ['haraan_city']);

        $middleware->alias([
            'auth.jwt'         => \App\Http\Middleware\EnsureJwtAuthenticated::class,
            'auth.jwt.optional' => \App\Http\Middleware\OptionalJwtAuthenticated::class,
            'auth.partner'     => \App\Http\Middleware\EnsurePartner::class,
            'partner.can'      => \App\Http\Middleware\EnsurePartnerPermission::class,
            'erp.key'          => \App\Http\Middleware\EnsureErpPortalKey::class,
            'actionboard.profile' => \App\Http\Middleware\EnsureActionboardProfile::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('site.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
