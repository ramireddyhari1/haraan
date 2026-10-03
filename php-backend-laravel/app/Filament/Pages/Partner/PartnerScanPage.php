<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * /partner/scan — ticket check-in, the partner app's Scan tab on a phone.
 *
 * On a phone the page is the camera: public/js/partner/app-shell.js draws the app's
 * scanner over it and checks tickets in through /api/partner/check-in, exactly as the
 * Android app does. It isn't in the desktop menu (desktop keeps its own check-in page);
 * a desktop visitor who lands here is told to open it on a phone.
 */
class PartnerScanPage extends Page
{
    protected string $view = 'filament.pages.partner.scan';

    protected static ?string $slug = 'scan';

    protected static ?string $title = 'Scan a ticket';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return false;
        }

        return auth()->user()?->hasPartnerPermission('checkin') ?? false;
    }
}
