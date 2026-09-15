<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A player's career in one sport, rolled up from their finished-match rows in
 * `player_match_stats` by {@see \App\Services\Stats\PlayerCareerService}.
 */
class PlayerSportCareer extends Model
{
    protected $fillable = [
        'player_id', 'sport', 'matches', 'wins', 'losses', 'draws',
        'totals', 'bests', 'last_match_at',
    ];

    protected $casts = [
        'matches' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'draws' => 'integer',
        'totals' => 'array',
        'bests' => 'array',
        'last_match_at' => 'datetime',
    ];
}
