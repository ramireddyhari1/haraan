<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Forms\OrganizationSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Staff account')
                    ->description('Who this person is and what they can access. To add an employee, give them a role that grants admin access (Operations, Finance or Marketing).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->tel(),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Set a password for the account. When editing, leave blank to keep the current one.')
                            ->columnSpanFull(),
                        Select::make('role')
                            ->label('Role / access')
                            ->options(self::roleOptions())
                            ->required()
                            ->native(false)
                            ->default('OPS')
                            ->helperText('Operations / Finance / Marketing can sign into this control panel. "App user" has no admin access.'),
                        Select::make('status')
                            ->options(['ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended'])
                            ->required()
                            ->native(false)
                            ->default('ACTIVE'),
                        OrganizationSelect::make()
                            ->helperText('Home organization — also scopes what this staff member sees in the panel.'),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at')
                            ->helperText('Optional. Set to mark the email as already verified.'),
                    ]),

                Section::make('Player profile')
                    ->description('Only relevant for app player accounts — safe to ignore for employees.')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('avatar'),
                        TextInput::make('partner_type'),
                        TextInput::make('event_host_id'),
                        TextInput::make('player_id'),
                        TextInput::make('player_role'),
                        TextInput::make('playing_style'),
                        Toggle::make('is_guest'),
                        Toggle::make('is_organizer'),
                        TextInput::make('district'),
                        TextInput::make('state'),
                        TextInput::make('gender'),
                        DatePicker::make('date_of_birth'),
                        TextInput::make('birth_place'),
                        TextInput::make('height'),
                        TextInput::make('nationality'),
                        TextInput::make('batting_style'),
                        TextInput::make('bowling_style'),
                        TextInput::make('career_runs')->numeric()->default(0),
                        TextInput::make('career_balls')->numeric()->default(0),
                        TextInput::make('career_matches')->numeric()->default(0),
                        TextInput::make('career_wickets')->numeric()->default(0),
                        TextInput::make('career_runs_conceded')->numeric()->default(0),
                        TextInput::make('career_overs_bowled')->default('0.0'),
                        TextInput::make('rank_district')->numeric(),
                        TextInput::make('rank_state')->numeric(),
                        TextInput::make('rank_country')->numeric(),
                        TextInput::make('ranked_xp')->numeric()->default(0),
                        TextInput::make('casual_xp')->numeric()->default(0),
                        TextInput::make('trust_score')->numeric()->default(100),
                    ]),
            ]);
    }

    /**
     * Roles selectable in the form. The super-admin roles (ADMIN/COADMIN) are only offered to a
     * super-admin, so a non-super staff manager can't grant or elevate someone to full access.
     *
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        $roles = [
            'OPS' => 'Operations — venues, events, bookings',
            'FINANCE' => 'Finance — payouts & reports',
            'MARKETING' => 'Marketing — ads, feed, content',
            'PARTNER' => 'Partner — venue / host owner (partner app)',
            'WORKER' => 'Desk staff / worker',
            'USER' => 'App user — no admin access',
        ];

        if (auth()->user()?->isSuperAdmin() ?? false) {
            $roles = [
                'ADMIN' => 'Admin — full control-panel access',
                'COADMIN' => 'Co-admin — full control-panel access',
            ] + $roles;
        }

        return $roles;
    }
}
