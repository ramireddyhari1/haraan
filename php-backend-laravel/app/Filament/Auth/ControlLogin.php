<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Admin-panel (/control) sign-in.
 *
 * Identical auth logic to Filament's base Login — we only re-word the heading
 * and subheading so the right-hand form column reads as the internal control
 * plane rather than a generic "Sign in". The BookMyShow-style split brand panel
 * on the left (blue aurora) is injected by the SIMPLE_LAYOUT_START render hook
 * registered in AdminPanelProvider (see resources/views/filament/control/auth-brand.blade.php),
 * mirroring the partner console's PartnerLogin. Unlike the partner login, this
 * page stays OUT of the search index — the console behind it is staff-only.
 */
class ControlLogin extends BaseLogin
{
    public function getTitle(): string | Htmlable
    {
        return 'Haraan Control — Staff Sign in';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Sign in to Control';
    }

    public function getSubHeading(): string | Htmlable | null
    {
        return new HtmlString(
            'Internal console for the Haraan team — content, people, payments &amp; platform.'
        );
    }
}
