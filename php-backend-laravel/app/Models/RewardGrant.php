<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The reward ledger: one row per thing a player was given.
 *
 *   locked ──(result verified / ad verified)──► available ──(claim)──► claimed ──(used)──► redeemed
 *     │                                            │
 *     └──► expired (unverified, window lapsed)     └──► expired (not claimed in time)
 *   any not-yet-claimed state ──► revoked (admin, or the match was reopened)
 *
 * A grant is locked while EITHER lock flag is set: needs_verification (money-value rewards wait
 * for a trusted result) or needs_ad (the player chose to unlock it with a rewarded video).
 *
 * dedupe_key is unique, so re-running the engine for the same match can never grant twice. When
 * a grant is revoked because its match was reopened, the key is suffixed so the re-finished
 * match can grant again while the revoked row stays as history.
 *
 * Rows are written by the reward services only. /control can read them and revoke, nothing else.
 */
final class RewardGrant extends Model
{
    public const LOCKED = 'locked';

    public const AVAILABLE = 'available';

    public const CLAIMED = 'claimed';

    public const REDEEMED = 'redeemed';

    public const EXPIRED = 'expired';

    public const REVOKED = 'revoked';

    public const STATUSES = [
        self::LOCKED => 'Locked',
        self::AVAILABLE => 'Ready to claim',
        self::CLAIMED => 'Claimed',
        self::REDEEMED => 'Used',
        self::EXPIRED => 'Expired',
        self::REVOKED => 'Revoked',
    ];

    /** States a grant can still be revoked or expired from. */
    public const OPEN = [self::LOCKED, self::AVAILABLE];

    protected $fillable = [
        'user_id', 'match_id', 'program_id', 'rule_id', 'zone_id', 'source', 'type', 'status', 'needs_verification', 'needs_ad',
        'dedupe_key', 'title', 'description', 'value', 'bonus_xp', 'coupon_id', 'reward_code_id', 'override_ids',
        'status_reason', 'unlocked_at', 'claimed_at', 'redeemed_at', 'expires_at', 'revoked_at', 'revoked_by',
        'expiry_warned_at',
    ];

    protected $casts = [
        'needs_verification' => 'boolean',
        'needs_ad' => 'boolean',
        'value' => 'array',
        'override_ids' => 'array',
        'bonus_xp' => 'integer',
        'unlocked_at' => 'datetime',
        'claimed_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expiry_warned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(RewardProgram::class, 'program_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RewardRule::class, 'rule_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(RewardZone::class, 'zone_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(LiveMatch::class, 'match_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function rewardCode(): BelongsTo
    {
        return $this->belongsTo(RewardCode::class, 'reward_code_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /**
     * The trust level this grant needs before it unlocks, when it is stricter than the
     * platform-wide floor. A location-targeted money reward carries its own bar, snapshotted at
     * grant time so that later changing the rule cannot retroactively unlock what is already out.
     */
    public function requiredTrustLevel(): ?string
    {
        $trust = ($this->value['geo']['min_trust'] ?? null);

        return is_string($trust) && $trust !== '' ? $trust : null;
    }

    /** @return list<string> why a locked grant is still locked */
    public function lockReasons(): array
    {
        if ($this->status !== self::LOCKED) {
            return [];
        }

        return array_values(array_filter([
            $this->needs_verification ? 'verification' : null,
            $this->needs_ad ? 'rewarded_ad' : null,
        ]));
    }
}
