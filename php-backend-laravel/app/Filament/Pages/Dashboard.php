<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The /control landing page.
 *
 * Operators who can see the Command Center (super-admin / finance / events)
 * land straight on it — the money · health · radar home — instead of an empty
 * stock dashboard. Limited staff (marketing / ops) who can't access it fall
 * through to the default widget dashboard, so nobody is 403'd at the door.
 *
 * For the privileged path this page is just a redirect, so it hides itself from
 * the sidebar (Command Center already sits at the top); for everyone else it
 * stays the normal "Dashboard" nav item.
 */
class Dashboard extends BaseDashboard
{
    public function mount(): void
    {
        if (CommandCenter::canAccess()) {
            $this->redirect(CommandCenter::getUrl());
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! CommandCenter::canAccess();
    }
}
