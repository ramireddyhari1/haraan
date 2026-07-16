<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $password
 * @property string|null $phone
 * @property string|null $avatar
 * @property string      $role
 * @property string      $status
 * @property string|null $partner_type
 * @property string|null $event_host_id
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<Event>   $events
 * @property-read \Illuminate\Database\Eloquent\Collection<Booking> $bookings
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;

    /** Super-admins see every workspace. */
    private const SUPER_ROLES = ['ADMIN', 'COADMIN'];

    /** Department roles → the workspaces they may manage. */
    private const DEPT_ROLES = ['FINANCE', 'MARKETING', 'OPS', 'PARTNER'];

    /** Which roles may manage each workspace key. */
    private const WORKSPACE_ROLES = [
        'finance' => ['FINANCE'],
        'marketing' => ['MARKETING'],
        'gamehub' => ['OPS', 'PARTNER'],
        'events' => ['OPS'],
        'admin' => [], // super-admin only (People / System)
    ];

    /** True if this user holds the given role under either role scheme. */
    public function hasRoleEither(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }
        $legacy = in_array(strtoupper((string) ($this->role ?? '')), array_map('strtoupper', $roles), true);
        $spatie = method_exists($this, 'hasAnyRole') && $this->hasAnyRole($roles);

        return $legacy || $spatie;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRoleEither(self::SUPER_ROLES);
    }

    /** Can this user manage a given workspace? Super-admins can manage all. */
    public function canManage(string $workspace): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleEither(self::WORKSPACE_ROLES[$workspace] ?? []);
    }

    /**
     * Gate the Filament panels. Partners live in the dedicated /partner console
     * and must never reach the internal /control panel (where support threads
     * and other tenants' data would otherwise be exposed); internal department
     * staff get /control but not /partner. Super-admins get both. The
     * per-workspace clusters then decide what each role actually sees.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($panel->getId() === 'partner') {
            return $this->hasRoleEither(['PARTNER']);
        }

        // /control — internal staff only, never partners.
        return $this->hasRoleEither(['FINANCE', 'MARKETING', 'OPS']);
    }

    /**
     * The partner account that owns this user's data. Owners resolve to their
     * own id; desk staff (created with a parent_partner_id) resolve to their
     * owner, so both operate on the same venues/events/bookings. The entire
     * /api/partner surface scopes on this — see PartnerController.
     */
    public function effectivePartnerId(): int
    {
        return (int) ($this->parent_partner_id ?? $this->id);
    }

    /** Every capability a desk person can be granted (owners hold all of them). */
    public const STAFF_PERMISSIONS = ['bookings', 'checkin', 'pricing', 'reports'];

    /** True when this user is a desk person under a partner owner. */
    public function isDeskStaff(): bool
    {
        return $this->parent_partner_id !== null;
    }

    /**
     * Whether this user may perform a partner capability. Owners always may;
     * desk persons only if the capability is in their staff_permissions.
     */
    public function hasPartnerPermission(string $permission): bool
    {
        if (! $this->isDeskStaff()) {
            return true;
        }

        $perms = $this->staff_permissions;

        return is_array($perms) && in_array($permission, $perms, true);
    }

    protected $fillable = [
        'player_id',
        'name',
        'email',
        'password',
        'phone',
        'age',
        'avatar',
        'role',
        'organization_id',
        'status',
        'partner_type',
        'event_host_id',
        'parent_partner_id',
        'staff_permissions',
        'player_role',
        'playing_style',
        'is_guest',
        'district',
        'state',
        'batting_style',
        'bowling_style',
        'gender',
        'date_of_birth',
        'birth_place',
        'height',
        'nationality',
        'primary_sport',
        'sport_attributes',
        'privacy_public_profile',
        'privacy_show_stats',
        'privacy_show_district',
        'privacy_discoverable',
        'career_runs',
        'career_balls',
        'career_matches',
        'career_wickets',
        'career_runs_conceded',
        'career_overs_bowled',
        'rank_district',
        'rank_state',
        'rank_country',
        'ranked_xp',
        'casual_xp',
        'trust_score',
        'is_organizer',
    ];

    /**
     * Whether this user has a complete ActionBoard player profile — the
     * prerequisite for any ranked action (create/confirm/verify a match).
     */
    /**
     * Required sport_attributes keys per primary sport. Adding a sport is data-only — no
     * migration — because the attributes live in the `sport_attributes` JSON bag.
     */
    public const SPORT_REQUIRED_ATTRS = [
        'Cricket'    => ['role', 'batting', 'bowling'],
        'Football'   => ['position', 'foot'],
        'Badminton'  => ['format', 'hand'],
        'Basketball' => ['position', 'hand'],
    ];

    public function isActionboardProfileComplete(): bool
    {
        if ($this->is_guest) {
            return false;
        }

        foreach (['name', 'state', 'district', 'primary_sport'] as $field) {
            if (empty($this->{$field})) {
                return false;
            }
        }

        $attrs = $this->sport_attributes ?? [];
        foreach (self::SPORT_REQUIRED_ATTRS[$this->primary_sport] ?? [] as $key) {
            if (empty($attrs[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The member ID: HRN + the zero-padded account number (id 60 → HRN00060).
     *
     * Derived from the primary key, so it is unique by construction and needs no
     * collision loop. Every member gets their real ID the moment the row exists —
     * there is no temporary form to "upgrade" later.
     */
    public static function memberId(int $userId): string
    {
        return 'HRN'.str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Anything that isn't a real member ID: blank, a legacy 6-digit random, or one
     * of the old structured/guest forms (HRN-AP-YSR-00002, HRN-GST-1234).
     */
    public static function isPlaceholderPlayerId(?string $playerId): bool
    {
        $playerId = trim((string) $playerId);

        return $playerId === ''
            || preg_match('/^\d+$/', $playerId) === 1
            || str_starts_with($playerId, 'HRN-');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (User $user): void {
            // A guest is a placeholder player on someone's squad, not an account, so it
            // keeps its own prefix until it's claimed.
            if (blank($user->player_id) && $user->is_guest) {
                $user->player_id = 'HRN-GST-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            }
            // A member's ID is left blank here on purpose: it's the account number, which
            // the database only assigns on insert. `created` stamps it a moment later.
        });

        static::created(function (User $user): void {
            if (! $user->is_guest && blank($user->player_id)) {
                $user->player_id = self::memberId((int) $user->id);
                $user->saveQuietly();
            }
        });

        static::saving(function (User $user): void {
            // Normalise anything still carrying an old placeholder — including a guest
            // that has just been claimed into a real account.
            if (! $user->is_guest && $user->exists && self::isPlaceholderPlayerId($user->player_id)) {
                $user->player_id = self::memberId((int) $user->id);
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'password'          => 'hashed',
            'date_of_birth'     => 'date',
            'sport_attributes'  => 'array',
            'staff_permissions' => 'array',
            'privacy_public_profile' => 'boolean',
            'privacy_show_stats'     => 'boolean',
            'privacy_show_district'  => 'boolean',
            'privacy_discoverable'   => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Record an activity heartbeat, throttled so we write at most once every few
     * minutes per user instead of on every single authenticated request. Uses a
     * quiet update (no `updated_at` bump) so activity tracking never masquerades
     * as a profile edit in the admin.
     */
    public function touchLastSeen(int $throttleSeconds = 300): void
    {
        $now = now();

        if ($this->last_seen_at !== null && $this->last_seen_at->gt($now->copy()->subSeconds($throttleSeconds))) {
            return;
        }

        $this->last_seen_at = $now;
        static::withoutTimestamps(fn () => $this->saveQuietly());

        // Append-only day log for the DAU trend. insertOrIgnore is a single statement
        // that no-ops on the (user_id, activity_date) unique key, so repeat hits in the
        // same day are free. Runs only past the throttle above → ≤ a few times/day/user.
        \App\Models\UserActivityDay::query()->insertOrIgnore([
            'user_id' => $this->getKey(),
            'activity_date' => $now->toDateString(),
            'created_at' => $now,
        ]);
    }

    // -------------------------------------------------------------------------
    //  Two-factor (app authenticator / TOTP) — Filament MFA
    // -------------------------------------------------------------------------

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email ?? $this->name ?? 'Haraan';
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    // -------------------------------------------------------------------------
    //  Relationships
    // -------------------------------------------------------------------------

    /** Events created/managed by this user (as a partner). */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'partner_id');
    }

    /** Bookings placed by this user. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Organization units this user belongs to (pivot: designation, is_primary). */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationUnit::class,
            'user_organization_map',
            'user_id',
            'organization_id'
        )->withPivot(['designation', 'is_primary'])->withTimestamps();
    }

    /** This user's home/primary organization unit (the future tenant key). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_id');
    }

    /**
     * Organization ids this admin is scoped to within the control panel, or null
     * if unrestricted. Lockout-proof by design:
     *   - super_admin             → null (sees everything)
     *   - no org assigned at all   → null (preserves pre-tenancy behavior)
     *   - has org(s)               → that org subtree (home org + pivot orgs, each
     *                                expanded to descendants)
     * This only drives Filament resource queries — it never touches the mobile API.
     *
     * @return array<int>|null
     */
    public function scopedOrganizationIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $roots = collect([$this->organization_id])
            ->merge($this->organizations->pluck('id'))
            ->filter()
            ->unique();

        if ($roots->isEmpty()) {
            return null;
        }

        return OrganizationUnit::whereIn('id', $roots->all())
            ->get()
            ->flatMap->descendantAndSelfIds()
            ->unique()
            ->values()
            ->all();
    }
}
