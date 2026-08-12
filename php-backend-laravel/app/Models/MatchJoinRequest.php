<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's request to join an open match. Owned by the requester; resolved by the
 * match creator (accept → slotted into a squad; decline → closed).
 */
class MatchJoinRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';

    public function match(): BelongsTo
    {
        return $this->belongsTo(LiveMatch::class, 'match_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
