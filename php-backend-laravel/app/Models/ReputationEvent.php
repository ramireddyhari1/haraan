<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single reputation penalty levied against a player.
 */
class ReputationEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id', 'player_id');
    }
}
