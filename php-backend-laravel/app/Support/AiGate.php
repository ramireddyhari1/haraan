<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The one question every AI call asks before it spends money: may I call the provider now?
 *
 * Three layers, all set in /control → Platform rules:
 *   1. `ops.ai_disabled` — the emergency switch, stops every AI feature at once;
 *   2. the feature's own switch (`ai.<feature>`);
 *   3. `ai.daily_call_budget` — a platform-wide cap on calls per IST day (0 = no cap).
 *
 * A "no" is never an error: every caller already has a non-AI fallback (plain commentary, the
 * numbers without a narrative, a blank description field), and returns it.
 */
final class AiGate
{
    public const CAREER_READ = 'career_read';

    public const DELIVERY_REVIEW = 'delivery_review';

    public const MATCH_COMMENTARY = 'match_commentary';

    public const MATCH_INSIGHTS = 'match_insights';

    public const EVENT_COPY = 'event_copy';

    public const PARTNER_SUPPORT = 'partner_support';

    /** Is the feature switched on? Doesn't touch the budget — use for showing/hiding UI. */
    public static function enabled(string $feature): bool
    {
        if (PlatformRules::bool('ops.ai_disabled')) {
            return false;
        }

        $key = 'ai.'.$feature;

        return ! PlatformRules::exists($key) || PlatformRules::bool($key);
    }

    /**
     * Claim one call against today's budget. True means go ahead and call the provider.
     * Call this immediately before the HTTP request, never earlier, so a cache hit doesn't
     * spend budget.
     */
    public static function attempt(string $feature): bool
    {
        if (! self::enabled($feature)) {
            return false;
        }

        // Every call is counted (it's what /control shows as "AI calls today"), capped or not.
        $key = self::counterKey();
        Cache::add($key, 0, Carbon::now('Asia/Kolkata')->endOfDay());
        $used = (int) Cache::increment($key);

        $budget = PlatformRules::int('ai.daily_call_budget');
        if ($budget > 0 && $used > $budget) {
            if ($used === $budget + 1) {
                Log::warning('AI daily call budget reached; AI features fall back until midnight IST.', ['budget' => $budget]);
            }

            return false;
        }

        return true;
    }

    /** Calls made today (IST), for /control. */
    public static function usedToday(): int
    {
        return (int) Cache::get(self::counterKey(), 0);
    }

    private static function counterKey(): string
    {
        return 'ai_calls:'.Carbon::now('Asia/Kolkata')->toDateString();
    }
}
