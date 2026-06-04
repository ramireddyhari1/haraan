<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMatchStat extends Model
{
    protected $table = 'player_match_stats';

    protected $fillable = [
        'match_id',
        'player_id',
        'player_name',
        'runs',
        'balls',
        'wickets',
        'overs_bowled',
        'runs_conceded',
    ];

    /**
     * Get the match associated with the stat.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(LiveMatch::class, 'match_id');
    }

    /**
     * Get the user player associated with the stat.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id', 'player_id');
    }
}
