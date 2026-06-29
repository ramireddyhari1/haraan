<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                OrganizationSelect::make(),
                Textarea::make('description')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('category')
                    ->required()
                    ->default('GENERAL'),
                TextInput::make('booking_format')
                    ->required()
                    ->default('HYBRID'),
                TextInput::make('visibility')
                    ->required()
                    ->default('PUBLIC'),
                TextInput::make('access_code'),
                TextInput::make('location')
                    ->required()
                    ->default(''),
                TextInput::make('venue')
                    ->required()
                    ->default(''),
                DateTimePicker::make('date')
                    ->required(),
                TextInput::make('time')
                    ->required()
                    ->default(''),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('total_slots')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('available_slots')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('images')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('DRAFT'),
                Select::make('partner_id')
                    ->relationship('partner', 'name')
                    ->required(),
                TextInput::make('seat_rows')
                    ->numeric(),
                TextInput::make('seats_per_row')
                    ->numeric(),
                Toggle::make('seat_selection')
                    ->required(),
            ]);
    }
}
