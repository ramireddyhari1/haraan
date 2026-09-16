<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberSubscriptions\Tables;

use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionActions;
use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource;
use App\Models\MemberSubscription;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        $statuses = array_merge(MemberSubscription::OPEN_STATUSES, MemberSubscription::TERMINAL_STATUSES);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'plan', 'price']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Member')
                    ->weight('bold')
                    ->description(fn (MemberSubscription $r): ?string => $r->user?->email ?: $r->user?->phone)
                    ->searchable(['name', 'email', 'phone']),
                TextColumn::make('plan.name')->label('Plan')->badge()->color('info'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => MemberSubscriptionResource::statusColor($state))
                    ->description(fn (MemberSubscription $r): ?string => $r->cancel_at_period_end ? 'Ends at period end' : null),
                TextColumn::make('provider')
                    ->formatStateUsing(fn (string $state): string => $state === MemberSubscription::PROVIDER_ADMIN ? 'Comp' : 'Razorpay')
                    ->color('gray'),
                TextColumn::make('price_label')
                    ->label('Price')
                    ->state(fn (MemberSubscription $r): string => $r->price?->label() ?? '—')
                    ->alignRight(),
                TextColumn::make('current_period_end')
                    ->label('Period end')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('granting')
                    ->label('Access')
                    ->state(fn (MemberSubscription $r): bool => $r->grantsAccess())
                    ->boolean(),
                TextColumn::make('created_at')->label('Created')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine($statuses, array_map('ucfirst', $statuses))),
                SelectFilter::make('plan_id')->label('Plan')->relationship('plan', 'name')->preload(),
                SelectFilter::make('provider')->options([
                    MemberSubscription::PROVIDER_RAZORPAY => 'Razorpay',
                    MemberSubscription::PROVIDER_ADMIN => 'Complimentary',
                ]),
                TernaryFilter::make('cancel_at_period_end')->label('Cancelling at period end'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(MemberSubscriptionActions::forRecord()),
            ])
            ->emptyStateHeading('No member subscriptions yet');
    }
}
