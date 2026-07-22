<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\Partner\PartnerKpiHeroWidget;
use App\Filament\Widgets\Partner\PartnerQuickActionsWidget;
use App\Filament\Widgets\Partner\PartnerRecentBookingsWidget;
use App\Filament\Widgets\Partner\PartnerRevenueTrendWidget;
use Filament\Facades\Filament;
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
        // Command Center only exists in /control. Never redirect there from the
        // partner console — an event partner passes canAccess() (canManage events)
        // but the route doesn't exist in /partner, which would 500.
        if (! self::isPartnerPanel() && CommandCenter::canAccess()) {
            $this->redirect(CommandCenter::getUrl());
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Partners always keep the Dashboard nav; in /control it hides for Command
        // Center users (who land there instead).
        return self::isPartnerPanel() || ! CommandCenter::canAccess();
    }

    private static function isPartnerPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'partner';
    }

    /**
     * The /partner console lands here (partners never have Command Center access),
     * so give them a real home instead of the stock empty widget grid: a lane-aware
     * launchpad + KPIs + revenue trend + recent bookings, all partner-scoped. Other
     * panels keep their default widget set.
     */
    public function getWidgets(): array
    {
        if (Filament::getCurrentPanel()?->getId() !== 'partner') {
            return parent::getWidgets();
        }

        return [
            PartnerQuickActionsWidget::class,
            // Premium "money hero" (dominant revenue + supporting KPIs), lane-aware
            // internally — supersedes the generic Events/GameHub stats strip here.
            PartnerKpiHeroWidget::class,
            PartnerRevenueTrendWidget::class,
            PartnerRecentBookingsWidget::class,
        ];
    }
}
