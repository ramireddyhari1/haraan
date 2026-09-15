<?php

namespace App\Filament\Resources\Ads\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
                    ->url()
                    // http(s) only: the apps and the site refuse anything else, so a
                    // javascript: or intent: link would save and then silently never open.
                    ->rule('regex:/^https?:\/\//i'),
                // A fixed list: a free-text placement that matches no slot saved fine and
                // then never appeared anywhere. An older row keeps its stored value visible.
                Select::make('placement')
                    ->required()
                    ->native(false)
                    ->options(fn ($record) => \App\Models\Ad::PLACEMENTS
                        + ($record && ! array_key_exists((string) $record->placement, \App\Models\Ad::PLACEMENTS)
                            ? [(string) $record->placement => $record->placement . ' (not shown anywhere)']
                            : []))
                    ->default('match_live'),
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
