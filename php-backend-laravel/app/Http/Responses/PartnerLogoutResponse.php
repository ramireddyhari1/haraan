<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Logout redirect for the Filament panels.
 *
 * Partners reach the /partner console through the public website login modal, so
 * on logout they should land back on that same website login area (/login) — not
 * the bare Filament "Sign in" page for the panel. Every other panel keeps
 * Filament's default behaviour (its own login page).
 */
class PartnerLogoutResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            return redirect()->to(route('site.login'));
        }

        // Default Filament behaviour for the other panels.
        return redirect()->to(
            Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl(),
        );
    }
}
