<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\Select;
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
                    ->default('Badminton')
                    ->helperText('Primary sport — shown as the card badge and used by the sport filter.'),
                Select::make('sports')
                    ->label('Sports offered')
                    ->multiple()
                    ->options([
                        'Cricket' => 'Cricket',
                        'Football' => 'Football',
                        'Badminton' => 'Badminton',
                        'Basketball' => 'Basketball',
                        'Tennis' => 'Tennis',
                        'Volleyball' => 'Volleyball',
                    ])
                    ->helperText('All games playable here. Shown as icons on the venue card (first two + a count). The primary category is always included.'),
                TextInput::make('location')
                    ->required(),
                TextInput::make('distance'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('map_link')
                    ->label('Google Maps link')
                    ->url()
                    ->maxLength(600)
                    ->placeholder('https://maps.app.goo.gl/…')
                    ->helperText('Open the venue in Google Maps → Share → Copy link, and paste it here. Powers "Show in Map" / "Get directions".')
                    ->columnSpanFull(),
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
