<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One opted-in rewarded video. Created when the player taps "Watch to unlock"; it becomes
 * `verified` ONLY when Google's server-side verification callback arrives with a valid signature
 * and this session's nonce. The app's own "ad finished" callback is never trusted.
 */
final class RewardedAdSession extends Model
{
    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const EXPIRED = 'expired';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'nonce', 'user_id', 'grant_id', 'provider', 'ad_unit', 'status', 'transaction_id', 'expires_at', 'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function grant(): BelongsTo
    {
        return $this->belongsTo(RewardGrant::class, 'grant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
