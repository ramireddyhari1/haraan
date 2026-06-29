<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('CONFIRMED'),
                Textarea::make('seat_numbers')
                    ->columnSpanFull(),
                TextInput::make('coupon_code'),
                TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('event_id')
                    ->relationship('event', 'title')
                    ->required(),
            ]);
    }
}
