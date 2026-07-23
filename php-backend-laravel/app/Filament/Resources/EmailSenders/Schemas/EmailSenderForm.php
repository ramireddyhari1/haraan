<?php

namespace App\Filament\Resources\EmailSenders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmailSenderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Label')
                    ->placeholder('e.g. OTP sender #1')
                    ->maxLength(120),

                TextInput::make('username')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->placeholder('haraan.otp1@gmail.com'),

                TextInput::make('app_password')
                    ->label('App password')
                    ->password()
                    ->revealable()
                    // Encrypted at rest. On edit the field is blank; leaving it blank keeps the
                    // stored password, so we never round-trip the secret to the browser.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('16-char Google App Password (not your normal password). Leave blank to keep the current one.')
                    ->maxLength(255),

                TextInput::make('from_name')
                    ->label('From name')
                    ->default('Haraan')
                    ->maxLength(120),

                TextInput::make('host')
                    ->label('SMTP host')
                    ->default('smtp.gmail.com')
                    ->required(),

                TextInput::make('port')
                    ->numeric()
                    ->default(465)
                    ->required(),

                Select::make('encryption')
                    ->options(['ssl' => 'SSL (port 465)', 'tls' => 'STARTTLS (port 587)'])
                    ->default('ssl')
                    ->required(),

                TextInput::make('daily_limit')
                    ->label('Daily send limit')
                    ->numeric()
                    ->default(450)
                    ->helperText('Gmail caps ~500/day per account; keep some headroom.')
                    ->required(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
