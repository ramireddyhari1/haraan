<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
                    ->label('Price per hour')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('₹'),
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
                TextInput::make('tagline')
                    ->placeholder('6 wooden indoor courts')
                    ->helperText('Short one-liner shown under the venue name on the browse card.'),
                TextInput::make('hours')
                    ->label('Operating hours')
                    ->placeholder('6:00 AM – 11:00 PM')
                    ->helperText('Shown with a clock icon under the venue name.'),
                Textarea::make('about')
                    ->columnSpanFull(),
                TagsInput::make('images')
                    ->label('Gallery image URLs')
                    ->placeholder('Paste a URL, press Enter')
                    ->helperText('Full https URLs. The first image is the hero; users swipe through the rest.')
                    ->columnSpanFull(),
                TagsInput::make('amenities')
                    ->placeholder('Floodlights, Parking, Washrooms…')
                    ->helperText('One chip per amenity. Known names (wifi, parking, washroom, shower, cafe, water, floodlights, AC, security, seating, equipment) get their own icon on the venue page.')
                    ->columnSpanFull(),
                TagsInput::make('courts')
                    ->label('Courts / pitches / lanes')
                    ->placeholder('Court 1, Court 2, Lane A…')
                    ->helperText('Bookable units inside the venue. These fill the "Court" dropdown in the app booking form.')
                    ->columnSpanFull(),
                TagsInput::make('rules')
                    ->label('Good to know')
                    ->placeholder('Carry your own racket, Shoes mandatory…')
                    ->helperText('House rules and policies — each chip is a bullet in the "Good to know" list.')
                    ->columnSpanFull(),
                TextInput::make('price_note')
                    ->label('Pricing note')
                    ->placeholder('Pricing is subject to change and is controlled by the venue')
                    ->helperText('Disclaimer shown above the price chart.')
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
