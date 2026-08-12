<?php

namespace App\Filament\Resources\PartnerPlans\Schemas;

use App\Models\PartnerPlan;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PartnerPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(60),

                TextInput::make('code')
                    ->required()
                    ->maxLength(40)
                    ->helperText('Stable identifier used in code — do not rename a live plan.')
                    ->unique(ignoreRecord: true),

                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(255)
                    ->helperText('Shown to partners on their plan page.'),

                TextInput::make('price_inr')
                    ->label('Price (₹ per month)')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Nothing charges yet — billing lands with Razorpay subscriptions.'),

                TextInput::make('included_conversations')
                    ->label('Included conversations / month')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('WhatsApp bills per 24h conversation, not per message. '
                        . 'Size this from real volume on the Messaging page.'),

                CheckboxList::make('features')
                    ->label('Unlocks')
                    ->options([
                        PartnerPlan::FEATURE_INBOUND => 'Inbound auto-replies (keyword + away message)',
                        PartnerPlan::FEATURE_JOURNEYS => 'Outbound journeys (reminders, review requests)',
                        PartnerPlan::FEATURE_INSTAGRAM => 'Instagram DMs (not built yet — phase 3)',
                    ])
                    ->helperText('Ticket delivery and OTPs are deliberately absent: they are never '
                        . 'gated by a plan, on any tier.'),

                TextInput::make('razorpay_plan_id')
                    ->label('Razorpay plan id')
                    ->placeholder('plan_XXXXXXXXXXXX')
                    ->helperText('Create the matching recurring plan in the Razorpay dashboard and paste '
                        . 'its id here. Without it this tier can only be assigned by an admin, not bought.'),

                Toggle::make('is_default')
                    ->label('Default plan')
                    ->helperText('What a partner falls back to with no subscription, or when payment fails. '
                        . 'Exactly one plan should have this.'),

                Toggle::make('is_active')
                    ->label('Selectable')
                    ->default(true)
                    ->helperText('Leave off until the price and quota are real — an active ₹0 tier '
                        . 'is how someone gets paid features for nothing.'),

                TextInput::make('sort')
                    ->numeric()
                    ->default(100)
                    ->required(),
            ]);
    }
}
