<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

/**
 * Presence, not analytics: one row per viewer currently on a match's detail screen.
 *
 * The app heartbeats while the screen is open and foregrounded; the row is upserted, so a
 * match with 40 watchers holds 40 rows no matter how long they watch. Nobody has to say
 * goodbye — a viewer who backgrounds the app, loses signal or force-quits simply stops
 * beating and falls out of the window on the next count.
 */
class MatchViewer extends Model
{
    /**
     * How long a viewer keeps counting as "watching" after their last heartbeat.
     * Must be comfortably longer than the app's heartbeat interval (20s) so one dropped
     * beat on a bad connection doesn't blink a real viewer out of the count.
     */
    public const PRESENCE_WINDOW_SECONDS = 60;

    /** Rows older than this are dead weight — swept opportunistically on heartbeat. */
    private const SWEEP_AFTER_SECONDS = 21600; // 6h

    public $timestamps = false;

    protected $fillable = ['match_id', 'user_id', 'viewer_key', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(LiveMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Only viewers whose last heartbeat is still inside the presence window. */
    public function scopePresent(Builder $query): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subSeconds(self::PRESENCE_WINDOW_SECONDS));
    }

    /**
     * Record that [$viewerKey] is watching [$matchId] right now, and return how many
     * distinct viewers (this one included) are currently on the match.
     */
    public static function heartbeat(int $matchId, string $viewerKey, ?int $userId = null): int
    {
        try {
            static::query()->updateOrCreate(
                ['match_id' => $matchId, 'viewer_key' => $viewerKey],
                ['user_id' => $userId, 'last_seen_at' => now()],
            );
        } catch (QueryException) {
            // Two beats from the same viewer can race the select-then-insert and collide on
            // the unique index. The row that won is this viewer's row, so touching it is the
            // correct outcome either way — nothing here is worth failing a heartbeat over.
            static::query()
                ->where('match_id', $matchId)
                ->where('viewer_key', $viewerKey)
                ->update(['user_id' => $userId, 'last_seen_at' => now()]);
        }

        // Keep the table honest without paying for a DELETE on every beat: roughly one
        // heartbeat in fifty sweeps rows that went cold hours ago. The count below never
        // depends on this — it filters by the window regardless.
        if (random_int(1, 50) === 1) {
            static::query()
                ->where('last_seen_at', '<', now()->subSeconds(self::SWEEP_AFTER_SECONDS))
                ->delete();
        }

        return static::watchingCount($matchId);
    }

    /** How many viewers are on this match right now. */
    public static function watchingCount(int $matchId): int
    {
        return static::query()->where('match_id', $matchId)->present()->count();
    }
}
