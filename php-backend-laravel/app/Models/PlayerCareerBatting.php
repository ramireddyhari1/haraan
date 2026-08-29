<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated real career batting for one player (see the migration). Read by the
 * match-detail "new batter" card; written only by CareerBattingService.
 */
class PlayerCareerBatting extends Model
{
    protected $table = 'player_career_batting';

    protected $casts = ['zones' => 'array'];

    /**
     * The eight regions the scorer's picker names, in ground order from straight.
     * Deliberately region names, not off/leg side: batting hand is not recorded, so a
     * side of the wicket would be a claim the data cannot support.
     */
    public const ZONE_LABELS = [
        'Straight', 'Cover', 'Point', 'Third man',
        'Fine leg', 'Square leg', 'Mid-wicket', 'Long-on',
    ];

    protected $fillable = [
        'player_id', 'player_name', 'innings', 'runs', 'balls',
        'fours', 'sixes', 'outs', 'high_score',
        'thirties', 'fifties', 'hundreds', 'zones',
    ];

    /** Batting average = runs / dismissals; null (shown as "—") until they've been out once. */
    public function average(): ?float
    {
        return $this->outs > 0 ? round($this->runs / $this->outs, 2) : null;
    }

    /** Career strike rate = 100 * runs / balls. */
    public function strikeRate(): ?float
    {
        return $this->balls > 0 ? round($this->runs * 100.0 / $this->balls, 2) : null;
    }
}
