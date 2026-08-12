<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
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

            $this->resync($locked);

            return $event->refresh();
        });
        // Push "this match changed" to anyone watching — after commit, so a listener
        // that refetches always sees the just-recorded event.
        \App\Events\MatchUpdated::dispatch($match->id);
        return $event;
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

        $home = 0;
        $away = 0;

        foreach ($events as $event) {
            if ($this->countsForHome($event)) {
                $home++;
            } elseif ($this->countsForAway($event)) {
                $away++;
            }

            // Only write when it actually changed — this runs on every event.
            if ($event->home_score !== $home || $event->away_score !== $away) {
                $event->forceFill(['home_score' => $home, 'away_score' => $away])->saveQuietly();
            }
        }

        $match->forceFill([
            'home_score' => $home,
            'away_score' => $away,
            'score_text' => "{$home} - {$away}",
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
