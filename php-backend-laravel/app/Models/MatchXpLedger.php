<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One XP award for one player in one settled match.
 */
class MatchXpLedger extends Model
{
    protected $table = 'match_xp_ledger';

    protected $guarded = [];

    protected $casts = [
        'xp'                   => 'integer',
        'base_xp'              => 'integer',
        'trust_multiplier'     => 'float',
        'diversity_multiplier' => 'float',
        'is_ranked'            => 'boolean',
        'won'                  => 'boolean',
        'mom'                  => 'boolean',
        'awarded_at'           => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(LiveMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id', 'player_id');
    }
}
