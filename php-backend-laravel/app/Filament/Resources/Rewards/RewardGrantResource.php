<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards;

use App\Filament\Resources\Rewards\Pages\ManageRewardGrants;
use App\Models\AdminAction;
use App\Models\RewardGrant;
use App\Models\RewardProgram;
use App\Services\Rewards\RewardLedger;
use App\Support\Rewards\RewardTypes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Rewards → Reward ledger. Every reward given, read-only. The one change allowed is Revoke
 * (with a reason, audited) on a reward not yet claimed — or on credited Bonus XP, which is
 * reversed. Codes are never shown here.
 */
class RewardGrantResource extends Resource
{
    protected static ?string $model = RewardGrant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';

    protected static ?string $navigationLabel = 'Reward ledger';

    protected static ?string $modelLabel = 'reward';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'program']))
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('user.name')->label('Player')->searchable()->description(fn (RewardGrant $r): ?string => $r->user?->player_id),
                TextColumn::make('title')->wrap()->limit(50),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state): string => RewardTypes::RULE_TYPES[$state] ?? ucfirst($state)),
                TextColumn::make('program.name')->label('Program')->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => RewardGrant::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        RewardGrant::AVAILABLE => 'success', RewardGrant::LOCKED => 'warning', RewardGrant::CLAIMED, RewardGrant::REDEEMED => 'info',
                        default => 'gray',
                    })
                    ->description(fn (RewardGrant $r): ?string => $r->status_reason),
                TextColumn::make('match_id')->label('Match')->placeholder('—'),
                TextColumn::make('created_at')->label('Given')->dateTime('d M Y H:i'),
                TextColumn::make('expires_at')->label('Expires')->dateTime('d M Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(RewardGrant::STATUSES),
                SelectFilter::make('type')->options(RewardTypes::RULE_TYPES + [RewardTypes::BADGE => 'Badge', RewardTypes::STREAK => 'Streak']),
                SelectFilter::make('program_id')->label('Program')->options(fn (): array => RewardProgram::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('revoke')->label('Revoke')->icon('heroicon-m-no-symbol')->color('danger')
                    ->visible(fn (RewardGrant $r): bool => $r->isOpen() || ($r->type === RewardTypes::BONUS_XP && $r->status === RewardGrant::CLAIMED))
                    ->schema([Textarea::make('reason')->required()->maxLength(200)->rows(2)])
                    ->requiresConfirmation()
                    ->modalDescription('The player loses this reward. Bonus XP already credited is taken back. Claimed coupons and codes can’t be revoked.')
                    ->action(function (RewardGrant $record, array $data): void {
                        $done = app(RewardLedger::class)->revoke($record, 'admin: '.$data['reason'], (int) auth()->id());
                        if (! $done) {
                            Notification::make()->title('This reward can no longer be revoked')->warning()->send();

                            return;
                        }
                        AdminAction::log('reward_grant.revoked', [
                            'grant_id' => $record->id, 'user_id' => $record->user_id, 'type' => $record->type,
                            'from' => $record->status, 'reason' => $data['reason'],
                        ], $record);
                        Notification::make()->title('Reward revoked')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRewardGrants::route('/')];
    }
}
