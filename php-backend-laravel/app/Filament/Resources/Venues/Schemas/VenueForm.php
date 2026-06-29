<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                OrganizationSelect::make(),
                TextInput::make('category')
                    ->required()
                    ->default('Badminton'),
                TextInput::make('location')
                    ->required(),
                TextInput::make('distance'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('rating')
                    ->required()
                    ->default('4.5'),
                TextInput::make('ratings_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('reviews_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('tagline'),
                Textarea::make('about')
                    ->columnSpanFull(),
                Textarea::make('images')
                    ->columnSpanFull(),
                Textarea::make('amenities')
                    ->columnSpanFull(),
                Toggle::make('is_bookable')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_featured')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('partner_id')
                    ->numeric(),
            ]);
    }
}
