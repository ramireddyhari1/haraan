<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\EditsPlatformRules;
use App\Models\Venue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * /control → Platform → Pulse desktop page. The `pulse_web` section of the Platform rules
 * registry: which sections show under the venue grid on haraan.app/gamehub at desktop width,
 * their headings, the banner strip, the app QR link and the questions. Read by
 * App\Support\PulseWebSections the moment it's saved.
 */
class PulseWebPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Pulse desktop page';

    protected static ?string $title = 'Pulse page on desktop';

    protected static ?string $slug = 'pulse-desktop';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.membership-settings';

    private const SECTIONS = ['pulse_web'];

    public static function canAccess(): bool
    {
        return self::canManageSection('pulse_web');
    }

    protected function ruleSections(): array
    {
        return self::SECTIONS;
    }

    public function getSubheading(): ?string
    {
        $venues = Venue::published()->count();

        return "{$venues} published ".($venues === 1 ? 'venue' : 'venues').'. '
            .'Changes show on haraan.app/gamehub as soon as you save.';
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
        $this->saveRules('pulse_web_rules.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Open the page')->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')->url(url('/gamehub'), shouldOpenInNewTab: true),
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }
}
