<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * A partner-app tool that has a home on the phone web app but no desktop console
 * page yet (Standing Slots, Packages, Academy).
 *
 * On a phone, public/js/partner/app-tools.js draws the app's screen over this page and
 * talks to /api/partner/* exactly as the Android app does. It stays out of the desktop
 * menu; a desktop visitor who lands here is told to open it on a phone.
 */
abstract class PartnerAppOnlyPage extends Page
{
    protected string $view = 'filament.pages.partner.app-only';

    /** The partner permission this tool needs, or null for any signed-in partner. */
    protected static ?string $permission = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return false;
        }

        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return static::$permission === null || $user->hasPartnerPermission(static::$permission);
    }
}
