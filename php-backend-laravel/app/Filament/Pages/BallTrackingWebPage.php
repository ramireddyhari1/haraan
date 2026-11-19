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
 * /control → Platform → Ball tracking page. The `ball_tracking_web` section of the Platform
 * rules registry: everything on haraan.app/ball-tracking — the Google title and description,
 * heading, intro, features, steps, questions and the on/off switch. Read by
 * App\Http\Controllers\Web\BallTrackingController on every request.
 */
class BallTrackingWebPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-viewfinder-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Ball tracking page';

    protected static ?string $title = 'Ball tracking page';

    protected static ?string $slug = 'ball-tracking-page';

    protected static ?int $navigationSort = 41;

    protected string $view = 'filament.pages.membership-settings';

    private const SECTIONS = ['ball_tracking_web'];

    public static function canAccess(): bool
    {
        return self::canManageSection('ball_tracking_web');
    }

    protected function ruleSections(): array
    {
        return self::SECTIONS;
    }

    public function getSubheading(): ?string
    {
        return PlatformRules::bool('ball_tracking_web.enabled')
            ? 'Live at haraan.app/ball-tracking. Changes show on the page as soon as you save.'
            : 'The page is switched off: haraan.app/ball-tracking shows page not found.';
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
        $this->saveRules('ball_tracking_web_rules.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Open the page')->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')->url(url('/ball-tracking'), shouldOpenInNewTab: true),
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }
}
