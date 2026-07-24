<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerPayoutAccounts;

use App\Filament\Resources\PartnerPayoutAccounts\Pages\ListPartnerPayoutAccounts;
use App\Models\AdminAction;
use App\Models\PartnerPayoutAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Settlement destinations partners have entered, for the finance desk to verify
 * before money is sent. Read-only by design — the partner owns these details
 * (they re-enter them in /partner → Payouts); admin only vouches for them, and
 * any edit by the partner clears the tick again.
 */
class PartnerPayoutAccountResource extends Resource
{
    protected static ?string $model = PartnerPayoutAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'settlement account';

    protected static ?string $navigationLabel = 'Settlement accounts';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('partner.name')
                    ->label('Partner')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => $state === 'upi' ? 'info' : 'gray'),
                TextColumn::make('account_holder')
                    ->label('Destination')
                    ->description(fn (PartnerPayoutAccount $r): string => $r->summaryLine())
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-badge')
                    ->falseIcon('heroicon-m-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('verified_at')
                    ->label('Verification')
                    ->placeholder('All')
                    ->trueLabel('Verified')
                    ->falseLabel('Awaiting verification')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('verified_at'),
                        false: fn ($q) => $q->whereNull('verified_at'),
                        blank: fn ($q) => $q,
                    ),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Verify this destination')
                    ->modalDescription(fn (PartnerPayoutAccount $r): string => 'Confirm ' . $r->summaryLine()
                        . ' belongs to ' . ($r->partner?->name ?: 'this partner') . '.')
                    ->visible(fn (PartnerPayoutAccount $r): bool => ! $r->isVerified())
                    ->action(function (PartnerPayoutAccount $r): void {
                        $r->update(['verified_at' => now()]);
                        AdminAction::log('payout_account.verified', [
                            'account_id' => $r->id,
                            'partner_id' => $r->partner_id,
                        ]);
                        Notification::make()->title('Destination verified')->success()->send();
                    }),

                Action::make('unverify')
                    ->label('Remove verification')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (PartnerPayoutAccount $r): bool => $r->isVerified())
                    ->action(function (PartnerPayoutAccount $r): void {
                        $r->update(['verified_at' => null]);
                        AdminAction::log('payout_account.unverified', [
                            'account_id' => $r->id,
                            'partner_id' => $r->partner_id,
                        ]);
                        Notification::make()->title('Verification removed')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('No settlement accounts yet')
            ->emptyStateDescription('Partners add these themselves in /partner → Payouts.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerPayoutAccounts::route('/'),
        ];
    }
}
