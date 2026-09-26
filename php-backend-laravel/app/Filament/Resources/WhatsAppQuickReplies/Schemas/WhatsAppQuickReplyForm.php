<?php

namespace App\Filament\Resources\WhatsAppQuickReplies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WhatsAppQuickReplyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('venue_id')
                    ->label('Venue')
                    ->relationship('venue', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Every venue (platform default)')
                    ->helperText('Leave empty for a default every venue gets. A venue’s own reply with the same shortcut replaces the default for that venue.'),

                TextInput::make('shortcut')
                    ->required()
                    ->maxLength(48)
                    ->placeholder('/rates')
                    ->regex('/^\/[a-z0-9_-]+$/')
                    ->helperText('Starts with “/”, lowercase, no spaces.'),

                TextInput::make('title')
                    ->required()
                    ->maxLength(120)
                    ->placeholder('Rate card'),

                Select::make('category')
                    ->options([
                        'General' => 'General',
                        'Pricing' => 'Pricing',
                        'Booking' => 'Booking',
                        'Directions' => 'Directions',
                        'Rules' => 'Rules',
                    ])
                    ->default('General')
                    ->required(),

                Textarea::make('body')
                    ->required()
                    ->rows(6)
                    ->maxLength(1500)
                    ->columnSpanFull()
                    ->helperText('Filled in per venue: {{venue_name}} {{venue_address}} {{maps_url}} {{rate_card}} {{venue_hours}} {{venue_rules}} {{hold_minutes}} · per chat: {{customer_name}}. A reply is hidden for a venue that hasn’t set one of its details.'),
            ]);
    }
}
