<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stretch of a scorer being on air. The audio is in LiveKit, never here — this row is who,
 * when, how many listened and how it ended. See App\Services\MatchCommentary.
 */
class MatchCommentarySession extends Model
{
    public const ENDED_REASONS = [
        'commentator' => 'Commentator ended it',
        'silence' => 'Mic was silent too long',
        'match_finished' => 'Match finished',
        'time_limit' => 'Reached the time limit',
        'admin' => 'Taken off air by Haraan',
        'reports' => 'Taken off air by listener reports',
        'switched_off' => 'Commentary switched off in /control',
        'stale' => 'Commentator’s phone stopped responding',
        'restarted' => 'Commentator went on air again',
    ];

    protected $fillable = [
        'match_id', 'user_id', 'room', 'started_at', 'last_heartbeat_at', 'muted',
        'ended_at', 'ended_reason', 'ended_by', 'listeners_now', 'peak_listeners',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'ended_at' => 'datetime',
        'muted' => 'boolean',
        'listeners_now' => 'integer',
        'peak_listeners' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(LiveMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MatchCommentaryReport::class, 'session_id');
    }

    /** Not ended. May still be stale — {@see \App\Services\MatchCommentary::current()}. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isEnded(): bool
    {
        return $this->ended_at !== null;
    }

    public function durationSeconds(): int
    {
        $end = $this->ended_at ?? now();

        return max(0, (int) $this->started_at?->diffInSeconds($end, true));
    }
}
