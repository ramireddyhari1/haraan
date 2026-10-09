<?php

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Support\PlatformRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Closes matches that were started and then left: still "Live", nothing scored for
 * `creation.stale_live_hours`. They are marked "Abandoned" and NOT finished — completed_at
 * stays null — so a half-played game never flows into stats, careers, rankings or rewards.
 * The feed then reports them as neither live nor finished, so they leave the Live tab
 * without appearing as results.
 *
 * A match with a scorer still active (a recent event or save) is never touched.
 */
class StaleMatchCloser
{
    public const STATUS = 'Abandoned';

    public static function enabled(): bool
    {
        return PlatformRules::bool('creation.stale_live_close');
    }

    public static function idleHours(): int
    {
        return max(1, PlatformRules::int('creation.stale_live_hours'));
    }

    /**
     * Live matches idle past the cut-off: no save to the match AND no scoring event since.
     *
     * @return Collection<int, LiveMatch>
     */
    public function candidates(): Collection
    {
        $cutoff = now()->subHours(self::idleHours());

        return LiveMatch::query()
            ->whereNull('completed_at')
            ->whereRaw('lower(status) = ?', ['live'])
            ->where('updated_at', '<', $cutoff)
            ->whereNotExists(function ($q) use ($cutoff): void {
                $q->selectRaw('1')
                    ->from((new MatchEvent)->getTable())
                    ->whereColumn('live_match_id', 'live_matches.id')
                    ->where('created_at', '>=', $cutoff);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Mark every idle match abandoned. Returns the ids closed.
     *
     * @return list<int>
     */
    public function close(bool $dryRun = false): array
    {
        if (! self::enabled()) {
            return [];
        }

        $closed = [];
        foreach ($this->candidates() as $match) {
            if (! $dryRun) {
                // Conditional update: a scorer who resumes between the read and the write
                // flips status or updated_at, and this then changes nothing.
                $updated = LiveMatch::query()
                    ->whereKey($match->id)
                    ->whereNull('completed_at')
                    ->whereRaw('lower(status) = ?', ['live'])
                    ->where('updated_at', $match->updated_at)
                    ->update(['status' => self::STATUS, 'updated_at' => now()]);
                if ($updated === 0) {
                    continue;
                }
                Log::info('stale match abandoned', ['match_id' => $match->id, 'last_activity' => (string) $match->updated_at]);
            }
            $closed[] = (int) $match->id;
        }

        return $closed;
    }
}
