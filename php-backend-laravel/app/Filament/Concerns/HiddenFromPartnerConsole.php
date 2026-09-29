<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Facades\Filament;

/**
 * For the "executive hero" strips on shared list pages.
 *
 * They read platform-wide tables (every partner's bookings, blocks, shifts) and
 * fill empty states with illustrative numbers — fine as an operator's mock-up in
 * /control, wrong in front of a partner: they would see other businesses'
 * totals, or figures that never happened at their venue. In the partner console
 * the list page's own table, which IS scoped to the partner, carries the page.
 */
trait HiddenFromPartnerConsole
{
    public static function canView(): bool
    {
        return Filament::getCurrentPanel()?->getId() !== 'partner';
    }
}
