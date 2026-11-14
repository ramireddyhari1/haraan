<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostReports;

use App\Filament\Resources\PostReports\Pages\ListPostReports;
use App\Models\PlayerPost;
use App\Models\PostComment;
use App\Models\PostReport;
use App\Services\HomeFeed;
use App\Support\MediaUrl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * /control → ActionBoard Home → Reported posts. The queue behind the app's and website's
 * Report action on a post or a comment.
 *
 * Like Player reports, a WORKLIST: nothing is created or edited here. A moderator takes the
 * post (or comment) down, which closes every open report on it as actioned, or dismisses the
 * report. Both record who decided and when.
 */
class PostReportResource extends Resource
{
    protected static ?string $model = PostReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Reported posts';

    protected static string|\UnitEnum|null $navigationGroup = 'ActionBoard Home';

    protected static ?string $modelLabel = 'post report';

    protected static ?string $slug = 'home-feed/reports';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PostReport::query()->where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['post.user', 'comment.user', 'reporter', 'reviewer']);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Oldest open first — a queue sorted newest-first buries what has waited longest.
            ->defaultSort('created_at', 'asc')
            ->columns([
                ImageColumn::make('post_cover')
                    ->label('')
                    ->square()
                    ->size(56)
                    ->getStateUsing(fn (PostReport $r): ?string => MediaUrl::resolve($r->post?->image_path)),
                TextColumn::make('what')
                    ->label('Reported')
                    ->weight('bold')
                    ->getStateUsing(fn (PostReport $r): string => $r->comment_id
                        ? 'Comment: “'.\Illuminate\Support\Str::limit((string) $r->comment?->body, 80).'”'
                        : ($r->post?->caption ? \Illuminate\Support\Str::limit($r->post->caption, 80) : 'Post (no caption)'))
                    ->description(fn (PostReport $r): string => 'by '.($r->comment_id ? ($r->comment?->user?->name ?? 'player') : ($r->post?->user?->name ?? 'player'))
                        .' · post is '.strtolower(PlayerPost::STATUSES[$r->post?->status ?? 'visible'] ?? 'visible'))
                    ->wrap(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PostReport::REASONS[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => in_array($state, ['nudity', 'violence', 'harassment', 'hate'], true) ? 'danger' : 'warning'),
                TextColumn::make('details')->label('What they said')->limit(60)->wrap()->placeholder('No detail given'),
                TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->description(fn (PostReport $r): string => (string) ($r->reporter?->player_id ?: '—'))
                    ->searchable(),
                TextColumn::make('post.open_reports')
                    ->label('Open on post')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 1 ? 'danger' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'actioned' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Reported')->since()->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('Decided by')
                    ->placeholder('—')
                    ->description(fn (PostReport $r): ?string => $r->reviewed_at?->diffForHumans())
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['open' => 'Open', 'actioned' => 'Actioned', 'dismissed' => 'Dismissed'])
                    ->default('open'),
                SelectFilter::make('reason')->options(PostReport::REASONS),
                Filter::make('post_id')
                    ->form([\Filament\Forms\Components\TextInput::make('value')->label('Post ID')->numeric()])
                    ->query(fn (Builder $q, array $data): Builder => filled($data['value'] ?? null) ? $q->where('post_id', $data['value']) : $q),
            ])
            ->recordActions([
                Action::make('take_down')
                    ->label(fn (PostReport $r): string => $r->comment_id ? 'Hide comment' : 'Hide post')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (PostReport $r): bool => $r->status === 'open')
                    ->schema(fn (PostReport $r): array => $r->comment_id ? [] : [
                        Textarea::make('reason')->label('Reason (kept in the log)')->maxLength(300)->rows(2)
                            ->default(fn () => 'Reported: '.(PostReport::REASONS[$r->reason] ?? $r->reason)),
                    ])
                    ->requiresConfirmation(fn (PostReport $r): bool => (bool) $r->comment_id)
                    ->modalDescription(fn (PostReport $r): string => $r->comment_id
                        ? 'The comment leaves the thread. Every open report on it is closed as actioned.'
                        : 'The post leaves the Home feed and the author’s public profile. Every open report on it is closed as actioned.')
                    ->action(function (PostReport $r, array $data): void {
                        if ($r->comment_id && $r->comment) {
                            HomeFeed::setCommentStatus($r->comment, PostComment::STATUS_HIDDEN);
                        } elseif ($r->post) {
                            if ($r->post->isVisible()) {
                                HomeFeed::takeDown($r->post, PlayerPost::STATUS_HIDDEN, $data['reason'] ?? null);
                            } else {
                                // Already down (auto-hidden by reports): confirm the decision.
                                HomeFeed::closeReports($r->post, 'actioned');
                            }
                        }
                        Notification::make()->success()->title('Done — reports closed')->send();
                    }),
                Action::make('dismiss')
                    ->label(fn (PostReport $r): string => (! $r->comment_id && $r->post && ! $r->post->isVisible()) ? 'Dismiss & restore post' : 'Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (PostReport $r): bool => $r->status === 'open')
                    ->requiresConfirmation()
                    ->modalDescription(fn (PostReport $r): string => (! $r->comment_id && $r->post && ! $r->post->isVisible())
                        ? 'The post was taken down by reports. Dismissing puts it back on the feed and dismisses every open report on it.'
                        : 'Only this report is closed. The post stays as it is.')
                    ->action(function (PostReport $r): void {
                        if (! $r->comment_id && $r->post && $r->post->status === PlayerPost::STATUS_HIDDEN) {
                            HomeFeed::restore($r->post);
                        } else {
                            $r->update(['status' => 'dismissed', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                            if ($r->post) {
                                HomeFeed::refreshOpenReports($r->post);
                            }
                        }
                        Notification::make()->success()->title('Dismissed')->send();
                    }),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (PostReport $r): bool => $r->status !== 'open')
                    ->action(function (PostReport $r): void {
                        $r->update(['status' => 'open', 'reviewed_by' => null, 'reviewed_at' => null]);
                        if ($r->post) {
                            HomeFeed::refreshOpenReports($r->post);
                        }
                        Notification::make()->success()->title('Reopened')->send();
                    }),
            ])
            ->emptyStateHeading('Nothing reported')
            ->emptyStateDescription('Reports players file on posts and comments land here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostReports::route('/'),
        ];
    }
}
