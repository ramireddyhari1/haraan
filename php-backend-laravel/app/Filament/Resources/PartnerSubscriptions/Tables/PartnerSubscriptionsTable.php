<?php

namespace App\Filament\Resources\PartnerSubscriptions\Tables;

use App\Models\PartnerSubscription;
use App\Services\PlanEntitlements;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartnerSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('partner.name')
                    ->label('Partner')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PartnerSubscription::STATUS_ACTIVE, PartnerSubscription::STATUS_TRIALING => 'success',
                        PartnerSubscription::STATUS_HALTED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('current_period_end')
                    ->label('Runs until')
                    ->dateTime('d M Y')
                    ->placeholder('No expiry')
                    ->color(fn ($record): string => $record->current_period_end?->isPast() ? 'danger' : 'gray'),

                // Live from the messaging ledger — the same number the partner sees.
                TextColumn::make('quota')
                    ->label('Conversations left')
                    ->state(function ($record): string {
                        $quota = app(PlanEntitlements::class)->quota($record->partner_id);

                        return $quota['allowance'] === 0
                            ? '—'
                            : number_format($quota['remaining']) . ' of ' . number_format($quota['allowance']);
                    })
                    ->alignRight(),

                TextColumn::make('source')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    PartnerSubscription::STATUS_ACTIVE => 'Active',
                    PartnerSubscription::STATUS_TRIALING => 'Trialing',
                    PartnerSubscription::STATUS_HALTED => 'Halted',
                    PartnerSubscription::STATUS_CANCELLED => 'Cancelled',
                ]),
                SelectFilter::make('plan_id')->relationship('plan', 'name')->label('Plan'),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('No partner is on a paid plan')
            ->emptyStateDescription('Everyone falls back to the default plan, so automations stay off until a plan is assigned here.');
    }
}
