<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated real career fielding for one player (see the migration). Written only by
 * CareerBattingService, which replays the ball log; read by the profile career block.
 */
class PlayerCareerFielding extends Model
{
    protected $table = 'player_career_fielding';

    protected $fillable = ['player_id', 'player_name', 'catches', 'run_outs', 'stumpings'];

    /** Every dismissal this player had a hand in, however it was made. */
    public function dismissals(): int
    {
        return (int) $this->catches + (int) $this->run_outs + (int) $this->stumpings;
    }
}
