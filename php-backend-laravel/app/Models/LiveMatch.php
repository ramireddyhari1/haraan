<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveMatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'probability' => 'array',
        'projected_score' => 'array',
        'batters' => 'array',
        'bowler' => 'array',
        'over_summary' => 'array',
        'timeline' => 'array',
        'home_squad' => 'array',
        'away_squad' => 'array',
    ];
}
