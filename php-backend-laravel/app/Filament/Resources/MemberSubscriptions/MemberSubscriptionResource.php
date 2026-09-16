<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberSubscriptions;

use App\Filament\Resources\MemberSubscriptions\Pages\ListMemberSubscriptions;
use App\Filament\Resources\MemberSubscriptions\Pages\ViewMemberSubscription;
use App\Filament\Resources\MemberSubscriptions\RelationManagers\EventsRelationManager;
use App\Filament\Resources\MemberSubscriptions\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\MemberSubscriptions\Tables\MemberSubscriptionsTable;
use App\Models\MemberSubscription;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Every member subscription — paid (Razorpay) and complimentary (admin) — with its lifecycle
 * timeline and payments. Rows are never edited by hand: state comes from Razorpay, and every
 * admin intervention is an explicit, audited action.
 */
class MemberSubscriptionResource extends Resource
{
    protected static ?string $model = MemberSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?string $navigationLabel = 'Member subscriptions';

    protected static ?string $modelLabel = 'member subscription';

    protected static ?int $navigationSort = 21;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('finance') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            MemberSubscription::STATUS_ACTIVE => 'success',
            MemberSubscription::STATUS_PENDING, MemberSubscription::STATUS_AUTHENTICATED => 'warning',
            MemberSubscription::STATUS_HALTED => 'danger',
            MemberSubscription::STATUS_CREATED => 'info',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('user.name')
                        ->label('Member')
                        ->weight('bold')
                        ->helperText(fn (MemberSubscription $r): ?string => $r->user?->email ?: $r->user?->phone),
                    TextEntry::make('plan.name')->label('Plan')->badge()->color('info'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (?string $state): string => self::statusColor($state)),
                    TextEntry::make('provider')
                        ->formatStateUsing(fn (string $state): string => $state === MemberSubscription::PROVIDER_ADMIN ? 'Complimentary (admin)' : 'Razorpay'),
                    TextEntry::make('price_label')
                        ->label('Price')
                        ->state(fn (MemberSubscription $r): string => $r->price?->label() ?? '—'),
                    TextEntry::make('change_type')->label('Started as')->formatStateUsing(fn (string $state): string => ucfirst($state)),
                    TextEntry::make('current_period_start')->label('Period start')->dateTime('d M Y, g:i A')->placeholder('—'),
                    TextEntry::make('current_period_end')->label('Period end')->dateTime('d M Y, g:i A')->placeholder('No end'),
                    TextEntry::make('starts_at')->label('Scheduled start')->dateTime('d M Y, g:i A')->placeholder('—'),
                    IconEntry::make('cancel_at_period_end')->label('Cancels at period end')->boolean(),
                    IconEntry::make('grants_access')
                        ->label('Granting access now')
                        ->state(fn (MemberSubscription $r): bool => $r->grantsAccess())
                        ->boolean(),
                    TextEntry::make('paid_count')->label('Charges'),
                    TextEntry::make('provider_subscription_id')->label('Razorpay subscription')->copyable()->placeholder('—'),
                    TextEntry::make('replaces_subscription_id')->label('Replaces #')->placeholder('—'),
                    TextEntry::make('note')->placeholder('—'),
                    // What the member's advanced insights cover right now, from the same
                    // service the insights endpoints authorise with.
                    TextEntry::make('insight_sports')
                        ->label('Advanced insights')
                        ->state(function (MemberSubscription $r): string {
                            $status = app(\App\Services\Membership\SportInsightsAccess::class)->status($r->user);
                            $unlocked = collect($status['sports'])->where('unlocked', true)->pluck('label')->all();

                            return match ($status['mode']) {
                                'all' => 'Every sport',
                                'none' => 'Not on current plan',
                                default => $unlocked === [] ? 'No sports chosen yet' : implode(', ', $unlocked)
                                    . ($status['over_limit'] ? ' (over limit)' : ''),
                            };
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return MemberSubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [EventsRelationManager::class, PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberSubscriptions::route('/'),
            'view' => ViewMemberSubscription::route('/{record}'),
        ];
    }
}
