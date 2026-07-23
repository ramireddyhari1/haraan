<?php

namespace App\Filament\Resources\Ads\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sponsor'),
                TextInput::make('title')
                    ->required(),
                TextInput::make('subtitle'),
                FileUpload::make('image')
                    ->image(),
                TextInput::make('logo'),
                TextInput::make('cta_text')
                    ->required()
                    ->default('Try Now'),
                TextInput::make('cta_url')
                    ->url(),
                TextInput::make('placement')
                    ->required()
                    ->default('events'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
            ]);
    }
}
