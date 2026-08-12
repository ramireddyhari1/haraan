<?php

namespace App\Filament\Resources\CreditPacks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CreditPackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(60)
                    ->placeholder('500 conversations'),

                TextInput::make('code')->required()->maxLength(40)
                    ->unique(ignoreRecord: true)
                    ->helperText('Stable identifier; it ends up in the payment receipt.'),

                TextInput::make('conversations')->numeric()->required()
                    ->helperText('Added to the partner\'s allowance. Credits never expire.'),

                TextInput::make('price_inr')->label('Price (₹)')->numeric()->required()
                    ->helperText('Charged once. Keep it above ₹1 — Razorpay rejects smaller amounts.'),

                Toggle::make('is_active')->label('On sale')->default(false)
                    ->helperText('Off until the price is right — an active pack is immediately buyable '
                        . 'by every partner.'),

                TextInput::make('sort')->numeric()->default(100)->required(),
            ]);
    }
}
