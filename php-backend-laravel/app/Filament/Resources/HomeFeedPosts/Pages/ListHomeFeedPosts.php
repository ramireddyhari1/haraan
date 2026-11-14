<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeFeedPosts\Pages;

use App\Filament\Pages\HomeFeedRulesPage;
use App\Filament\Resources\HomeFeedPosts\HomeFeedPostResource;
use App\Models\PlayerPost;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListHomeFeedPosts extends ListRecords
{
    protected static string $resource = HomeFeedPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rules')
                ->label('Feed rules')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(HomeFeedRulesPage::getUrl()),
            CreateAction::make()->label('New post'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'feed' => Tab::make('On the feed')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PlayerPost::STATUS_VISIBLE)),
            'pinned' => Tab::make('Pinned')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereNotNull('pinned_at')
                    ->where(fn (Builder $w) => $w->whereNull('pinned_until')->orWhere('pinned_until', '>', now()))),
            'reported' => Tab::make('Reported')
                ->badge(fn (): ?int => ($n = PlayerPost::query()->where('open_reports', '>', 0)->count()) > 0 ? $n : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('open_reports', '>', 0)),
            'hidden' => Tab::make('Hidden')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PlayerPost::STATUS_HIDDEN)),
            'removed' => Tab::make('Removed')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PlayerPost::STATUS_REMOVED)),
        ];
    }
}
