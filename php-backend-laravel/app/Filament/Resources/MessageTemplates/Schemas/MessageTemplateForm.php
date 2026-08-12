<?php

namespace App\Filament\Resources\MessageTemplates\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Set in code — this is what the sending path looks up.'),

                TextInput::make('name')->required()->maxLength(120),

                Textarea::make('body')
                    ->label('Approved copy')
                    ->rows(6)
                    ->required()
                    ->helperText('Must match what Meta approved, {{1}} placeholders and all. '
                        . 'Editing this here does not re-submit it.'),

                KeyValue::make('variables')
                    ->label('Placeholders')
                    ->keyLabel('Position')
                    ->valueLabel('What it holds')
                    ->helperText('Documentation only — the values are filled by JourneyTemplates::variables(), '
                        . 'and the ORDER must match.'),

                TextInput::make('provider_template_id')
                    ->label('Meta template name')
                    ->placeholder('event_reminder_24h')
                    ->helperText('The exact name registered in WhatsApp Manager. Meta identifies a '
                        . 'template by name + language, so this and the locale below must both match. '
                        . 'Without it this template can never send.'),

                Select::make('status')
                    ->options([
                        'draft' => 'Draft — not submitted',
                        'submitted' => 'Submitted — waiting on Meta',
                        'approved' => 'Approved — usable outside the 24h window',
                        'rejected' => 'Rejected',
                    ])
                    ->default('draft')
                    ->required()
                    ->helperText('Only "approved" WITH a Content SID is ever used for sending.'),

                Select::make('category')
                    ->options([
                        'utility' => 'Utility — about a transaction they made',
                        'marketing' => 'Marketing — promotional, needs opt-in',
                        'authentication' => 'Authentication — codes',
                        'service' => 'Service — replies inside an open window',
                    ])
                    ->required()
                    ->helperText('Meta prices and polices these differently; it must match what was submitted.'),

                Toggle::make('is_active')->label('Active')->default(true),
            ]);
    }
}
