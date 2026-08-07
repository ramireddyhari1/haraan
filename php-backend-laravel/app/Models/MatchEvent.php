<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing that happened in a match — a goal, a card, a substitution, a badminton
 * point.
 *
 * Ordered by `sequence`, never by `minute`: two goals in the 67th minute must keep
 * the order they were actually scored in, and badminton has no clock at all.
 *
 * @property int         $sequence
 * @property string|null $side  'home' | 'away' | null for neutral events
 */
class MatchEvent extends Model
{
    /** @use HasFactory<\Database\Factories\MatchEventFactory> */
    use HasFactory;

    // Football
    public const GOAL = 'goal';
    public const OWN_GOAL = 'own_goal';
    public const ASSIST = 'assist';
    public const YELLOW = 'yellow';
    public const RED = 'red';
    public const SUB = 'sub';
    public const PERIOD = 'period';

    // Badminton
    public const POINT = 'point';

    /** Football events that change the scoreline. */
    public const SCORING = [self::GOAL, self::OWN_GOAL];

    protected $fillable = [
        'live_match_id', 'sport', 'sequence', 'minute', 'side', 'kind',
        'player_name', 'player_id', 'related_name',
        'home_score', 'away_score', 'detail', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'minute' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(LiveMatch::class, 'live_match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('sequence');
    }

    public function isScoring(): bool
    {
        return in_array($this->kind, self::SCORING, true);
    }

    /** "67'" — what a timeline row shows in its rail. Null for badminton. */
    public function minuteLabel(): ?string
    {
        return $this->minute === null ? null : $this->minute . "'";
    }

    /**
     * One line a client can render without knowing the sport's rules.
     * Kept server-side so the app, the website and any future surface agree.
     */
    public function headline(): string
    {
        $who = $this->player_name ?: 'Unknown';

        return match ($this->kind) {
            self::GOAL => $this->related_name
                ? "{$who} scored, assisted by {$this->related_name}"
                : "{$who} scored",
            self::OWN_GOAL => "{$who} scored an own goal",
            self::YELLOW => "{$who} booked",
            self::RED => "{$who} sent off",
            self::SUB => $this->related_name
                ? "{$who} on for {$this->related_name}"
                : "{$who} substituted",
            self::PERIOD => $this->note ?: 'Period',
            self::POINT => $this->detail === 'error'
                ? "Point to {$this->side} — unforced error"
                : "{$who} won the rally",
            default => $this->note ?: ucfirst($this->kind),
        };
    }
}
