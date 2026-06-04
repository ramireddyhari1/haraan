<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;

    protected $fillable = [
        'player_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
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
        'career_runs',
        'career_balls',
        'career_matches',
        'career_wickets',
        'career_runs_conceded',
        'career_overs_bowled',
        'rank_district',
        'rank_state',
        'rank_country',
    ];

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

        return "HRN-{$statePart}-{$distPart}-{$numPart}";
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
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
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

    /** Organization units this user belongs to. */
    public function organizations()
    {
        return $this->belongsToMany(
            \App\Models\OrganizationUnit::class,
            'user_organization_map',
            'user_id',
            'organization_id'
        );
    }
}
