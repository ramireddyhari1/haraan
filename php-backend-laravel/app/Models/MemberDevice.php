<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One phone (app install) or browser a member is signed in on. The member app's JWT carries
 * [public_id] as its `sid` claim; a website session stores it. Rules live in MemberDevices.
 *
 * @property int $id
 * @property int $user_id
 * @property string $public_id
 * @property string $surface
 * @property string $platform
 * @property string|null $install_id
 * @property string $name
 * @property string|null $app_version
 * @property string|null $ip_address
 * @property string $status
 * @property \Illuminate\Support\Carbon $signed_in_at
 * @property \Illuminate\Support\Carbon $last_active_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property int|null $revoked_by
 */
class MemberDevice extends Model
{
    public const SURFACE_APP = 'app';

    public const SURFACE_WEB = 'web';

    /** Counts toward the plan's limit. */
    public const STATUS_ACTIVE = 'active';

    /** Signed in, but waiting at the device chooser for a free slot. Doesn't count. */
    public const STATUS_PENDING = 'pending';

    /** Signed out. Its token or session no longer works. */
    public const STATUS_REVOKED = 'revoked';

    public const REASON_SIGNED_OUT = 'signed_out';

    public const REASON_REMOVED = 'removed_by_member';

    public const REASON_ADMIN = 'admin';

    protected $fillable = [
        'user_id', 'public_id', 'surface', 'platform', 'install_id', 'name', 'app_version',
        'ip_address', 'status', 'signed_in_at', 'last_active_at', 'expires_at',
        'revoked_at', 'revoked_reason', 'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'signed_in_at' => 'datetime',
            'last_active_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rows that are still signed in: not revoked, token not past its expiry, and used within
     * the idle window. A phone whose 7-day token ran out, or a browser nobody opened for a
     * month, frees its slot by itself — a lost phone never holds one forever.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query, int $idleDays): void
    {
        $now = now();
        $query->where('status', '!=', self::STATUS_REVOKED)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->where('last_active_at', '>', $now->copy()->subDays(max(1, $idleDays)));
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
