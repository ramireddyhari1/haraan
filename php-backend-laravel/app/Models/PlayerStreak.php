<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A player's weekly play streak: consecutive ISO weeks with at least one finished match.
 * Weekly, not daily — amateur sport is played on weekends, and a daily streak would punish
 * exactly the people we want to keep.
 */
final class PlayerStreak extends Model
{
    public const PLAY_WEEK = 'play_week';

    protected $fillable = ['user_id', 'kind', 'current', 'best', 'last_period', 'last_match_id'];

    protected $casts = [
        'current' => 'integer',
        'best' => 'integer',
    ];

    /** The streak as it stands today: a streak whose last week is older than last week is broken. */
    public function liveCurrent(?\DateTimeInterface $now = null): int
    {
        $now = Carbon::instance($now ?? now());
        $thisWeek = $now->format('o-\WW');
        $lastWeek = $now->copy()->subWeek()->format('o-\WW');

        return in_array($this->last_period, [$thisWeek, $lastWeek], true) ? (int) $this->current : 0;
    }
}
