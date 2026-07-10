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
                    ->required()
                    ->label('Area / locality')
                    ->helperText('Short label shown on the venue card (e.g. "Bandra").'),
                TextInput::make('address')
                    ->label('Full address')
                    ->maxLength(255)
                    ->placeholder('123 MG Road, Bandra West, Mumbai, Maharashtra 400050')
                    ->helperText('Street line, colony, city, state, PIN — shown in full under the timing on the venue page.')
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->numeric()
                    ->step('0.0000001')
                    ->minValue(-90)
                    ->maxValue(90)
                    ->placeholder('19.0596')
                    ->helperText('In Google Maps, right-click the exact spot → the first row is "lat, lng". Copy the first number here.'),
                TextInput::make('longitude')
                    ->numeric()
                    ->step('0.0000001')
                    ->minValue(-180)
                    ->maxValue(180)
                    ->placeholder('72.8295')
                    ->helperText('…and the second number here. Coordinates power the live "X km away" distance from each user.'),
                TextInput::make('distance')
                    ->label('Fallback distance')
                    ->helperText('Only used when coordinates are missing. Leave blank once lat/lng are set — the app then computes real distance per user.'),
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
