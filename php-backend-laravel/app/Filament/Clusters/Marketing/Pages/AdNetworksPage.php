<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Marketing\Pages;

use App\Filament\Clusters\Marketing\MarketingCluster;
use App\Filament\Concerns\EditsPlatformRules;
use App\Support\NetworkAds;
use App\Support\PlatformRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * /control → Marketing → Ad networks. The `network_ads` section of the Platform rules registry:
 * Google AdMob in the app's ActionBoard and AdSense on the website's, each spot with its own
 * switch and unit ID. Haraan's own sponsors stay in Marketing → Ads and always win the spot.
 * Served by App\Support\NetworkAds through /api/config and the ActionBoard pages.
 */
class AdNetworksPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static ?string $cluster = MarketingCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Ad networks';

    protected static ?string $title = 'ActionBoard ad networks';

    protected static ?string $slug = 'ad-networks';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.membership-settings';

    private const SECTIONS = ['network_ads'];

    public static function canAccess(): bool
    {
        return self::canManageSection('network_ads');
    }

    protected function ruleSections(): array
    {
        return self::SECTIONS;
    }

    public function getSubheading(): ?string
    {
        if (PlatformRules::bool('ops.network_ads_disabled')) {
            return 'Google ads are STOPPED by the emergency switch in /control → Operations. Nothing below shows until it is turned off.';
        }

        $app = NetworkAds::appConfig(null);
        $live = array_filter([
            $app['feed'] ? 'app match lists' : null,
            $app['match'] ? 'app match screen' : null,
            NetworkAds::webSlot('feed', null) ? 'website match lists' : null,
            NetworkAds::webSlot('match', null) ? 'website match pages' : null,
        ]);

        return ($live === [] ? 'No Google ad spot is live.' : 'Live now: '.implode(', ', $live).'.')
            .' Haraan sponsors in Marketing → Ads (placements “ActionBoard — match lists” and “Match detail”) show first.'
            .' '.url('/ads.txt').' and '.url('/app-ads.txt').' are written from these IDs.';
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
        $this->saveRules('network_ads.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }
}
