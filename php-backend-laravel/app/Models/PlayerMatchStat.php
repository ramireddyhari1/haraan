<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One player's figures in one match, for any sport — written only by
 * {@see \App\Services\Stats\MatchPlayerStatsService} from the match's real log.
 */
class PlayerMatchStat extends Model
{
    protected $table = 'player_match_stats';

    protected $fillable = [
        'match_id',
        'sport',
        'side',
        'player_key',
        'player_id',
        'user_id',
        'player_name',
        'played',
        'result',
        'runs',
        'balls',
        'wickets',
        'overs_bowled',
        'runs_conceded',
        'stats',
    ];

    protected $casts = [
        'played' => 'boolean',
        'stats' => 'array',
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
