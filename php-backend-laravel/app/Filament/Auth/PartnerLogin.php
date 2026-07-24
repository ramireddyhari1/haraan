<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Partner-panel sign-in.
 *
 * Identical auth logic to Filament's base Login — we only re-word the heading
 * and subheading so the right-hand form column reads as a partner console door
 * rather than a generic "Sign in". The BookMyShow-style split brand panel on
 * the left is injected by the SIMPLE_LAYOUT_START render hook registered in
 * PartnerPanelProvider (see resources/views/filament/partner/auth-brand.blade.php);
 * everything here stays panel-agnostic so password-reset inherits the same shell.
 */
class PartnerLogin extends BaseLogin
{
    // Browser <title>. Leads with the brand so a search for "haraan partner"
    // resolves here; this page is intentionally indexable (unlike the console
    // behind it) — see the HEAD_END render hook + robots.txt in
    // PartnerPanelProvider.
    public function getTitle(): string | Htmlable
    {
        return 'Haraan Partner Login — Hosts & Venue Owners';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Partner sign in';
    }

    public function getSubHeading(): string | Htmlable | null
    {
        return new HtmlString(
            'For hosts &amp; venue owners — manage events, bookings &amp; earnings.'
        );
    }
}
