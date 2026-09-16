<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-member exception to their plan for one feature — a support comp, a creator deal.
 * It replaces the plan's value for that key while unexpired, in either direction.
 *
 * @property int $user_id
 * @property string $feature_key
 * @property bool $enabled
 * @property int|null $limit_value
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string $reason
 */
final class MemberEntitlementOverride extends Model
{
    protected $fillable = ['user_id', 'feature_key', 'enabled', 'limit_value', 'expires_at', 'reason', 'granted_by'];

    protected $casts = [
        'enabled' => 'boolean',
        'limit_value' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $flush = fn (MemberEntitlementOverride $o) => \App\Services\Membership\MemberEntitlements::flush($o->user_id);
        self::saved($flush);
        self::deleted($flush);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeInEffect(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
