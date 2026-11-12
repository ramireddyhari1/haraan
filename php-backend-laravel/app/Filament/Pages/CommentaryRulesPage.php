<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Filament\Concerns\EditsPlatformRules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * /control → GameHub → Commentary rules. The `commentary` section of the Platform rules
 * registry: the master switch, private matches, time and listener limits, the silence cut-off
 * and how many reports take a commentator off air. Enforced by App\Services\MatchCommentary;
 * the app reads silence and time limits from the go-on-air response, so a save applies to the
 * next broadcast (the switch and reports apply at once).
 */
class CommentaryRulesPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Commentary rules';

    protected static ?string $title = 'Live commentary rules';

    protected static ?string $slug = 'live-commentary-rules';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.membership-settings';

    private const SECTIONS = ['commentary'];

    public static function canAccess(): bool
    {
        return self::canManageSection('commentary');
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
        $this->saveRules('commentary.rules_updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }
}
