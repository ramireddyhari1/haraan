<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PartnerAppShell;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The fast phone app: /partner/app and /partner/app/{screen}.
 *
 * One light page (no Filament console underneath) that loads the app's screens once
 * and then switches between them in place, the way the Android app does. Every path
 * under /partner/app returns this same page; the script draws the screen the path
 * names, so a refresh or a shared link lands on the right screen.
 *
 * Behind /control → Platform rules → Partner web app → "Fast phone app". Off, or for
 * anyone not signed in to the partner console, it steps aside to the console.
 */
final class PartnerWebAppController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        // Partner pages answer "can this partner open me?" against the partner panel.
        Filament::setCurrentPanel(Filament::getPanel('partner'));

        if (! PartnerAppShell::fastAppOn()) {
            return redirect('/partner');
        }

        $config = PartnerAppShell::config(spa: true);
        if ($config === null) {
            return redirect()->guest(Filament::getPanel('partner')->getLoginUrl());
        }

        return response()
            ->view('partner.app', ['config' => $config])
            // The page carries a short-lived token; never let a shared cache keep it.
            ->header('Cache-Control', 'no-store, private');
    }
}
