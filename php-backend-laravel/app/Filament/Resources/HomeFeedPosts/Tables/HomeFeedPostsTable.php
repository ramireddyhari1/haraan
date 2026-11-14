<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeFeedPosts\Tables;

use App\Filament\Resources\HomeFeedComments\HomeFeedCommentResource;
use App\Filament\Resources\PostReports\PostReportResource;
use App\Models\AdminAction;
use App\Models\FeedRestriction;
use App\Models\PlayerPost;
use App\Services\HomeFeed;
use App\Support\MediaUrl;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class HomeFeedPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->square()
                    ->size(64)
                    ->getStateUsing(fn (PlayerPost $p): ?string => MediaUrl::resolve($p->image_path))
                    ->extraImgAttributes(['loading' => 'lazy']),
                TextColumn::make('user.name')
                    ->label('Posted by')
                    ->weight('bold')
                    ->description(fn (PlayerPost $p): string => (string) ($p->user?->username ? '@'.$p->user->username : ($p->user?->player_id ?: '—')))
                    ->searchable(['name', 'username', 'player_id']),
                TextColumn::make('caption')
                    ->limit(70)
                    ->wrap()
                    ->placeholder('No caption')
                    ->description(fn (PlayerPost $p): ?string => ($n = count($p->imagePaths())) > 1 ? "{$n} photos" : null)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PlayerPost::STATUSES[$state ?? 'visible'] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        PlayerPost::STATUS_HIDDEN => 'warning',
                        PlayerPost::STATUS_REMOVED => 'danger',
                        default => 'success',
                    })
                    ->description(fn (PlayerPost $p): ?string => $p->isVisible() ? null : ($p->moderation_reason ?: null)),
                TextColumn::make('flags')
                    ->label('On the feed')
                    ->badge()
                    ->getStateUsing(fn (PlayerPost $p): array => array_values(array_filter([
                        $p->isPinned() ? 'Pinned'.($p->pinned_until ? ' till '.$p->pinned_until->format('j M') : '') : null,
                        $p->featured ? 'Featured' : null,
                    ])))
                    ->color(fn (string $state): string => str_starts_with($state, 'Pinned') ? 'info' : 'warning')
                    ->placeholder('—'),
                TextColumn::make('likes_count')->label('Likes')->numeric()->sortable(),
                TextColumn::make('comments_count')
                    ->label('Comments')
                    ->numeric()
                    ->sortable()
                    ->url(fn (PlayerPost $p): string => HomeFeedCommentResource::getUrl('index', ['filters' => ['post_id' => ['value' => $p->id]]])),
                TextColumn::make('open_reports')
                    ->label('Reports')
                    ->badge()
                    ->sortable()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                    ->url(fn (PlayerPost $p): ?string => $p->open_reports > 0
                        ? PostReportResource::getUrl('index', ['filters' => ['post_id' => ['value' => $p->id]]])
                        : null),
                TextColumn::make('created_at')->label('Posted')->since()->sortable(),
                TextColumn::make('moderator.name')
                    ->label('Moderated by')
                    ->placeholder('—')
                    ->description(fn (PlayerPost $p): ?string => $p->moderated_at?->diffForHumans())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('featured'),
                Filter::make('user_id')
                    ->form([\Filament\Forms\Components\TextInput::make('value')->label('Author user ID')->numeric()])
                    ->query(fn (Builder $q, array $data): Builder => filled($data['value'] ?? null) ? $q->where('user_id', $data['value']) : $q),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('')
                    ->tooltip('See the post')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (PlayerPost $p): string => 'Post by '.($p->user?->name ?? 'player'))
                    ->modalContent(fn (PlayerPost $p) => view('filament.home-feed.post-preview', [
                        'post' => $p,
                        'images' => MediaUrl::resolveMany($p->imagePaths()),
                        'comments' => $p->comments()->with('user')->latest('id')->limit(20)->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                ActionGroup::make([
                    Action::make('pin')
                        ->label('Pin to top')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('info')
                        ->visible(fn (PlayerPost $p): bool => $p->status !== PlayerPost::STATUS_REMOVED && ! $p->isPinned())
                        ->schema([
                            DateTimePicker::make('until')->label('Pinned until')->helperText('Empty = until you unpin it.')->seconds(false)->after('now'),
                        ])
                        ->action(function (PlayerPost $p, array $data): void {
                            HomeFeed::pin($p, filled($data['until'] ?? null) ? Carbon::parse($data['until']) : null);
                            Notification::make()->success()->title('Pinned to the top of the Home feed')->send();
                        }),
                    Action::make('unpin')
                        ->label('Unpin')
                        ->icon('heroicon-o-arrow-down-circle')
                        ->visible(fn (PlayerPost $p): bool => $p->isPinned())
                        ->action(function (PlayerPost $p): void {
                            HomeFeed::unpin($p);
                            Notification::make()->success()->title('Unpinned')->send();
                        }),
                    Action::make('feature')
                        ->label(fn (PlayerPost $p): string => $p->featured ? 'Stop featuring' : 'Feature')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->visible(fn (PlayerPost $p): bool => $p->isVisible())
                        ->action(function (PlayerPost $p): void {
                            HomeFeed::setFeatured($p, ! $p->featured);
                            Notification::make()->success()->title($p->featured ? 'Featured' : 'No longer featured')->send();
                        }),
                    Action::make('hide')
                        ->label('Hide from feed')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->visible(fn (PlayerPost $p): bool => $p->isVisible())
                        ->modalDescription('The post leaves the Home feed and the author’s public profile. The author still sees it on their own profile, marked hidden. Open reports on it are closed as actioned.')
                        ->schema([Textarea::make('reason')->label('Reason (kept in the log)')->maxLength(300)->rows(2)])
                        ->action(function (PlayerPost $p, array $data): void {
                            HomeFeed::takeDown($p, PlayerPost::STATUS_HIDDEN, $data['reason'] ?? null);
                            Notification::make()->success()->title('Hidden')->send();
                        }),
                    Action::make('remove')
                        ->label('Remove')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (PlayerPost $p): bool => $p->status !== PlayerPost::STATUS_REMOVED)
                        ->modalDescription('For posts that break the rules. Nobody sees it any more, the author included, and the author can’t delete it. The photos and the post are kept for the record and can be restored.')
                        ->schema([Textarea::make('reason')->label('Reason (kept in the log)')->required()->maxLength(300)->rows(2)])
                        ->action(function (PlayerPost $p, array $data): void {
                            HomeFeed::takeDown($p, PlayerPost::STATUS_REMOVED, $data['reason']);
                            Notification::make()->success()->title('Removed')->send();
                        }),
                    Action::make('restore')
                        ->label('Restore')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->visible(fn (PlayerPost $p): bool => ! $p->isVisible())
                        ->requiresConfirmation()
                        ->modalDescription('The post goes back on the feed and the author’s profile. Any open reports on it are dismissed.')
                        ->action(function (PlayerPost $p): void {
                            HomeFeed::restore($p);
                            Notification::make()->success()->title('Restored')->send();
                        }),
                    Action::make('restrict')
                        ->label('Restrict the author')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(fn (PlayerPost $p): bool => $p->user !== null)
                        ->schema([
                            Select::make('type')->label('Restriction')->options(FeedRestriction::TYPES)->required()->native(false),
                            DateTimePicker::make('until')->label('Until')->helperText('Empty = until someone lifts it.')->seconds(false)->after('now'),
                            Textarea::make('reason')->maxLength(300)->rows(2),
                        ])
                        ->action(function (PlayerPost $p, array $data): void {
                            $r = FeedRestriction::create([
                                'user_id' => $p->user_id,
                                'type' => $data['type'],
                                'until' => $data['until'] ?? null,
                                'reason' => $data['reason'] ?? null,
                                'created_by' => auth()->id(),
                            ]);
                            AdminAction::log('home_feed.restriction_added', ['user_id' => $p->user_id, 'type' => $r->type, 'until' => $r->until?->toIso8601String(), 'from_post_id' => $p->id], $r);
                            Notification::make()->success()->title('Restriction added')->send();
                        }),
                ])->tooltip('Manage'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hide_selected')
                        ->label('Hide from feed')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->schema([Textarea::make('reason')->label('Reason (kept in the log)')->maxLength(300)->rows(2)])
                        ->action(function (Collection $records, array $data): void {
                            $records->filter(fn (PlayerPost $p) => $p->isVisible())
                                ->each(fn (PlayerPost $p) => HomeFeed::takeDown($p, PlayerPost::STATUS_HIDDEN, $data['reason'] ?? null));
                            Notification::make()->success()->title('Hidden')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('restore_selected')
                        ->label('Restore')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->reject(fn (PlayerPost $p) => $p->isVisible())->each(fn (PlayerPost $p) => HomeFeed::restore($p));
                            Notification::make()->success()->title('Restored')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No posts yet')
            ->emptyStateDescription('Photos players post from their profile land here. Use “New post” to post as an account yourself.');
    }
}
