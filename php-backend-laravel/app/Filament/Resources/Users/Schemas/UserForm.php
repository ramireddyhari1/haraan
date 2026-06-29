<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('avatar'),
                TextInput::make('role')
                    ->required()
                    ->default('USER'),
                OrganizationSelect::make()
                    ->helperText('Home organization — also drives control-panel scoping for staff accounts.'),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVE'),
                TextInput::make('partner_type'),
                TextInput::make('event_host_id'),
                TextInput::make('player_id'),
                TextInput::make('player_role'),
                TextInput::make('playing_style'),
                Toggle::make('is_guest')
                    ->required(),
                TextInput::make('district'),
                TextInput::make('state'),
                TextInput::make('batting_style'),
                TextInput::make('bowling_style'),
                TextInput::make('career_runs')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('career_balls')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('career_matches')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('career_wickets')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('career_runs_conceded')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('career_overs_bowled')
                    ->required()
                    ->default('0.0'),
                TextInput::make('rank_district')
                    ->numeric(),
                TextInput::make('rank_state')
                    ->numeric(),
                TextInput::make('rank_country')
                    ->numeric(),
                TextInput::make('ranked_xp')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('casual_xp')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('trust_score')
                    ->required()
                    ->numeric()
                    ->default(100),
                Toggle::make('is_organizer')
                    ->required(),
                TextInput::make('gender'),
                DatePicker::make('date_of_birth'),
                TextInput::make('birth_place'),
                TextInput::make('height'),
                TextInput::make('nationality'),
            ]);
    }
}
