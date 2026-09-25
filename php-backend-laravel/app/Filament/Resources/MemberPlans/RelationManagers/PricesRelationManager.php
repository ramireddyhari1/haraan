<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberPlans\RelationManagers;

use App\Models\AdminAction;
use App\Models\MemberPlan;
use App\Models\MemberPlanPrice;
use App\Services\Membership\MemberCatalog;
use App\Services\Membership\MembershipException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A plan's prices. A price is created unlinked, then "Create in Razorpay" makes the
 * Razorpay plan and freezes its amount. Only linked prices can be switched on for sale;
 * changing a live price means adding a new one and switching the old one off — existing
 * subscribers keep the price they signed up at.
 */
class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Prices';

    public static function canViewForRecord(mixed $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof MemberPlan && ! $ownerRecord->is_default;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('interval')
                ->options(MemberPlanPrice::intervalOptions())
                ->required()
                ->native(false)
                ->disabled(fn (?MemberPlanPrice $record): bool => filled($record?->razorpay_plan_id)),

            TextInput::make('amount_paise')
                ->label('Amount (₹)')
                ->numeric()
                ->minValue(1)
                ->step('0.01')
                ->required()
                ->prefix('₹')
                ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
                ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * 100))
                ->disabled(fn (?MemberPlanPrice $record): bool => filled($record?->razorpay_plan_id))
                ->helperText('Frozen once the Razorpay plan is created.'),

            Toggle::make('is_active')
                ->label('On sale')
                ->helperText('Only possible once linked to Razorpay.')
                ->disabled(fn (?MemberPlanPrice $record): bool => blank($record?->razorpay_plan_id)),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('interval')
            ->columns([
                TextColumn::make('interval')
                    ->formatStateUsing(fn (string $state): string => MemberPlanPrice::intervalLabel($state))
                    ->badge()
                    ->color('info'),
                TextColumn::make('amount_paise')
                    ->label('Amount')
                    ->formatStateUsing(fn (MemberPlanPrice $record): string => $record->label())
                    ->alignRight(),
                TextColumn::make('razorpay_plan_id')
                    ->label('Razorpay plan')
                    ->placeholder('Not linked')
                    ->copyable(),
                IconColumn::make('is_active')->label('On sale')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add price')
                    ->after(fn (MemberPlanPrice $record) => AdminAction::log('member_price.created', [
                        'price_id' => $record->id, 'amount_paise' => $record->amount_paise, 'interval' => $record->interval,
                    ])),
            ])
            ->recordActions([
                // Unlinked prices are drafts: fix the amount or throw them away. Linked ones are
                // history that subscriptions point at.
                EditAction::make()->visible(fn (MemberPlanPrice $record): bool => blank($record->razorpay_plan_id)),
                DeleteAction::make()->visible(fn (MemberPlanPrice $record): bool => blank($record->razorpay_plan_id)),

                Action::make('linkRazorpay')
                    ->label('Create in Razorpay')
                    ->icon('heroicon-m-link')
                    ->color('info')
                    ->visible(fn (MemberPlanPrice $record): bool => blank($record->razorpay_plan_id))
                    ->requiresConfirmation()
                    ->modalDescription(fn (MemberPlanPrice $record): string => 'Creates a Razorpay plan charging '
                        . $record->label() . '. After this the amount and interval can never change.')
                    ->action(function (MemberPlanPrice $record): void {
                        try {
                            app(MemberCatalog::class)->linkRazorpayPlan($record);
                        } catch (MembershipException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        AdminAction::log('member_price.linked', ['price_id' => $record->id, 'razorpay_plan_id' => $record->razorpay_plan_id]);
                        Notification::make()->title('Linked to Razorpay — switch it on sale when ready')->success()->send();
                    }),

                Action::make('toggleSale')
                    ->label(fn (MemberPlanPrice $record): string => $record->is_active ? 'Take off sale' : 'Put on sale')
                    ->icon(fn (MemberPlanPrice $record): string => $record->is_active ? 'heroicon-m-pause' : 'heroicon-m-play')
                    ->color(fn (MemberPlanPrice $record): string => $record->is_active ? 'gray' : 'success')
                    ->visible(fn (MemberPlanPrice $record): bool => filled($record->razorpay_plan_id))
                    ->requiresConfirmation()
                    ->action(function (MemberPlanPrice $record): void {
                        $record->forceFill(['is_active' => ! $record->is_active])->save();
                        AdminAction::log('member_price.' . ($record->is_active ? 'on_sale' : 'off_sale'), ['price_id' => $record->id]);
                        Notification::make()->title($record->is_active ? 'On sale' : 'Taken off sale')->success()->send();
                    }),
            ]);
    }
}
