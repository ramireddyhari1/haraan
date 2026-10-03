<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\EditsPlatformRules;
use App\Support\PlatformRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Platform → Platform rules. Every business rule that isn't an emergency switch: fees,
 * commission and tax defaults; booking holds and limits; waitlist; creation limits and match
 * proximity; the XP economy; sign-in code policy; automated messaging; AI features.
 *
 * Values are enforced on the server the moment they're saved (the settings snapshot is cache-
 * busted on write) and broadcast to clients as a `config` change. Secrets are not here and
 * cannot be added here — see PlatformRules::assertNotSecret().
 */
class PlatformRulesPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Platform rules';

    protected static ?string $title = 'Platform rules';

    protected static ?string $slug = 'platform-rules';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.membership-settings';

    private const SECTIONS = ['fees', 'bookings', 'waitlist', 'creation', 'xp', 'rewards', 'otp', 'whatsapp_bot', 'partner_insights', 'partner_web_app', 'messaging', 'ai'];

    public static function canAccess(): bool
    {
        foreach (self::SECTIONS as $section) {
            if (self::canManageSection($section)) {
                return true;
            }
        }

        return false;
    }

    protected function ruleSections(): array
    {
        return self::SECTIONS;
    }

    public function getSubheading(): ?string
    {
        return 'Changes are live on the server as soon as you save, for the app and the website. '
            .'Payment keys and other secrets are never set here — they stay in the server’s environment.';
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
        $this->saveRules('platform_rules.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }

    /** For tests and the Operations page: the sections this page owns. */
    public static function sections(): array
    {
        return array_values(array_intersect(self::SECTIONS, array_keys(PlatformRules::SECTIONS)));
    }
}
