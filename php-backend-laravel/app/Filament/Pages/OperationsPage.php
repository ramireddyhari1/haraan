<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\EditsPlatformRules;
use App\Support\AiGate;
use App\Support\Operations;
use App\Support\PlatformRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Platform → Operations. The emergency switches (maintenance, pause bookings, stop payments,
 * pause match/tournament creation, turn off AI) and the Android version gate. Super admins only.
 *
 * Every switch is enforced on the server (EnforcePlatformOperations, BookingService, the
 * Razorpay gateways, AiGate, the creation endpoints) — the app is told too, via /api/config,
 * but never trusted to comply. Saving always asks for confirmation, and is audit-logged.
 */
class OperationsPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-power';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Operations';

    protected static ?string $title = 'Operations';

    protected static ?string $slug = 'operations';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.operations';

    private const SECTIONS = ['ops', 'app'];

    public static function canAccess(): bool
    {
        return self::canManageSection('ops');
    }

    /** A red count in the sidebar while anything is switched off, so it's never forgotten. */
    public static function getNavigationBadge(): ?string
    {
        $on = count(self::activeSwitches());

        return $on > 0 ? (string) $on : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function ruleSections(): array
    {
        return self::SECTIONS;
    }

    public function mount(): void
    {
        $this->fillRules();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->ruleSectionComponents())->statePath('data');
    }

    public function save(): void
    {
        $this->saveRules('operations.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Apply changes')
                ->icon('heroicon-m-bolt')
                ->requiresConfirmation()
                ->modalHeading('Apply to everyone now?')
                ->modalDescription('These switches take effect immediately for every customer, on the app and the website. The change is recorded in the audit log under your name.')
                ->modalSubmitActionLabel('Apply now')
                ->action('save'),
        ];
    }

    /**
     * What's switched off right now, in words — the status strip at the top of the page.
     *
     * @return list<string>
     */
    public static function activeSwitches(): array
    {
        $on = [];
        foreach (PlatformRules::inSection('ops') as $key => $def) {
            if ($def['type'] === PlatformRules::TYPE_BOOL && PlatformRules::bool($key)) {
                $on[] = $def['label'];
            }
        }
        if (Operations::minimumAppVersion() !== null) {
            $on[] = 'Apps older than '.Operations::minimumAppVersion().' are blocked';
        }

        return $on;
    }

    /** @return array<string, mixed> for the view */
    protected function getViewData(): array
    {
        $budget = PlatformRules::int('ai.daily_call_budget');

        return [
            'active' => self::activeSwitches(),
            'aiUsed' => AiGate::usedToday(),
            'aiBudget' => $budget,
        ];
    }
}
