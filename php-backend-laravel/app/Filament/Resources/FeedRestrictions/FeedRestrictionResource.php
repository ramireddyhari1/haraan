<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedRestrictions;

use App\Filament\Resources\FeedRestrictions\Pages\ManageFeedRestrictions;
use App\Models\AdminAction;
use App\Models\FeedRestriction;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * /control → ActionBoard Home → Restrictions. Narrower than suspending an account: stop one
 * player posting, commenting, or appearing on the Home feed — for a while or until lifted.
 * Enforced by App\Services\HomeFeed on the app and the website.
 */
class FeedRestrictionResource extends Resource
{
    protected static ?string $model = FeedRestriction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'Restrictions';

    protected static string|\UnitEnum|null $navigationGroup = 'ActionBoard Home';

    protected static ?string $modelLabel = 'restriction';

    protected static ?string $slug = 'home-feed/restrictions';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    /** History is the point — a restriction is lifted, never deleted. */
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'creator']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Player')
                ->required()
                ->searchable()
                ->disabledOn('edit')
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('player_id', 'like', "%{$search}%"))
                    ->limit(25)
                    ->get()
                    ->mapWithKeys(fn (User $u): array => [$u->id => trim($u->name.' · '.($u->username ? '@'.$u->username : ($u->player_id ?: $u->email)))])
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->name),
            Select::make('type')->label('Restriction')->options(FeedRestriction::TYPES)->required()->native(false)->disabledOn('edit'),
            DateTimePicker::make('until')->label('Until')->helperText('Empty = until someone lifts it.')->seconds(false),
            Textarea::make('reason')->maxLength(300)->rows(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Player')
                    ->weight('bold')
                    ->description(fn (FeedRestriction $r): string => (string) ($r->user?->username ? '@'.$r->user->username : ($r->user?->player_id ?: '—')))
                    ->searchable(['name', 'username', 'player_id']),
                TextColumn::make('type')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (string $state): string => FeedRestriction::TYPES[$state] ?? $state),
                TextColumn::make('state')
                    ->badge()
                    ->getStateUsing(fn (FeedRestriction $r): string => $r->lifted_at ? 'Lifted' : ($r->isActive() ? 'Active' : 'Expired'))
                    ->color(fn (string $state): string => $state === 'Active' ? 'danger' : 'gray'),
                TextColumn::make('until')->dateTime('j M Y, g:i a')->placeholder('Until lifted'),
                TextColumn::make('reason')->limit(60)->wrap()->placeholder('—'),
                TextColumn::make('creator.name')->label('Added by')->placeholder('—')
                    ->description(fn (FeedRestriction $r): ?string => $r->created_at?->diffForHumans()),
            ])
            ->filters([
                SelectFilter::make('type')->options(FeedRestriction::TYPES),
                TernaryFilter::make('active')
                    ->label('Active only')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->active(),
                        false: fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNotNull('lifted_at')->orWhere('until', '<=', now())),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (FeedRestriction $r): bool => $r->isActive())
                    ->after(fn (FeedRestriction $r) => AdminAction::log('home_feed.restriction_changed', [
                        'user_id' => $r->user_id, 'type' => $r->type, 'until' => $r->until?->toIso8601String(),
                    ], $r)),
                Action::make('lift')
                    ->label('Lift')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (FeedRestriction $r): bool => $r->isActive())
                    ->requiresConfirmation()
                    ->action(function (FeedRestriction $r): void {
                        $r->update(['lifted_at' => now(), 'lifted_by' => auth()->id()]);
                        AdminAction::log('home_feed.restriction_lifted', ['user_id' => $r->user_id, 'type' => $r->type], $r);
                        Notification::make()->success()->title('Restriction lifted')->send();
                    }),
            ])
            ->emptyStateHeading('No restrictions')
            ->emptyStateDescription('Stop a player posting, commenting or appearing on the Home feed without suspending their account.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFeedRestrictions::route('/'),
        ];
    }
}
