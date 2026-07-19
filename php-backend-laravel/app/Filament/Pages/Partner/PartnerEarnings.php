<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Filament\Widgets\Partner\PartnerEarningsLedgerWidget;
use App\Filament\Widgets\Partner\PartnerEarningsStatsWidget;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * The partner's money home: collected / settled / pending KPIs plus a full
 * earnings ledger. Lives only in the /partner console (admins have the Finance
 * cluster in /control); everything it shows is scoped to the partner's own
 * events and venues.
 */
class PartnerEarnings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $title = 'Earnings';

    protected static ?string $navigationLabel = 'Earnings';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.partner.earnings';

    public static function canAccess(): bool
    {
        // Partner console only — never surface it in /control.
        return Filament::getCurrentPanel()?->getId() === 'partner';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PartnerEarningsStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PartnerEarningsLedgerWidget::class,
        ];
    }
}
