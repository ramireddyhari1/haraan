<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeFeedComments;

use App\Filament\Resources\HomeFeedComments\Pages\ListHomeFeedComments;
use App\Models\PostComment;
use App\Services\HomeFeed;
use App\Support\MediaUrl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * /control → ActionBoard Home → Comments. Every comment on every post, newest first, with
 * hide / restore. A hidden comment leaves the thread and the post's comment count everywhere.
 */
class HomeFeedCommentResource extends Resource
{
    protected static ?string $model = PostComment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-oval-left';

    protected static ?string $navigationLabel = 'Comments';

    protected static string|\UnitEnum|null $navigationGroup = 'ActionBoard Home';

    protected static ?string $modelLabel = 'comment';

    protected static ?string $slug = 'home-feed/comments';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'post', 'moderator']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('post_cover')
                    ->label('')
                    ->square()
                    ->size(44)
                    ->getStateUsing(fn (PostComment $c): ?string => MediaUrl::resolve($c->post?->image_path)),
                TextColumn::make('body')->label('Comment')->wrap()->limit(160)->searchable(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->description(fn (PostComment $c): string => (string) ($c->user?->username ? '@'.$c->user->username : ($c->user?->player_id ?: '—')))
                    ->searchable(['name', 'username', 'player_id']),
                TextColumn::make('post.caption')->label('On post')->limit(40)->placeholder('No caption')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => $state === PostComment::STATUS_HIDDEN ? 'warning' : 'success')
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'visible')),
                TextColumn::make('created_at')->label('Posted')->since()->sortable(),
                TextColumn::make('moderator.name')->label('Moderated by')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    PostComment::STATUS_VISIBLE => 'Visible',
                    PostComment::STATUS_HIDDEN => 'Hidden',
                ]),
                Filter::make('post_id')
                    ->form([\Filament\Forms\Components\TextInput::make('value')->label('Post ID')->numeric()])
                    ->query(fn (Builder $q, array $data): Builder => filled($data['value'] ?? null) ? $q->where('post_id', $data['value']) : $q),
            ])
            ->recordActions([
                Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (PostComment $c): bool => $c->status !== PostComment::STATUS_HIDDEN)
                    ->requiresConfirmation()
                    ->action(function (PostComment $c): void {
                        HomeFeed::setCommentStatus($c, PostComment::STATUS_HIDDEN);
                        Notification::make()->success()->title('Comment hidden')->send();
                    }),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (PostComment $c): bool => $c->status === PostComment::STATUS_HIDDEN)
                    ->action(function (PostComment $c): void {
                        HomeFeed::setCommentStatus($c, PostComment::STATUS_VISIBLE);
                        Notification::make()->success()->title('Comment restored')->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('hide_selected')
                    ->label('Hide selected')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->each(fn (PostComment $c) => HomeFeed::setCommentStatus($c, PostComment::STATUS_HIDDEN));
                        Notification::make()->success()->title('Comments hidden')->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('No comments');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeFeedComments::route('/'),
        ];
    }
}
