<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerStaff;

use App\Filament\Resources\PartnerStaff\Pages\CreatePartnerStaff;
use App\Filament\Resources\PartnerStaff\Pages\EditPartnerStaff;
use App\Filament\Resources\PartnerStaff\Pages\ListPartnerStaff;
use App\Filament\Support\AvatarColumn;
use App\Models\User;
use App\Services\EmailOtpService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Desk & scanner team for a partner. Staff are Users tied to the owner by
 * parent_partner_id, with a subset of capabilities (staff_permissions). This is
 * the web twin of the /api/partner/staff endpoints the app already uses.
 *
 * Only owners (not staff themselves) manage the team, and only in the /partner
 * console — the query is hard-scoped to the owner's own staff. Owners pick a
 * BookMyShow-style role preset (or fine-tune raw permissions), invite by email
 * so the member sets their own password, and can suspend without deleting.
 */
class PartnerStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Staff & team';

    protected static ?string $modelLabel = 'staff member';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    /** Short chip labels for each capability (the form uses the long descriptions). */
    private const PERM_SHORT = [
        'bookings' => 'Bookings',
        'checkin' => 'Check-in',
        'pricing' => 'Listings',
        'reports' => 'Reports',
    ];

    /** Owners only, in the partner console only. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && ! $user->isDeskStaff();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('parent_partner_id', auth()->user()?->effectivePartnerId())
            ->withCount(['assignedVenues', 'assignedEvents']);
    }

    /** Is the owner managing this team an event organiser (vs a venue owner)? */
    private static function ownerIsEventLane(): bool
    {
        return auth()->user()?->partner_type === 'event';
    }

    // The User model has an admin-only policy; bypass it here — access is the
    // owner check in canAccess(), and every query is already scoped to the
    // owner's own staff, so create/edit/delete are safe within that lane.
    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }

    /** Long, self-documenting labels for the permission checkboxes. */
    private static function permissionOptions(): array
    {
        return [
            'bookings' => 'Bookings — view & manage bookings, take offline bookings',
            'checkin' => 'Check-in — scan tickets at the gate',
            'pricing' => 'Listings & pricing — create/edit events, venues, prices & slots',
            'reports' => 'Reports & earnings — revenue, analytics & exports',
        ];
    }

    /** Role-preset dropdown options: the named bundles plus a custom escape hatch. */
    private static function presetOptions(): array
    {
        $options = [];
        foreach (User::STAFF_ROLE_PRESETS as $key => $preset) {
            $options[$key] = $preset['label'];
        }
        $options['custom'] = 'Custom…';

        return $options;
    }

    /** The preset key an exact permission set maps to, or 'custom'. */
    private static function presetForPermissions(array $permissions): string
    {
        $perms = collect($permissions)->sort()->values()->all();

        foreach (User::STAFF_ROLE_PRESETS as $key => $preset) {
            if (collect($preset['permissions'])->sort()->values()->all() === $perms) {
                return $key;
            }
        }

        return 'custom';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Member')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(190)
                        ->unique(table: 'users', column: 'email', ignoreRecord: true),
                ])
                ->columns(2),

            Section::make('Access')
                ->description('Pick a role to apply a ready-made set of permissions, then fine-tune if you need to.')
                ->schema([
                    Select::make('access_preset')
                        ->label('Role')
                        ->options(self::presetOptions())
                        ->default('box_office')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Select $component, ?Model $record): void {
                            if ($record instanceof User) {
                                $component->state($record->staffRolePreset());
                            }
                        })
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if ($state !== null && $state !== 'custom' && isset(User::STAFF_ROLE_PRESETS[$state])) {
                                $set('staff_permissions', User::STAFF_ROLE_PRESETS[$state]['permissions']);
                            }
                        }),
                    CheckboxList::make('staff_permissions')
                        ->label('Permissions')
                        ->options(self::permissionOptions())
                        ->columns(1)
                        ->live()
                        ->afterStateUpdated(function (?array $state, Set $set): void {
                            // Reflect a hand-tuned set back onto the preset selector.
                            $set('access_preset', self::presetForPermissions($state ?? []));
                        })
                        ->helperText('Exactly what this team member can do — on the web console and in the app.'),
                ]),

            Section::make('Assigned to')
                ->description('Optionally limit this member to specific ' . (self::ownerIsEventLane() ? 'events' : 'venues') . '. Leave empty to allow all of yours.')
                ->schema([
                    Select::make('assignedEvents')
                        ->label('Events')
                        ->relationship(
                            'assignedEvents',
                            'title',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->where('partner_id', auth()->user()?->effectivePartnerId()),
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->visible(fn (): bool => self::ownerIsEventLane())
                        ->helperText('Leave empty to allow all your events.'),
                    Select::make('assignedVenues')
                        ->label('Venues')
                        ->relationship(
                            'assignedVenues',
                            'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->where('partner_id', auth()->user()?->effectivePartnerId()),
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->visible(fn (): bool => ! self::ownerIsEventLane())
                        ->helperText('Leave empty to allow all your venues.'),
                ]),

            Section::make('Sign-in')
                ->schema([
                    Toggle::make('send_invite')
                        ->label('Email an invite so they set their own password')
                        ->default(true)
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'create')
                        ->helperText('Recommended — they get a secure link (valid 60 minutes) to choose a password.'),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->minLength(6)
                        ->helperText(fn (string $operation): string => $operation === 'edit'
                            ? 'Leave blank to keep the current password.'
                            : 'At least 6 characters.')
                        ->required(fn (string $operation, Get $get): bool => $operation === 'create' && ! $get('send_invite'))
                        ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' || ! $get('send_invite'))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                AvatarColumn::make('avatar', fn (User $r): ?string => $r->name, fn (User $r): ?string => $r->avatar),
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (User $r): ?string => $r->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('role_preset')
                    ->label('Role')
                    ->badge()
                    ->getStateUsing(fn (User $r): string => self::presetOptions()[$r->staffRolePreset()] ?? 'Custom')
                    ->color(fn (User $r): string => $r->staffRolePreset() === 'custom' ? 'gray' : 'info'),
                TextColumn::make('staff_permissions')
                    ->label('Can')
                    ->badge()
                    ->placeholder('No access')
                    ->getStateUsing(fn (User $r): array => array_map(
                        fn (string $p): string => self::PERM_SHORT[$p] ?? ucfirst($p),
                        $r->staff_permissions ?? [],
                    ))
                    ->color('gray'),
                TextColumn::make('scope')
                    ->label('Scope')
                    ->badge()
                    ->color(fn (User $r): string => self::scopeCount($r) === 0 ? 'success' : 'warning')
                    ->getStateUsing(fn (User $r): string => self::scopeLabel($r)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (User $r): string => self::statusLabel($r))
                    ->color(fn (User $r): string => self::statusColor($r)),
                TextColumn::make('last_seen_at')
                    ->label('Last active')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('resendInvite')
                    ->label('Resend invite')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (User $r): bool => $r->last_seen_at === null)
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $r): string => "Email a fresh set-password link to {$r->email}.")
                    ->action(function (User $r): void {
                        self::sendInvite($r);
                        Notification::make()->title('Invite sent')->success()->send();
                    }),
                Action::make('toggleStatus')
                    ->label(fn (User $r): string => self::isSuspended($r) ? 'Reactivate' : 'Suspend')
                    ->icon(fn (User $r): BackedEnum => self::isSuspended($r) ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedNoSymbol)
                    ->color(fn (User $r): string => self::isSuspended($r) ? 'success' : 'warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $r): string => self::isSuspended($r)
                        ? 'They will be able to sign in again immediately.'
                        : 'They will be signed out and blocked from the console until reactivated.')
                    ->action(function (User $r): void {
                        $r->status = self::isSuspended($r) ? 'ACTIVE' : 'SUSPENDED';
                        $r->save();
                        Notification::make()
                            ->title(self::isSuspended($r) ? 'Suspended' : 'Reactivated')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()->label('Remove'),
            ])
            ->emptyStateHeading('No team members yet')
            ->emptyStateDescription('Add desk or gate staff and choose exactly what they can do.')
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateActions([
                // Plain link to the create page (no model-policy gate to trip on),
                // so the empty state always offers a clear way in.
                Action::make('createStaff')
                    ->label('Create staff')
                    ->icon(Heroicon::OutlinedPlus)
                    ->button()
                    ->visible(fn (): bool => static::canCreate())
                    ->url(fn (): string => static::getUrl('create')),
            ]);
    }

    /** How many venues/events (for the owner's lane) this member is limited to; 0 = all. */
    private static function scopeCount(User $user): int
    {
        return self::ownerIsEventLane()
            ? (int) ($user->assigned_events_count ?? $user->assignedEvents()->count())
            : (int) ($user->assigned_venues_count ?? $user->assignedVenues()->count());
    }

    /** Human label for the scope column: "All events" / "2 venues" etc. */
    private static function scopeLabel(User $user): string
    {
        $n = self::scopeCount($user);
        $noun = self::ownerIsEventLane() ? 'event' : 'venue';

        if ($n === 0) {
            return 'All ' . $noun . 's';
        }

        return $n . ' ' . $noun . ($n === 1 ? '' : 's');
    }

    private static function isSuspended(User $user): bool
    {
        return strtoupper((string) $user->status) === 'SUSPENDED';
    }

    private static function statusLabel(User $user): string
    {
        if (self::isSuspended($user)) {
            return 'Suspended';
        }

        return $user->last_seen_at === null ? 'Pending' : 'Active';
    }

    private static function statusColor(User $user): string
    {
        if (self::isSuspended($user)) {
            return 'danger';
        }

        return $user->last_seen_at === null ? 'warning' : 'success';
    }

    /**
     * Email a staff member a link to set their own password. Reuses Laravel's
     * password broker token (same table the website reset uses) and sends through
     * the admin-managed sender pool, so no extra infrastructure.
     */
    public static function sendInvite(User $user): void
    {
        $token = Password::broker()->createToken($user);
        $url = url(route('site.password.reset', ['token' => $token], false)) . '?email=' . urlencode($user->email);
        $org = auth()->user()?->name ?? 'Your organiser';

        app(EmailOtpService::class)->send(
            $user->email,
            'You’ve been added to Haraan Partner',
            "Hi {$user->name},\n\n{$org} added you to their team on Haraan Partner. "
                . "Set your password to sign in:\n\n{$url}\n\nThis link expires in 60 minutes.",
            self::inviteHtml($user->name, $url, $org),
        );
    }

    private static function inviteHtml(string $name, string $url, string $org): string
    {
        $name = htmlspecialchars($name, ENT_QUOTES);
        $org = htmlspecialchars($org, ENT_QUOTES);

        return <<<HTML
            <div style="font-family:Inter,Arial,sans-serif;max-width:460px;margin:0 auto;padding:8px">
                <h2 style="margin:0 0 6px;color:#0b1220;font-size:20px">Welcome to Haraan Partner</h2>
                <p style="margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.5">
                    Hi {$name}, {$org} added you to their team. Tap the button to set your
                    password and sign in. The link expires in 60 minutes.
                </p>
                <a href="{$url}" style="display:inline-block;background:#1e50e6;color:#fff;
                    text-decoration:none;font-weight:600;font-size:14px;padding:11px 20px;border-radius:10px">
                    Set my password
                </a>
                <p style="margin:16px 0 0;color:#9aa2b1;font-size:12px;word-break:break-all">{$url}</p>
            </div>
        HTML;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerStaff::route('/'),
            'create' => CreatePartnerStaff::route('/create'),
            'edit' => EditPartnerStaff::route('/{record}/edit'),
        ];
    }
}
