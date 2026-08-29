<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated real career bowling for one player (see the migration). Written only by
 * CareerBattingService, which replays the ball log; read by the profile career block.
 */
class PlayerCareerBowling extends Model
{
    protected $table = 'player_career_bowling';

    protected $fillable = [
        'player_id', 'player_name', 'innings', 'balls', 'runs', 'wickets',
        'best_wickets', 'best_runs', 'three_fers', 'five_fers', 'maidens',
    ];

    /** "12.4" — overs in cricket's own base-6 notation, never a decimal fraction. */
    public function oversText(): string
    {
        return intdiv((int) $this->balls, 6) . '.' . ((int) $this->balls % 6);
    }

    /** Runs per over. Null until they have actually bowled a ball. */
    public function economy(): ?float
    {
        return $this->balls > 0 ? round($this->runs * 6.0 / $this->balls, 2) : null;
    }

    /** Runs per wicket; null while they are still wicketless. */
    public function average(): ?float
    {
        return $this->wickets > 0 ? round($this->runs / $this->wickets, 2) : null;
    }

    /** Balls per wicket — a bowler's strike rate. */
    public function strikeRate(): ?float
    {
        return $this->wickets > 0 ? round($this->balls / $this->wickets, 1) : null;
    }

    /** "4/23", or null when they have never taken one. */
    public function bestText(): ?string
    {
        return $this->best_wickets > 0 ? $this->best_wickets . '/' . $this->best_runs : null;
    }
}
