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
 * Partners sign out of the /partner console back onto the panel's own branded
 * sign-in page (/partner/login), not the public website login. Every panel keeps
 * Filament's default behaviour (its own login page).
 */
class PartnerLogoutResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            return redirect()->to(route('filament.partner.auth.login'));
        }

        // Default Filament behaviour for the other panels.
        return redirect()->to(
            Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl(),
        );
    }
}
