<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\AvatarColumn;
use App\Models\User;
use App\Support\ContactPrefill;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The people list, rebuilt for operators rather than as a raw column dump. A tight
 * default set (who they are, whether they're active, where, their role) with the
 * player-stat detail tucked behind the column toggle, plus filters that make the
 * activity data actionable — find everyone online now, or everyone who has lapsed.
 */
class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                AvatarColumn::make(
                    'avatar',
                    nameFor: fn (User $r): string => (string) ($r->name ?: 'Unnamed'),
                    avatarFor: fn (User $r): ?string => $r->avatar,
                ),
                TextColumn::make('name')
                    ->weight('bold')
                    // Show a REAL contact line: the placeholder `<phone>@whatsapp.local`
                    // address that phone-signup users carry isn't shown — their phone is.
                    ->description(fn (User $r): ?string => self::contactLine($r))
                    ->placeholder('Unnamed')
                    ->searchable(['name', 'email', 'phone']),

                TextColumn::make('presence')
                    ->label('Activity')
                    ->badge()
                    ->state(fn (User $r): string => self::presenceLabel($r->last_seen_at))
                    ->color(fn (User $r): string => self::presenceColor($r->last_seen_at)),

                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->placeholder('Never')
                    ->sortable()
                    ->tooltip(fn (User $r): ?string => $r->last_seen_at?->format('d M Y, H:i')),

                TextColumn::make('district')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(strtolower($state)) : '—')
                    // Roles are a privilege, not an alarm — a calm scale, no red. Red
                    // stays reserved for genuinely bad account states (below).
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'admin', 'coadmin' => 'info',
                        'partner' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Account')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(strtolower($state)) : '—')
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'active' => 'success',
                        'banned', 'suspended', 'blocked' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('d M Y')
                    ->sortable(),

                // --- Detail, hidden by default (toggle on when needed) ---
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('primary_sport')
                    ->label('Sport')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('player_id')
                    ->label('Player ID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ranked_xp')
                    ->label('Ranked XP')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('trust_score')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Drill-in for the "New this week" KPI tile above the table.
                SelectFilter::make('joined')
                    ->label('Joined')
                    ->options([
                        'today' => 'Today',
                        'week'  => 'This week',
                        'month' => 'This month',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $now = now();

                        return match ($data['value'] ?? null) {
                            'today' => $query->where('created_at', '>=', $now->copy()->startOfDay()),
                            'week'  => $query->where('created_at', '>=', $now->copy()->subDays(6)->startOfDay()),
                            'month' => $query->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay()),
                            default => $query,
                        };
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active'    => 'Active',
                        'suspended' => 'Suspended',
                    ])
                    // Status casing is mixed in the DB (ACTIVE/active), so match case-insensitively.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(status) = ?', [strtolower($data['value'])])
                        : $query),

                SelectFilter::make('activity')
                    ->label('Activity')
                    ->options([
                        'online'   => 'Online now (5 min)',
                        'today'    => 'Active today',
                        'week'     => 'Active this week',
                        'lapsed14' => 'Lapsed · 14+ days',
                        'lapsed30' => 'Lapsed · 30+ days',
                        'never'    => 'Never active',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $now = now();

                        return match ($data['value'] ?? null) {
                            'online'   => $query->where('last_seen_at', '>=', $now->copy()->subMinutes(5)),
                            'today'    => $query->where('last_seen_at', '>=', $now->copy()->subDay()),
                            'week'     => $query->where('last_seen_at', '>=', $now->copy()->subDays(7)),
                            'lapsed14' => $query->whereNotNull('last_seen_at')->where('last_seen_at', '<', $now->copy()->subDays(14)),
                            'lapsed30' => $query->whereNotNull('last_seen_at')->where('last_seen_at', '<', $now->copy()->subDays(30)),
                            'never'    => $query->whereNull('last_seen_at'),
                            default    => $query,
                        };
                    }),

                SelectFilter::make('role')
                    ->options([
                        'user'    => 'User',
                        'partner' => 'Partner',
                        'coadmin' => 'Co-admin',
                        'admin'   => 'Admin',
                    ])
                    // Column casing is mixed in the DB, so match case-insensitively.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereRaw('lower(role) = ?', [strtolower($data['value'])])
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * A clean contact sub-line for the name cell: the real email when there is
     * one, otherwise the phone. Never the `<phone>@whatsapp.local` placeholder.
     */
    private static function contactLine(User $r): ?string
    {
        if (ContactPrefill::isRealEmail($r->email)) {
            return $r->email;
        }

        $phone = trim((string) $r->phone);

        return $phone !== '' ? $phone : null;
    }

    private static function presenceLabel(?Carbon $seen): string
    {
        if ($seen === null) {
            return 'Away';
        }
        if ($seen->gt(now()->subMinutes(5))) {
            return 'Online';
        }
        if ($seen->gt(now()->subDay())) {
            return 'Today';
        }
        if ($seen->gt(now()->subDays(7))) {
            return 'This week';
        }

        return 'Away';
    }

    private static function presenceColor(?Carbon $seen): string
    {
        return match (self::presenceLabel($seen)) {
            'Online' => 'success',
            'Today' => 'info',
            'This week' => 'warning',
            default => 'gray',
        };
    }
}
