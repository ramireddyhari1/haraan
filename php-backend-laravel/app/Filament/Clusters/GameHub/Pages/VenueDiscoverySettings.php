<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Concerns\EditsPlatformRules;
use App\Filament\Resources\VenueCandidates\VenueCandidateResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * /control → GameHub → Discovery → Settings. The `venue_discovery` section of the Platform
 * rules: which cities and phrases are searched, the Playo check, and what a listed venue
 * shows on the website. Reached from the Discovery list, not the sidebar.
 */
class VenueDiscoverySettings extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $cluster = \App\Filament\Clusters\GameHub\GameHubCluster::class;

    protected static ?string $navigationLabel = 'Discovery settings';

    protected static ?string $title = 'Discovery settings';

    protected static ?string $slug = 'venue-discovery-settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.membership-settings';

    public static function canAccess(): bool
    {
        // The partner panel shares this cluster — settings are Haraan's, never a venue owner's.
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'control'
            && self::canManageSection('venue_discovery');
    }

    protected function ruleSections(): array
    {
        return ['venue_discovery'];
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
        $this->saveRules('venue_discovery_rules.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to Discovery')->icon('heroicon-m-arrow-left')
                ->color('gray')->url(VenueCandidateResource::getUrl()),
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }
}
