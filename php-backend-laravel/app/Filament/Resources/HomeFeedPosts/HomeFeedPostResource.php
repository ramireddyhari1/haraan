<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeFeedPosts;

use App\Filament\Resources\HomeFeedPosts\Pages\CreateHomeFeedPost;
use App\Filament\Resources\HomeFeedPosts\Pages\ListHomeFeedPosts;
use App\Filament\Resources\HomeFeedPosts\Tables\HomeFeedPostsTable;
use App\Models\PlayerPost;
use App\Models\PostComment;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * /control → ActionBoard Home → Posts. Every photo post players have made, with the tools to
 * run the Home feed: pin to the top, feature, hide, remove, restore, restrict the author —
 * and to post as an account (an official Haraan profile, say) straight from here.
 *
 * What the feed then shows is decided by App\Services\HomeFeed, for the app and the website.
 */
class HomeFeedPostResource extends Resource
{
    protected static ?string $model = PlayerPost::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Posts';

    protected static string|\UnitEnum|null $navigationGroup = 'ActionBoard Home';

    protected static ?string $modelLabel = 'post';

    protected static ?string $slug = 'home-feed/posts';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    /** Posts are only ever moderated here, never edited — the words and photos are the player's. */
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'images', 'moderator'])
            ->withCount(['likes', 'comments' => fn (Builder $q) => $q->where('status', PostComment::STATUS_VISIBLE)]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Post')
                ->description('Posted as the account you pick — it appears on that account’s profile and, if the account is public, on the Home feed.')
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->label('Post as')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => User::query()
                            ->where('is_guest', false)
                            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('player_id', 'like', "%{$search}%"))
                            ->limit(25)
                            ->get()
                            ->mapWithKeys(fn (User $u): array => [$u->id => trim($u->name.' · '.($u->username ? '@'.$u->username : ($u->player_id ?: $u->email)))])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->name)
                        ->columnSpanFull(),
                    FileUpload::make('photos')
                        ->label('Photos')
                        ->helperText('Up to 10. The first is the cover. Drag to reorder.')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->maxFiles(10)
                        ->maxSize(8192)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')
                        ->directory('posts')
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('caption')->maxLength(300)->rows(3)->columnSpanFull(),
                    Toggle::make('pin')->label('Pin to the top of the feed'),
                    DateTimePicker::make('pinned_until')->label('Pinned until')->helperText('Empty = until you unpin it.')->seconds(false),
                    Toggle::make('featured')->label('Featured'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return HomeFeedPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeFeedPosts::route('/'),
            'create' => CreateHomeFeedPost::route('/create'),
        ];
    }
}
