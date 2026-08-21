<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
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
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;

    /** Super-admins see every workspace. */
    private const SUPER_ROLES = ['ADMIN', 'COADMIN'];

    /** Department roles → the workspaces they may manage. */
    private const DEPT_ROLES = ['FINANCE', 'MARKETING', 'OPS', 'PARTNER'];

    /**
     * Which roles may manage each workspace key. NB: PARTNER is deliberately NOT
     * listed here — partners are lane-locked by partner_type in canManage() below
     * (event organisers → Events only, venue owners → GameHub only).
     */
    private const WORKSPACE_ROLES = [
        'finance' => ['FINANCE'],
        'marketing' => ['MARKETING'],
        'gamehub' => ['OPS'],
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

    /**
     * The console this partner signs in to — see {@see \App\Support\PartnerLane},
     * which owns the lane list, the type→lane map and each lane's vocabulary.
     *
     * `partner_type` is the ONLY dimension here. It briefly shared the job with a
     * `business_type` column, which was a mistake: two columns encoding one fact
     * means they can disagree, and nothing could ever set the second one anyway
     * (the admin never offered it). One type, one lane, one capability preset.
     */
    public function partnerLane(): string
    {
        return \App\Support\PartnerLane::forType($this->partner_type);
    }

    /** Can this user manage a given workspace? Super-admins can manage all. */
    public function canManage(string $workspace): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Partners are lane-locked by partner_type — see partnerLane().
        if ($this->hasRoleEither(['PARTNER'])) {
            return in_array($workspace, \App\Support\PartnerLane::ALL, true)
                && $this->partnerLane() === $workspace;
        }

        return $this->hasRoleEither(self::WORKSPACE_ROLES[$workspace] ?? []);
    }

    /**
     * Gate the Filament panels. Partners live in the dedicated /partner console
     * and must never reach the internal /control panel (where support threads
     * and other tenants' data would otherwise be exposed); internal department
     * staff and super-admins get /control but not /partner. The /partner console
     * is for real partner accounts only. The per-workspace clusters then decide
     * what each role actually sees.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'partner') {
            // Admins belong in /control only — they are barred from the partner
            // console even if their account also happens to carry a PARTNER role,
            // so an internal login can never masquerade inside a partner workspace.
            if ($this->isSuperAdmin()) {
                return false;
            }

            // A suspended desk person keeps their login but is locked out until an
            // owner reactivates them (owners themselves are never auto-suspended here).
            if ($this->isDeskStaff() && strtoupper((string) $this->status) === 'SUSPENDED') {
                return false;
            }

            return $this->hasRoleEither(['PARTNER']);
        }

        // /control — internal staff and super-admins, never partners.
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasRoleEither(['FINANCE', 'MARKETING', 'OPS']);
    }

    /**
     * The uploaded profile photo for Filament's topbar/user menu avatar. Without
     * this, Filament falls back to a generated initials chip ("BP"). Resolved
     * through MediaUrl so a stored relative path becomes an absolute URL; null
     * (no photo) lets Filament use its initials fallback.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return \App\Support\MediaUrl::resolve($this->avatar);
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

    /**
     * BookMyShow-style role presets: a named bundle of capabilities the owner can
     * pick in one tap instead of ticking raw permissions. "Custom" is anything
     * that doesn't match a preset exactly.
     *
     * @var array<string, array{label:string, permissions:array<int,string>}>
     */
    public const STAFF_ROLE_PRESETS = [
        'manager' => ['label' => 'Manager', 'permissions' => ['bookings', 'checkin', 'pricing', 'reports']],
        'box_office' => ['label' => 'Box office', 'permissions' => ['bookings', 'checkin']],
        'gate' => ['label' => 'Gate staff', 'permissions' => ['checkin']],
        'finance' => ['label' => 'Finance', 'permissions' => ['reports']],
    ];

    /**
     * The preset key whose permission set exactly matches this staff member's
     * capabilities, or 'custom' when it's a bespoke mix. Drives the list badge and
     * the form's preset selector.
     */
    public function staffRolePreset(): string
    {
        $perms = collect($this->staff_permissions ?? [])->sort()->values()->all();

        foreach (self::STAFF_ROLE_PRESETS as $key => $preset) {
            if (collect($preset['permissions'])->sort()->values()->all() === $perms) {
                return $key;
            }
        }

        return 'custom';
    }

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

    /** This partner's public organiser page (see {@see HostProfile}). */
    public function hostProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HostProfile::class);
    }

    // -------------------------------------------------------------------------
    //  Player follows — the ActionBoard social graph
    // -------------------------------------------------------------------------

    /** Players this user follows. */
    public function following(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(self::class, 'player_follows', 'follower_id', 'followee_id')
            ->withTimestamps();
    }

    /** Players who follow this user. */
    public function followers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(self::class, 'player_follows', 'followee_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Follow another player. Idempotent — following twice is a no-op rather than a
     * duplicate row, so a double-tap on a slow connection can't break the button.
     * Returns false when the follow was refused (yourself, or a guest account).
     */
    public function follow(self $player): bool
    {
        if ($player->id === $this->id || $player->is_guest) {
            return false;
        }

        // A block refuses the follow from BOTH sides: the blocker obviously can't follow
        // someone they blocked, and the blocked person must not be able to re-attach
        // themselves either — otherwise blocking only holds until they tap Follow again.
        if (self::blockExistsBetween($this, $player)) {
            return false;
        }

        $this->following()->syncWithoutDetaching([$player->id]);

        return true;
    }

    public function unfollow(self $player): void
    {
        $this->following()->detach($player->id);
    }

    public function isFollowing(self $player): bool
    {
        return $this->following()->whereKey($player->id)->exists();
    }

    // -------------------------------------------------------------------------
    //  Blocks — the safety half of the social graph
    // -------------------------------------------------------------------------

    /** Players this user has blocked. */
    public function blockedPlayers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(self::class, 'player_blocks', 'blocker_id', 'blocked_id')
            ->withTimestamps();
    }

    /** Players who have blocked this user. Never exposed to them — gates only. */
    public function blockedByPlayers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(self::class, 'player_blocks', 'blocked_id', 'blocker_id')
            ->withTimestamps();
    }

    /**
     * Block a player. Idempotent, and it SEVERS the relationship both ways rather than
     * just recording an intention — leaving the follow rows in place would keep their
     * matches in your feed and yours in theirs, which is not what anyone means by block.
     * Returns false when the block was refused (yourself).
     */
    public function block(self $player): bool
    {
        if ($player->id === $this->id) {
            return false;
        }

        $this->blockedPlayers()->syncWithoutDetaching([$player->id]);

        // Both directions. A block is not a mute.
        $this->following()->detach($player->id);
        $player->following()->detach($this->id);

        return true;
    }

    /** Lift a block. Follows are NOT restored — that's the other person's choice again. */
    public function unblock(self $player): void
    {
        $this->blockedPlayers()->detach($player->id);
    }

    public function hasBlocked(self $player): bool
    {
        return $this->blockedPlayers()->whereKey($player->id)->exists();
    }

    public function isBlockedBy(self $player): bool
    {
        return $this->blockedByPlayers()->whereKey($player->id)->exists();
    }

    /**
     * Is there a block between these two in EITHER direction? This is the check every
     * gate wants: a one-directional test lets the blocked party keep acting on the
     * blocker, which is the whole thing being prevented.
     */
    public static function blockExistsBetween(self $a, self $b): bool
    {
        return \Illuminate\Support\Facades\DB::table('player_blocks')
            ->where(function ($q) use ($a, $b): void {
                $q->where('blocker_id', $a->id)->where('blocked_id', $b->id);
            })
            ->orWhere(function ($q) use ($a, $b): void {
                $q->where('blocker_id', $b->id)->where('blocked_id', $a->id);
            })
            ->exists();
    }

    /** Venues this desk person is explicitly limited to (Phase 3 scoping). */
    public function assignedVenues(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'staff_venues');
    }

    /** Events this desk person is explicitly limited to (Phase 3 scoping). */
    public function assignedEvents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'staff_events');
    }

    /**
     * The venue ids this user is restricted to, or null for "all their owner's".
     * Owners and non-desk users are never restricted. A desk person with no
     * assignments still sees everything — assignment is an opt-in narrowing.
     *
     * @return array<int, int>|null
     */
    public function scopedVenueIds(): ?array
    {
        if (! $this->isDeskStaff()) {
            return null;
        }

        $ids = $this->assignedVenues()->pluck('venues.id')->map(fn ($id): int => (int) $id)->all();

        return $ids === [] ? null : $ids;
    }

    /**
     * Every branch this user may act on, as a query — the tenant boundary AND the
     * per-staff assignment in one place.
     *
     * `scopedVenueIds()` answers "is this person restricted?" and returns null when
     * they are not. That shape is right for a post-hoc check on a booking that has
     * already been loaded, but it is the wrong shape for the ~20 places that fetch
     * a branch BY ID, because forgetting the null case there fails open: the desk
     * person reaches a branch they were never assigned to.
     *
     * So those callers use this instead. It is always a real query, it is never
     * null, and a branch outside it simply does not exist as far as the caller is
     * concerned — `findOrFail` turns a cross-branch id into a 404 rather than a
     * 200 with someone else's day.
     *
     *     $venue = $user->branches()->findOrFail($id);   // scoped, always
     *
     * The frontend branch switcher is a convenience. This is the boundary.
     */
    public function branches(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Venue::query()->where('partner_id', $this->effectivePartnerId());
        $assigned = $this->scopedVenueIds();

        return $assigned === null ? $query : $query->whereIn('id', $assigned);
    }

    /**
     * How much of the business this user sees. Derived, never stored — the facts
     * it reads from are already authoritative, and a stored copy would be a third
     * thing to keep in sync with the other two.
     *
     *   owner    not desk staff          → every branch, business-level screens
     *   manager  desk staff, all perms   → their branches, everything within them
     *   desk     desk staff, some perms  → their branch, this shift
     *
     * Drives the nav graph and whether the branch switcher renders as a picker or
     * as a static name. It is NOT an authorisation check — permissions and
     * {@see branches()} remain the things that actually gate.
     */
    public function partnerAltitude(): string
    {
        if (! $this->isDeskStaff()) {
            return 'owner';
        }

        $perms = is_array($this->staff_permissions) ? $this->staff_permissions : [];

        return array_diff(self::STAFF_PERMISSIONS, $perms) === [] ? 'manager' : 'desk';
    }

    /**
     * The event ids this user is restricted to, or null for "all their owner's".
     *
     * @return array<int, int>|null
     */
    public function scopedEventIds(): ?array
    {
        if (! $this->isDeskStaff()) {
            return null;
        }

        $ids = $this->assignedEvents()->pluck('events.id')->map(fn ($id): int => (int) $id)->all();

        return $ids === [] ? null : $ids;
    }

    protected $fillable = [
        'player_id',
        'username',
        'name',
        'bio',
        'email',
        'password',
        'phone',
        'age',
        'avatar',
        'role',
        'organization_id',
        'status',
        'partner_type',
        'capabilities',
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
        // Admin-granted blue tick. Only /control writes these — no API path sets them,
        // which is the whole point of a verification badge.
        'is_verified',
        'verified_at',
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

    /**
     * Handles that must never belong to a player, because a route, a support identity or
     * an impersonation attempt would read as legitimate. Checked against the normalised
     * (lowercased) handle.
     */
    public const RESERVED_USERNAMES = [
        'haraan', 'admin', 'administrator', 'root', 'support', 'help', 'official',
        'staff', 'team', 'moderator', 'mod', 'system', 'security', 'billing',
        'api', 'www', 'app', 'web', 'control', 'partner', 'login', 'signup',
        'register', 'account', 'settings', 'profile', 'me', 'you', 'null', 'undefined',
        'haraanapp', 'haraanofficial', 'actionboard', 'gamehub', 'pulse',
    ];

    /** Lowercase + trim. The stored value is ALWAYS the normalised one, so uniqueness
     *  is case-insensitive without needing a functional index (SQLite has none). */
    public static function normalizeUsername(?string $username): string
    {
        return mb_strtolower(trim((string) $username));
    }

    /**
     * Why a handle is unacceptable, or null when it is fine. Shape rules only — this
     * says nothing about whether it is already taken.
     *
     * 3-20 chars, lowercase letters/digits/underscore/dot, must start with a letter,
     * must end alphanumeric, no doubled separators. The "starts with a letter" rule
     * keeps handles from colliding with Player IDs and from being read as numbers.
     */
    public static function usernameRejection(string $normalized): ?string
    {
        if (mb_strlen($normalized) < 3) {
            return 'Usernames need at least 3 characters.';
        }
        if (mb_strlen($normalized) > 20) {
            return 'Usernames can be at most 20 characters.';
        }
        if (!preg_match('/^[a-z][a-z0-9._]*[a-z0-9]$/', $normalized)) {
            return 'Use letters, numbers, dot or underscore. Start with a letter.';
        }
        if (preg_match('/[._]{2,}/', $normalized)) {
            return 'No two dots or underscores in a row.';
        }
        if (in_array($normalized, self::RESERVED_USERNAMES, true)) {
            return 'That username is reserved.';
        }

        return null;
    }

    /** True when no OTHER account already holds this handle. */
    public static function usernameIsFree(string $normalized, ?int $ignoreUserId = null): bool
    {
        return !self::query()
            ->where('username', $normalized)
            ->when($ignoreUserId !== null, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->exists();
    }

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

            // Keep "since when" honest without asking the operator to fill in a date: the
            // stamp follows the flag wherever it's flipped (list toggle, edit form, tinker).
            if ($user->isDirty('is_verified')) {
                $user->verified_at = $user->is_verified ? now() : null;
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
            'is_verified'       => 'boolean',
            'verified_at'       => 'datetime',
            'password'          => 'hashed',
            'date_of_birth'     => 'date',
            'sport_attributes'  => 'array',
            'staff_permissions' => 'array',
            'capabilities'      => 'array',
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

    /** Venues managed by this user (as a venue-owner partner). */
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class, 'partner_id');
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
