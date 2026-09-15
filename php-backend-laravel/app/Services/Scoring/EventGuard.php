<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Support\SportRules;

/**
 * Refuses an event the sport's rules make impossible, BEFORE it is recorded.
 *
 * The engines already ignore some of these on replay (a rally after the match is decided adds
 * nothing), but silently swallowing a tap leaves the scorer believing a point counted. Saying
 * no, with the reason, is the honest answer — and it keeps impossible rows out of the log that
 * careers and insights are built from.
 *
 * Cheap by construction, because it runs on every scoring tap: it reads the derived state the
 * last resync already stored on the match (decided, timeouts, timeouts allowed) and answers the
 * per-player questions with one narrow query each. It never replays the log itself.
 */
final class EventGuard
{
    /**
     * @param  array<string, mixed>  $event  kind, side, player_name, detail…
     * @return string|null the reason it is refused, or null when it may be recorded
     */
    public function refusal(LiveMatch $match, array $event): ?string
    {
        $kind = (string) ($event['kind'] ?? '');
        $side = in_array($event['side'] ?? null, ['home', 'away'], true) ? $event['side'] : null;
        $sport = SportRules::normalise((string) ($match->sport ?: 'football'));
        $family = SportRules::family($sport);
        $state = is_array($match->sport_state) ? $match->sport_state : [];

        // A decided rally or tennis match takes no more points.
        if (in_array($family, [SportRules::SETS, SportRules::TENNIS], true)
            && in_array($kind, [MatchEvent::POINT, MatchEvent::TIMEOUT], true)
            && ! empty($state['decided'])) {
            return 'This match is already decided — no more points can be recorded.';
        }

        if ($kind === MatchEvent::TIMEOUT && $side !== null) {
            $allowed = $state['timeouts_allowed'] ?? null;
            $used = $state['timeouts'][$side === 'home' ? 0 : 1] ?? null;
            if ($allowed !== null && $used !== null && (int) $used >= (int) $allowed) {
                return match ($sport) {
                    'table_tennis' => 'Each side has one timeout per match, and it has been used.',
                    'volleyball' => 'Both timeouts for this set have been used.',
                    default => 'No timeouts left for this side in this period.',
                };
            }
        }

        $player = mb_strtolower(trim((string) ($event['player_name'] ?? '')));
        if ($player === '' || $side === null) {
            return null;
        }

        $byPlayer = fn () => MatchEvent::query()
            ->where('live_match_id', $match->id)
            ->where('side', $side)
            ->whereRaw('lower(trim(player_name)) = ?', [$player]);

        if ($sport === 'football' && in_array($kind, [MatchEvent::GOAL, MatchEvent::OWN_GOAL, MatchEvent::YELLOW, MatchEvent::RED, 'assist'], true)) {
            $cards = $byPlayer()->whereIn('kind', [MatchEvent::YELLOW, MatchEvent::RED])
                ->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind');
            if ((int) ($cards[MatchEvent::RED] ?? 0) > 0 || (int) ($cards[MatchEvent::YELLOW] ?? 0) >= 2) {
                return 'That player has been sent off and can take no further part.';
            }
        }

        if ($sport === 'basketball' && $kind === MatchEvent::POINT) {
            $format = is_array($state['format'] ?? null) ? $state['format'] : [];
            $foulOut = (int) ($format['foulOut'] ?? 5);
            if ($byPlayer()->where('kind', 'foul')->count() >= $foulOut) {
                return 'That player has fouled out.';
            }
        }

        return null;
    }
}
