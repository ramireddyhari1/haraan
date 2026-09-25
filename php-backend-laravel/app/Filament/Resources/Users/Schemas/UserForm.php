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
                        // Only ever offered when creating a STAFF account, where an operator
                        // has to set the first credential. Editing an existing account never
                        // shows it: this form is shared with the app-user resource, and a
                        // silent "set any member's password" field there is an account
                        // takeover with no notification and no extra confirmation. Resetting
                        // an existing password is a deliberate, audited action instead —
                        // see EditAppUser / EditUser.
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(10)
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('The initial password for this staff account. At least 10 characters.')
                            ->columnSpanFull(),
                        Select::make('role')
                            ->label('Role / access')
                            // The record's existing role is always listed, even when the
                            // current operator may not grant it — otherwise opening an OPS
                            // colleague's record as a non-super would render an empty
                            // select and silently blank their role on save.
                            ->options(fn (?\App\Models\User $record): array => self::roleOptions($record))
                            // Display is not authorization. A crafted request can post any
                            // value, so the grantable set is re-checked server-side.
                            ->rule(fn (?\App\Models\User $record) => \Illuminate\Validation\Rule::in(
                                array_keys(self::roleOptions($record))
                            ))
                            ->disabled(fn (?\App\Models\User $record): bool => self::roleIsLocked($record))
                            ->dehydrated(fn (?\App\Models\User $record): bool => ! self::roleIsLocked($record))
                            ->required()
                            ->native(false)
                            ->default('OPS')
                            ->helperText(fn (?\App\Models\User $record): string => self::roleIsLocked($record)
                                ? 'Only an administrator can change a console role.'
                                : 'Operations / Finance / Marketing can sign into this control panel. "App user" has no admin access.'),
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
                        Toggle::make('is_verified')
                            ->label('Verified (blue tick)')
                            ->helperText('Shows a blue tick beside this player\'s name in the app. Granted here only — nobody can set it on themselves.'),
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
     * Roles selectable in the form.
     *
     * Granting a role that opens /control is itself an escalation, so only a super-admin
     * may do it. Previously ADMIN/COADMIN were hidden from non-supers but OPS/FINANCE/
     * MARKETING were not — which let an OPS operator mint a Finance console account, or
     * promote themselves via a second account. That was also inconsistent with the JSON
     * API, which has always refused to let a non-admin touch privileged roles
     * ({@see \App\Http\Controllers\Api\UsersController::updateRole}). The two paths write
     * the same column and now enforce the same rule.
     *
     * @return array<string, string>
     */
    private static function roleOptions(?\App\Models\User $record = null): array
    {
        // Roles with no console access — safe for any staff manager to assign.
        $roles = [
            'PARTNER' => 'Partner — venue / host owner (partner app)',
            'WORKER' => 'Desk staff / worker',
            'USER' => 'App user — no admin access',
        ];

        if (auth()->user()?->isSuperAdmin() ?? false) {
            $roles = self::CONSOLE_ROLES + $roles;
        }

        // Keep the record's own role visible so the field renders what it actually holds.
        // It is paired with the disabled/dehydrated guards on the field, so showing it
        // does not make it assignable.
        $current = strtoupper((string) ($record->role ?? ''));
        if ($current !== '' && ! array_key_exists($current, $roles)) {
            $roles[$current] = (self::CONSOLE_ROLES[$current] ?? ucfirst(strtolower($current))).' (current)';
        }

        return $roles;
    }

    /** Roles that grant /control access. Only a super-admin may hand one out. */
    private const CONSOLE_ROLES = [
        'ADMIN' => 'Admin — full control-panel access',
        'COADMIN' => 'Co-admin — full control-panel access',
        'OPS' => 'Operations — venues, events, bookings',
        'FINANCE' => 'Finance — payouts & reports',
        'MARKETING' => 'Marketing — ads, feed, content',
    ];

    /**
     * True when this operator must not rewrite this record's role: a non-super-admin
     * looking at someone who already holds a console role. Dehydration is switched off
     * alongside the disable, so the field is not merely greyed out in the browser — the
     * value never reaches the save.
     */
    private static function roleIsLocked(?\App\Models\User $record): bool
    {
        if (auth()->user()?->isSuperAdmin() ?? false) {
            return false;
        }

        return $record !== null
            && array_key_exists(strtoupper((string) $record->role), self::CONSOLE_ROLES);
    }
}
