<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
use App\Support\SportRules;
use Illuminate\Support\Facades\DB;

/**
 * Records football and badminton events, and keeps the match scoreline derived
 * from them.
 *
 * The rule that makes this trustworthy: **the score is never set by the client.**
 * A scorer posts "a goal happened"; the server recomputes `home_score`/`away_score`
 * by counting scoring events. So a dropped request, a double-tap, or a phone that
 * went offline mid-match can't leave a scoreboard that disagrees with its own
 * timeline — which is the failure that makes people stop trusting a live score.
 *
 * Cricket does not come through here; it keeps its own per-ball pipeline.
 */
class MatchEventRecorder
{
    /**
     * Append an event and re-derive the score.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(LiveMatch $match, array $attributes, ?User $by = null): MatchEvent
    {
        $event = DB::transaction(function () use ($match, $attributes, $by): MatchEvent {
            // Lock the match row so two scorers on two phones can't claim the same
            // sequence number.
            $locked = LiveMatch::query()->lockForUpdate()->find($match->id) ?? $match;

            $next = (int) MatchEvent::query()
                ->where('live_match_id', $locked->id)
                ->max('sequence') + 1;

            $event = MatchEvent::query()->create(array_merge([
                'live_match_id' => $locked->id,
                'sport' => $locked->sport ?: 'football',
                'sequence' => $next,
                'recorded_by' => $by?->id,
            ], $attributes));

            // A match with a recorded point IS live. Only cricket ever said so: its toss
            // flow sets the status, and every other sport reached the scorer without one —
            // so football and the five rally/points sports sat at "Scheduled" in the feed
            // while their score climbed. Derived, not guessed, and never resurrects a match
            // somebody has already finished.
            $this->promoteToLive($locked);

            $this->resync($locked);

            return $event->refresh();
        });
        // Push "this match changed" to anyone watching — after commit, so a listener
        // that refetches always sees the just-recorded event.
        \App\Events\MatchUpdated::dispatch($match->id);
        return $event;
    }

    /**
     * Move a match to Live the moment it is actually being played.
     *
     * Anything already Live or Completed is left alone: the first keeps its state, and the
     * second must not be dragged back into the live feed by a late correction to the log.
     */
    private function promoteToLive(LiveMatch $match): void
    {
        $status = strtolower(trim((string) $match->status));

        if ($status === 'live' || $status === 'completed') {
            return;
        }

        $match->forceFill(['status' => 'Live'])->save();
    }

    /** Remove an event (a mis-tap) and re-derive the score. */
    public function undo(LiveMatch $match, MatchEvent $event): void
    {
        DB::transaction(function () use ($match, $event): void {
            $event->delete();
            $this->resync($match->fresh() ?? $match);
        });
        \App\Events\MatchUpdated::dispatch($match->id);
    }

    /**
     * Drop the most recent event — what an "Undo" button on a scorer calls.
     *
     * With a `$side`, drops that side's most recent SCORING event instead. That is
     * what the "−" next to a team's tally means: a scorer correcting their own
     * mis-tap on one team shouldn't reach across and delete the other team's goal
     * just because it happened to be typed last.
     */
    public function undoLast(LiveMatch $match, ?string $side = null): ?MatchEvent
    {
        $query = MatchEvent::query()
            ->where('live_match_id', $match->id)
            ->orderByDesc('sequence');

        if ($side !== null) {
            // A side's score is raised by its own goals and by the opposition's own
            // goals, so "undo a point for this side" has to consider both.
            $query->where(fn ($q) => $q
                ->where(fn ($g) => $g->where('kind', MatchEvent::GOAL)->where('side', $side))
                ->orWhere(fn ($g) => $g->where('kind', MatchEvent::OWN_GOAL)
                    ->where('side', $side === 'home' ? 'away' : 'home'))
                ->orWhere(fn ($g) => $g->where('kind', MatchEvent::POINT)->where('side', $side)));
        }

        $last = $query->first();

        if ($last === null) {
            return null;
        }

        $this->undo($match, $last);

        return $last;
    }

    /**
     * Recompute the scoreline from the events, and stamp each event with the score
     * as it stood after it — so a timeline row renders without replaying the feed.
     */
    public function resync(LiveMatch $match): LiveMatch
    {
        $events = MatchEvent::query()
            ->where('live_match_id', $match->id)
            ->inOrder()
            ->get();

        // Every sport that is not cricket scores through one engine, which replays the
        // whole event log rather than patching a running total. Football's tally, a
        // basketball three-pointer, a volleyball set that resets the rally count and a
        // tennis game climbing 15/30/40 are all the same operation to the caller.
        $board = app(SportScoreEngine::class)->compute($match, $events);

        // The engine owns the derived half of sport_state (sets, periods, serve); the
        // manual half — the creator's format, a football clock a scorer is nudging — is
        // merged UNDER it so a recompute can never wipe what only a human can know.
        $state = is_array($match->sport_state) ? $match->sport_state : [];
        $state = array_merge($state, $board['state']);

        $match->forceFill([
            'home_score' => $board['home'],
            'away_score' => $board['away'],
            'score_text' => $board['scoreText'],
            'sport_state' => $state,
        ])->save();

        return $match;
    }

    /**
     * An own goal credits the OTHER side. Getting this backwards is the classic
     * scoreboard bug, so it lives in one named place rather than inline.
     */
    private function countsForHome(MatchEvent $event): bool
    {
        if ($event->kind === MatchEvent::GOAL) {
            return $event->side === 'home';
        }

        if ($event->kind === MatchEvent::OWN_GOAL) {
            return $event->side === 'away';
        }

        // Badminton: a point is a point for whoever it is credited to.
        return $event->kind === MatchEvent::POINT && $event->side === 'home';
    }

    private function countsForAway(MatchEvent $event): bool
    {
        if ($event->kind === MatchEvent::GOAL) {
            return $event->side === 'away';
        }

        if ($event->kind === MatchEvent::OWN_GOAL) {
            return $event->side === 'home';
        }

        return $event->kind === MatchEvent::POINT && $event->side === 'away';
    }

    /**
     * The football detail payload: the timeline, plus the two summary rows the
     * hero shows (scorers under each side).
     *
     * @return array<string, mixed>
     */
    public function footballPayload(LiveMatch $match): array
    {
        $events = MatchEvent::query()
            ->where('live_match_id', $match->id)
            ->inOrder()
            ->get();

        $state = is_array($match->sport_state) ? $match->sport_state : [];

        // The timeline is the match's key moments only — goals, cards, subs. Stat-count
        // events (shots, corners, fouls…) are aggregated into `stats` below, never
        // listed one-by-one, or a busy match's timeline would drown in "Shot" rows.
        $timelineKinds = [
            MatchEvent::GOAL, MatchEvent::OWN_GOAL,
            MatchEvent::YELLOW, MatchEvent::RED, MatchEvent::SUB,
        ];

        return [
            'half' => $state['half'] ?? null,
            'clock_min' => $state['clock_min'] ?? null,
            'added' => $state['added'] ?? null,
            'home_scorers' => $this->scorerLine($events, 'home'),
            'away_scorers' => $this->scorerLine($events, 'away'),
            'stats' => $this->statsBlock($events),
            'timeline' => $events
                ->filter(fn (MatchEvent $e): bool => in_array($e->kind, $timelineKinds, true))
                ->map(fn (MatchEvent $e): array => [
                    'sequence' => $e->sequence,
                    'minute' => $e->minute,
                    'minute_label' => $e->minuteLabel(),
                    'side' => $e->side,
                    'kind' => $e->kind,
                    'player' => $e->player_name,
                    'related' => $e->related_name,
                    'home_score' => $e->home_score,
                    'away_score' => $e->away_score,
                    'headline' => $e->headline(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The board a rally / points sport's detail screen renders.
     *
     * Football keeps its own richer payload (scorers, cards, subs, stats). Everything
     * else shares this one, because a volleyball set list and a basketball quarter line
     * are the same shape wearing different labels — and one payload means one place to
     * fix when a label is wrong.
     *
     * @return array<string, mixed>|null  null for cricket and football, which have theirs
     */
    public function boardPayload(LiveMatch $match): ?array
    {
        $sport = SportRules::normalise((string) ($match->sport ?: 'cricket'));
        $family = SportRules::family($sport);

        if ($sport === 'cricket' || $family === SportRules::TALLY) {
            return null;
        }

        $events = MatchEvent::query()
            ->where('live_match_id', $match->id)
            ->inOrder()
            ->get();

        $state = is_array($match->sport_state) ? $match->sport_state : [];

        // Newest first: a live board is read from the top, and the most recent point is
        // the one anybody is looking for.
        $feed = $events
            ->filter(fn (MatchEvent $e): bool => in_array($e->kind, [MatchEvent::POINT, MatchEvent::PERIOD], true))
            ->sortByDesc('sequence')
            ->take(40)
            ->map(fn (MatchEvent $e): array => [
                'sequence' => $e->sequence,
                'side' => $e->side,
                'kind' => $e->kind,
                'detail' => $e->detail,
                'player' => $e->player_name,
                'home_score' => $e->home_score,
                'away_score' => $e->away_score,
                'value' => $e->kind === MatchEvent::POINT
                    ? SportRules::pointValue($sport, $e->detail)
                    : 0,
            ])
            ->values()
            ->all();

        return [
            'sport' => $sport,
            'family' => $family,
            'sets' => $state['sets'] ?? [],
            'current' => $state['current'] ?? null,
            'games' => $state['games'] ?? null,
            'points' => $state['points'] ?? null,
            'target' => $state['target'] ?? null,
            'best_of' => $state['best_of'] ?? SportRules::defaultBestOf($sport),
            'set_noun' => $state['set_noun'] ?? SportRules::setNoun($sport),
            'serving' => $state['serving'] ?? null,
            'period' => $state['period'] ?? null,
            'period_label' => $state['period_label'] ?? null,
            'periods' => $state['periods'] ?? [],
            'scorers' => $this->pointScorers($events, $sport),
            'feed' => $feed,
        ];
    }

    /**
     * Who has scored, and how much — the "top scorers" line a points sport lives on.
     * Only named players count: a scorer tapping fast without picking a name still moves
     * the score, and inventing an "Unknown" leaderboard entry for those would be worse
     * than showing a shorter, true list.
     *
     * @param  \Illuminate\Support\Collection<int, MatchEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function pointScorers($events, string $sport): array
    {
        return $events
            ->filter(fn (MatchEvent $e): bool => $e->kind === MatchEvent::POINT
                && in_array($e->side, ['home', 'away'], true)
                && trim((string) $e->player_name) !== '')
            ->groupBy(fn (MatchEvent $e): string => $e->side.'|'.$e->player_name)
            ->map(function ($rows) use ($sport): array {
                $first = $rows->first();

                return [
                    'side' => $first->side,
                    'name' => (string) $first->player_name,
                    'points' => $rows->sum(fn (MatchEvent $e): int => SportRules::pointValue($sport, $e->detail)),
                ];
            })
            ->sortByDesc('points')
            ->values()
            ->all();
    }

    /**
     * Head-to-head match stats, grouped for the detail screen. Every number is a real
     * tally of events the scorer recorded — nothing is estimated or modelled (no
     * possession %, no xG), so a stat is only ever as true as what was tapped.
     *
     * `has_any` is driven by the counting stats (shots/corners/fouls/…), NOT by cards:
     * a match with only goals and cards hasn't had its stats tracked, so the screen
     * shows an empty state rather than a wall of honest-but-meaningless zeroes.
     *
     * @param  \Illuminate\Support\Collection<int, MatchEvent>  $events
     * @return array<string, mixed>
     */
    private function statsBlock($events): array
    {
        $count = static fn (string $kind, string $side): int => $events
            ->where('kind', $kind)->where('side', $side)->count();

        // [label, event-kind] per metric, grouped exactly as the UI renders them.
        $groups = [
            'Attacking' => [
                ['Total shots', 'shot'],
                ['Shots on target', 'shot_on'],
                ['Shots off target', 'shot_off'],
                ['Blocked shots', 'shot_blocked'],
                ['Corners', 'corner'],
            ],
            'Discipline' => [
                ['Fouls', 'foul'],
                ['Offsides', 'offside'],
                ['Yellow cards', MatchEvent::YELLOW],
                ['Red cards', MatchEvent::RED],
            ],
            'Defence' => [
                ['Saves', 'save'],
                ['Free kicks', 'free_kick'],
            ],
        ];

        // Only the counting stats gate the section — cards alone don't make it "tracked".
        $countingKinds = ['shot', 'shot_on', 'shot_off', 'shot_blocked', 'corner', 'foul', 'offside', 'save', 'free_kick'];
        $hasAny = false;
        foreach ($countingKinds as $k) {
            if ($count($k, 'home') > 0 || $count($k, 'away') > 0) {
                $hasAny = true;
                break;
            }
        }

        $out = [];
        foreach ($groups as $title => $rows) {
            $out[] = [
                'title' => $title,
                'rows' => array_map(static fn (array $r): array => [
                    'label' => $r[0],
                    'home' => $count($r[1], 'home'),
                    'away' => $count($r[1], 'away'),
                ], $rows),
            ];
        }

        return ['has_any' => $hasAny, 'groups' => $out];
    }

    /**
     * "Rahul 12', 45'  ·  Imran 67'" — a scorer listed once with all their minutes,
     * the way a real scoreboard reads, not one row per goal.
     *
     * @param  \Illuminate\Support\Collection<int, MatchEvent>  $events
     * @return array<int, array{name: string, minutes: array<int, string>}>
     */
    private function scorerLine($events, string $side): array
    {
        $goals = $events->filter(
            fn (MatchEvent $e): bool => $e->kind === MatchEvent::GOAL && $e->side === $side,
        );

        return $goals
            ->groupBy(fn (MatchEvent $e): string => $e->player_name ?: 'Unknown')
            ->map(fn ($rows, $name): array => [
                'name' => (string) $name,
                'minutes' => $rows->map(fn (MatchEvent $e): string => $e->minuteLabel() ?? '')
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
