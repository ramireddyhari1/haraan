<?php

namespace App\Filament\Resources\PartnerSubscriptions\Schemas;

use App\Models\PartnerSubscription;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PartnerSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('partner_id')
                    ->label('Partner')
                    ->relationship('partner', 'name', fn ($query) => $query->whereNotNull('partner_type'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Entitlements read the most recent row for a partner, so adding a new '
                        . 'one supersedes the old.'),

                Select::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name')
                    ->preload()
                    ->required(),

                Select::make('status')
                    ->options([
                        PartnerSubscription::STATUS_ACTIVE => 'Active — features unlocked',
                        PartnerSubscription::STATUS_TRIALING => 'Trialing — features unlocked',
                        PartnerSubscription::STATUS_HALTED => 'Halted — payment failed, automations off',
                        PartnerSubscription::STATUS_CANCELLED => 'Cancelled — automations off',
                    ])
                    ->default(PartnerSubscription::STATUS_ACTIVE)
                    ->required()
                    ->helperText('Halted and cancelled drop the partner to the default plan. '
                        . 'Neither ever affects ticket delivery.'),

                DateTimePicker::make('current_period_start')
                    ->label('Period start')
                    ->default(now()),

                DateTimePicker::make('current_period_end')
                    ->label('Period end')
                    ->default(now()->addMonth())
                    ->helperText('Once this passes, the plan stops granting features. Leave empty for '
                        . 'no expiry.'),

                Textarea::make('note')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Why this was assigned — e.g. "launch partner, comped for 3 months".'),
            ]);
    }
}
