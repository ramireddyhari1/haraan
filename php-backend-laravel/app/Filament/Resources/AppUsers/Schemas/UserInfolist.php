<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\Schemas;

use App\Http\Resources\UserAdminResource;
use App\Models\User;
use App\Support\ContactPrefill;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // 0. Pinned Operator Notes Callout Banner
            Section::make('Pinned Staff Notice')
                ->icon('heroicon-s-bookmark')
                ->visible(fn (User $record): bool => $record->userNotes()->pinned()->exists())
                ->schema([
                    TextEntry::make('pinned_notes')
                        ->label('')
                        ->state(function (User $record): string {
                            $notes = $record->userNotes()->pinned()->with('author')->latest('created_at')->get();
                            return $notes->map(fn ($n) => "📌 [" . strtoupper($n->category) . "] " . ($n->title ? "{$n->title}: " : '') . "{$n->content} — by " . ($n->author?->name ?? 'Staff') . " (" . $n->created_at->diffForHumans() . ")")->implode("\n\n");
                        })
                        ->color('warning')
                        ->wrap(),
                ]),

            // 1. User Header & Primary Identity
            Section::make('User Identity & Contact')
                ->description('Core account identity, verified credentials, and communication details.')
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    ImageEntry::make('avatar')
                        ->label('')
                        ->circular()
                        ->disk('public')
                        ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?background=EEF2FF&color=4F46E5&bold=true&name='
                            . urlencode((string) ($record->name ?: 'User'))),

                    Grid::make(['default' => 1, 'sm' => 2])
                        ->columnSpan(['default' => 1, 'md' => 3])
                        ->schema([
                            TextEntry::make('name')
                                ->label('Full name')
                                ->weight('bold')
                                ->size(TextSize::Large),

                            TextEntry::make('player_id')
                                ->label('Player ID')
                                ->badge()
                                ->color('info')
                                ->copyable()
                                ->formatStateUsing(fn (?string $state, User $record): string => $state ?: User::memberId($record->id)),

                            TextEntry::make('username')
                                ->label('Handle')
                                ->formatStateUsing(fn (?string $state): string => $state ? "@{$state}" : '—'),

                            TextEntry::make('status')
                                ->label('Account status')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                                ->color(fn (?string $state): string => strtolower((string) $state) === 'active' ? 'success' : 'danger'),

                            TextEntry::make('role')
                                ->label('Role')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                                ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                                    'admin', 'coadmin' => 'info',
                                    'partner' => 'warning',
                                    default => 'gray',
                                }),

                            TextEntry::make('is_verified')
                                ->label('Blue tick verification')
                                ->badge()
                                ->formatStateUsing(fn (bool $state, User $record): string => $state
                                    ? 'Verified' . ($record->verified_at ? ' · ' . $record->verified_at->format('d M Y') : '')
                                    : 'Unverified')
                                ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                                ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-badge' : 'heroicon-m-x-circle'),
                        ]),

                    TextEntry::make('email')
                        ->label('Email address')
                        ->icon('heroicon-m-envelope')
                        ->state(function (User $record): string {
                            if (! ContactPrefill::isRealEmail($record->email)) {
                                return '— (Phone-only signup)';
                            }

                            return self::canViewPii($record)
                                ? (string) $record->email
                                : (UserAdminResource::maskEmail($record->email) ?? '—');
                        })
                        ->copyable(fn (User $record): bool => self::canViewPii($record) && ContactPrefill::isRealEmail($record->email)),

                    TextEntry::make('phone')
                        ->label('Phone number')
                        ->icon('heroicon-m-phone')
                        ->state(function (User $record): string {
                            if (blank($record->phone)) {
                                return '—';
                            }

                            return self::canViewPii($record)
                                ? (string) $record->phone
                                : (UserAdminResource::maskPhone($record->phone) ?? '—');
                        })
                        ->copyable(fn (User $record): bool => self::canViewPii($record) && filled($record->phone)),

                    TextEntry::make('location')
                        ->label('Location')
                        ->icon('heroicon-m-map-pin')
                        ->state(fn (User $record): string => implode(', ', array_filter([$record->district, $record->state, $record->nationality])) ?: '—'),

                    TextEntry::make('dob_and_gender')
                        ->label('Age & Date of birth')
                        ->icon('heroicon-m-cake')
                        ->state(function (User $record): string {
                            $gender = $record->gender ? ucfirst($record->gender) : null;
                            $dob = null;

                            if ($record->date_of_birth) {
                                $dob = self::canViewPii($record)
                                    ? $record->date_of_birth->format('d M Y')
                                    : UserAdminResource::maskDate($record->date_of_birth);
                            }

                            $parts = array_filter([$record->age ? "{$record->age} yrs" : null, $dob, $gender]);

                            return $parts !== [] ? implode(' · ', $parts) : '—';
                        }),
                ]),

            // 2. GameHub & Sports Profile
            Section::make('GameHub & Sports Profile')
                ->description('Player statistics, ActionBoard ranking, and sports telemetry.')
                ->collapsible()
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    TextEntry::make('primary_sport')
                        ->label('Primary sport')
                        ->badge()
                        ->color('info')
                        ->placeholder('None specified'),

                    TextEntry::make('player_role')
                        ->label('Playing role')
                        ->badge()
                        ->placeholder('—'),

                    TextEntry::make('batting_bowling')
                        ->label('Style')
                        ->state(fn (User $record): string => implode(' / ', array_filter([$record->batting_style, $record->bowling_style])) ?: '—'),

                    TextEntry::make('trust_score')
                        ->label('Trust score')
                        ->numeric()
                        ->badge()
                        ->color(fn (?int $state): string => ($state ?? 100) >= 80 ? 'success' : 'danger'),

                    TextEntry::make('ranked_xp')
                        ->label('Ranked XP')
                        ->numeric()
                        ->formatStateUsing(fn ($state): string => number_format((int) $state)),

                    TextEntry::make('casual_xp')
                        ->label('Casual XP')
                        ->numeric()
                        ->formatStateUsing(fn ($state): string => number_format((int) $state)),

                    TextEntry::make('ranks')
                        ->label('District / State / National Rank')
                        ->state(fn (User $record): string => '#' . ($record->rank_district ?: '—') . ' Dist · #' . ($record->rank_state ?: '—') . ' State · #' . ($record->rank_country ?: '—') . ' Country'),

                    TextEntry::make('career_stats')
                        ->label('Career Figures')
                        ->state(fn (User $record): string => number_format((int) $record->career_matches) . ' matches · '
                            . number_format((int) $record->career_runs) . ' runs · '
                            . number_format((int) $record->career_wickets) . ' wickets ('
                            . ($record->career_overs_bowled ?: '0.0') . ' ov)'),
                ]),

            // 3. Security, Sessions & Device Telemetry
            Section::make('Security & Session Telemetry')
                ->description('Access credentials, active sessions, and authentication state.')
                ->collapsible()
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Registered at')
                        ->dateTime('d M Y, g:i A')
                        ->icon('heroicon-m-calendar'),

                    TextEntry::make('last_seen_at')
                        ->label('Last seen presence')
                        ->state(fn (User $record): string => $record->last_seen_at
                            ? $record->last_seen_at->diffForHumans() . ' (' . $record->last_seen_at->format('d M Y, H:i') . ')'
                            : 'Never active')
                        ->icon('heroicon-m-signal'),

                    TextEntry::make('email_verified_at')
                        ->label('Email verified')
                        ->state(fn (User $record): string => $record->email_verified_at
                            ? $record->email_verified_at->format('d M Y, g:i A')
                            : 'Unverified')
                        ->badge()
                        ->color(fn (User $record): string => $record->email_verified_at ? 'success' : 'gray'),

                    TextEntry::make('token_version')
                        ->label('Session token version')
                        ->badge()
                        ->color('info')
                        ->formatStateUsing(fn ($state): string => 'v' . ((int) $state) . ' (active)'),

                    TextEntry::make('two_factor')
                        ->label('Two-factor auth (TOTP)')
                        ->state(fn (User $record): string => ! empty($record->app_authentication_secret) ? 'Enabled' : 'Disabled')
                        ->badge()
                        ->color(fn (User $record): string => ! empty($record->app_authentication_secret) ? 'success' : 'gray'),

                    TextEntry::make('devices_count')
                        ->label('Registered push devices')
                        ->state(fn (User $record): string => (string) $record->deviceTokens()->count() . ' device(s)'),
                ]),
        ]);
    }

    public static function canViewPii(User $target): bool
    {
        $operator = auth()->user();

        return $operator !== null && (
            $operator->isSuperAdmin()
            || (method_exists($operator, 'can') && $operator->can('users.pii.view'))
            || (int) $operator->id === (int) $target->id
        );
    }
}
