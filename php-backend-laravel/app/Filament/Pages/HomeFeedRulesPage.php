<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\EditsPlatformRules;
use App\Models\FeedRestriction;
use App\Models\PlayerPost;
use App\Models\PostReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * /control → ActionBoard Home → Feed rules. The `home_feed` section of the Platform rules
 * registry — on/off, who may post and comment, ordering, caps, auto-hide on reports, blocked
 * words — on its own page next to the posts it governs. Enforced by App\Services\HomeFeed for
 * the app and the website the moment it's saved.
 */
class HomeFeedRulesPage extends Page implements HasForms
{
    use EditsPlatformRules;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'ActionBoard Home';

    protected static ?string $navigationLabel = 'Feed rules';

    protected static ?string $title = 'Home feed rules';

    protected static ?string $slug = 'home-feed/rules';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.membership-settings';

    private const SECTIONS = ['home_feed'];

    public static function canAccess(): bool
    {
        return self::canManageSection('home_feed');
    }

    protected function ruleSections(): array
    {
        return self::SECTIONS;
    }

    public function getSubheading(): ?string
    {
        $live = PlayerPost::query()->where('status', PlayerPost::STATUS_VISIBLE)->count();
        $week = PlayerPost::query()->where('created_at', '>=', now()->subDays(7))->count();
        $open = PostReport::query()->where('status', 'open')->count();
        $restricted = FeedRestriction::query()->active()->distinct()->count('user_id');

        return "{$live} posts live · {$week} posted this week · {$open} open reports · {$restricted} players restricted. "
            .'Changes apply to the app and the website as soon as you save.';
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
        $this->saveRules('home_feed_rules.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->icon('heroicon-m-check')->action('save'),
        ];
    }
}
