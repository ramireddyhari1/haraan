<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PartnerAppShell;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With the fast phone app on, a phone opening the console's Home (/partner, which is
 * also where an iPhone Home Screen icon saved earlier opens) is sent straight to
 * /partner/app, before the server spends time drawing a console it would hide.
 *
 * Phones are told apart by their user agent here; the page also carries a width check
 * (PartnerPanelProvider) for anything the agent misses. `?console=1` keeps the console.
 */
final class SendPhonesToFastPartnerApp
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->header('X-Livewire')
            && ! $request->boolean('console')
            && $request->routeIs('filament.partner.pages.dashboard')
            && preg_match('/iPhone|iPod|Android.+Mobile|Mobile.+Android/i', (string) $request->userAgent())
            && PartnerAppShell::fastAppOn()
            && PartnerAppShell::user() !== null
        ) {
            return redirect(PartnerAppShell::SPA_PATH);
        }

        return $next($request);
    }
}
