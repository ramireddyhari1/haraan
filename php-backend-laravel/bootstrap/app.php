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
        $middleware->alias([
            'auth.jwt'         => \App\Http\Middleware\EnsureJwtAuthenticated::class,
            'auth.jwt.optional' => \App\Http\Middleware\OptionalJwtAuthenticated::class,
            'erp.key'          => \App\Http\Middleware\EnsureErpPortalKey::class,
            'actionboard.profile' => \App\Http\Middleware\EnsureActionboardProfile::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('site.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
