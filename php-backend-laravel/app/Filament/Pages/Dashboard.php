<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\Partner\PartnerKpiHeroWidget;
use App\Filament\Widgets\Partner\PartnerNeedsAttentionWidget;
use App\Filament\Widgets\Partner\PartnerOrganizerScoreWidget;
use App\Filament\Widgets\Partner\PartnerPeakHoursWidget;
use App\Filament\Widgets\Partner\PartnerQuickActionsWidget;
use App\Filament\Widgets\Partner\PartnerRecentBookingsWidget;
use App\Filament\Widgets\Partner\PartnerRevenueTrendWidget;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

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
    // A single "period" control sits at the top of the partner console and drives
    // every money widget at once (à la the pro creator dashboards) — instead of a
    // filter buried inside one chart. Its value flows to widgets via `pageFilters`.
    use HasFiltersForm;

    /** Options for the one global period control. */
    public const PERIODS = [
        '7' => 'Last 7 days',
        '14' => 'Last 14 days',
        '30' => 'Last 30 days',
        '90' => 'Last 90 days',
    ];

    /** Fallback window (days) when no period is chosen yet — matches the default below. */
    public const DEFAULT_PERIOD = 30;

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
     * The one global period control. Rendered only on the partner console (see
     * content()); its selected value reaches every widget as $this->pageFilters.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('range')
                    ->label('Showing')
                    ->options(self::PERIODS)
                    ->default((string) self::DEFAULT_PERIOD)
                    ->selectablePlaceholder(false)
                    ->native(false),
            ]);
    }

    /**
     * Show the period control above the widgets on the partner console only.
     * The /control dashboard (limited staff who don't get the Command Center)
     * keeps its plain widget grid — its widgets don't read pageFilters.
     */
    public function content(Schema $schema): Schema
    {
        $components = [];

        if (self::isPartnerPanel()) {
            $components[] = $this->getFiltersFormContentComponent();
        }

        $components[] = $this->getWidgetsContentComponent();

        return $schema->components($components);
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
            // Its revenue + sparkline follow the global period control.
            PartnerKpiHeroWidget::class,
            // The one dominant money chart — follows the global period control too.
            // (The old "Money by day" bar strip was retired here: three separate
            //  daily-money visuals stacked read busy; PartnerDailyEarningsWidget
            //  stays in the codebase if it's ever wanted back.)
            PartnerRevenueTrendWidget::class,
            // "Needs you" — sellout risk · pending settlement · refund watch.
            PartnerNeedsAttentionWidget::class,
            // Insight analytics — standing trust score and when the audience buys
            // (these are long-horizon signals, so they don't follow the period).
            PartnerOrganizerScoreWidget::class,
            PartnerPeakHoursWidget::class,
            PartnerRecentBookingsWidget::class,
        ];
    }
}
