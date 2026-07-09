<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload()
                    ->placeholder('All events (global coupon)')
                    ->helperText('Leave blank for a code that works on any event; pick an event to scope it.'),
                TextInput::make('code')
                    ->required(),
                TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('max_uses')
                    ->numeric(),
                TextInput::make('uses')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
