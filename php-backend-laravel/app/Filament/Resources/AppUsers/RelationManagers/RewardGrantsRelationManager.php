<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\RewardGrant;
use App\Models\User;
use App\Support\Rewards\RewardTypes;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RewardGrantsRelationManager extends RelationManager
{
    protected static string $relationship = 'rewardGrants';

    protected static ?string $title = 'Rewards';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-gift';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        /** @var \App\Models\User $ownerRecord */
        $count = $ownerRecord->rewardGrants()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('title')
                    ->label('Reward title')
                    ->weight('bold')
                    ->description(fn (RewardGrant $r): ?string => $r->description)
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RewardTypes::RULE_TYPES[$state] ?? ucfirst($state)),

                TextColumn::make('bonus_xp')
                    ->label('Bonus XP')
                    ->numeric()
                    ->formatStateUsing(fn (?int $state): string => $state ? "+{$state} XP" : '—')
                    ->color('primary')
                    ->alignEnd(),

                TextColumn::make('value')
                    ->label('Benefit / Value')
                    ->formatStateUsing(fn ($state): string => filled($state) ? (is_numeric($state) ? '₹' . number_format((float) $state, 2) : (string) $state) : '—')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RewardGrant::STATUSES[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        RewardGrant::AVAILABLE => 'success',
                        RewardGrant::CLAIMED, RewardGrant::REDEEMED => 'info',
                        RewardGrant::LOCKED => 'warning',
                        RewardGrant::REVOKED, RewardGrant::EXPIRED => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Granted')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M Y')
                    ->placeholder('No expiry'),
            ])
            ->headerActions([
                Action::make('openInRewards')
                    ->label('View in Rewards ledger')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => route('filament.control.resources.rewards.reward-grants.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]))
                    ->openUrlInNewTab(),
            ]);
    }
}
