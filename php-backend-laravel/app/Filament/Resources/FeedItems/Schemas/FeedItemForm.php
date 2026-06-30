<?php

namespace App\Filament\Resources\FeedItems\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FeedItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('section')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('subtitle'),
                FileUpload::make('image')
                    ->image(),
                TextInput::make('badge'),
                TextInput::make('rating'),
                TextInput::make('link_type'),
                TextInput::make('link_id'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
