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
     * Gate the Filament admin panel: super-admins or any department role get in;
     * the per-workspace clusters then decide what each role actually sees.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSuperAdmin() || $this->hasRoleEither(self::DEPT_ROLES);
    }

    protected $fillable = [
        'player_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'organization_id',
        'status',
        'partner_type',
        'event_host_id',
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

    public static function generatePlayerId($state, $district, $userId): string
    {
        // 1. State code: e.g. "Andhra Pradesh" -> "AP", "Telangana" -> "TG"
        $stateClean = preg_replace('/[^A-Za-z ]/', '', $state ?? '');
        $stateWords = array_filter(explode(' ', $stateClean));
        if (count($stateWords) >= 2) {
            $statePart = '';
            foreach ($stateWords as $word) {
                $statePart .= substr($word, 0, 1);
            }
        } else {
            $statePart = substr($stateClean, 0, 2);
        }
        $statePart = strtoupper($statePart);
        if (strlen($statePart) < 2) {
            $statePart = str_pad($statePart, 2, 'S');
        }

        // 2. District code: Kadapa -> KDP (disemvowel)
        $distClean = preg_replace('/[^A-Za-z]/', '', $district ?? '');
        $firstLetter = substr($distClean, 0, 1);
        $rest = substr($distClean, 1);
        $restNoVowels = preg_replace('/[aeiouAEIOU]/', '', $rest);
        $distPart = strtoupper($firstLetter . $restNoVowels);
        $distPart = substr($distPart, 0, 3);
        if (strlen($distPart) < 3) {
            $distPart = str_pad($distPart, 3, 'D');
        }

        // 3. Unique player number
        $numPart = str_pad((string)$userId, 5, '0', STR_PAD_LEFT);

        // Compact shareable handle: HRN + account number (e.g. HRN00002). State/district are
        // surfaced separately in the app, so the id itself stays short and easy to read/share.
        return 'HRN'.$numPart;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->player_id)) {
                // If it is a guest player, we can assign a guest ID prefix
                if ($user->is_guest) {
                    $randomSuffix = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                    $user->player_id = 'HRN-GST-' . $randomSuffix;
                } else {
                    do {
                        $pid = (string)random_int(100000, 999999);
                    } while (self::where('player_id', $pid)->exists());
                    $user->player_id = $pid;
                }
            }
        });

        static::saving(function ($user) {
            // Update temporary numeric IDs or empty IDs to structured ones if state & district are filled
            if (!$user->is_guest && !empty($user->state) && !empty($user->district)) {
                $isTempId = empty($user->player_id) || preg_match('/^\d+$/', $user->player_id) || str_starts_with($user->player_id, 'HRN-GST');
                if ($isTempId) {
                    // We need a userId. If saving a new user, user->id might be null during creating hook, 
                    // so we do it in saving or fallback to random/incrementing if we don't have user ID yet.
                    $userId = $user->id;
                    if (!$userId) {
                        // Get next ID from DB auto_increment/sequence fallback
                        $userId = (self::max('id') ?? 0) + 1;
                    }
                    $user->player_id = self::generatePlayerId($user->state, $user->district, $userId);
                }
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
            'password'          => 'hashed',
            'date_of_birth'     => 'date',
            'sport_attributes'  => 'array',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
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
