<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Services\MatchCompletion;
use App\Services\MatchEventRecorder;
use App\Services\SportScoreEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Put a wrongly-undone match event back — safely.
 *
 * Two sources:
 *
 *   --event=ID        an event undone after 2026-09-16 (undo hides rather than deletes, so
 *                     the row is still there and simply comes back)
 *   --from-json=PATH  an event hard-deleted before then, re-inserted from a backup copy
 *                     (e.g. match 49's event 573, see deploy-insights/event_573.json)
 *
 * It is a DRY RUN unless --apply is given. The dry run checks that the event belongs to the
 * match, that its sequence slot is free, and prints the score now and the score the log would
 * replay to with the event restored — so the operator sees exactly what will change before
 * anything does. Nothing about this command runs on its own.
 *
 *   php artisan matches:restore-event 49 --from-json=deploy-insights/event_573.json
 *   php artisan matches:restore-event 49 --from-json=deploy-insights/event_573.json --apply
 */
class RestoreMatchEvent extends Command
{
    protected $signature = 'matches:restore-event
        {match : The live_matches id}
        {--event= : Id of an undone event to restore}
        {--from-json= : Path to a JSON backup of a hard-deleted event row}
        {--apply : Actually write. Without it nothing is changed.}';

    protected $description = 'Restore a wrongly undone match event (dry run unless --apply)';

    private const COLUMNS = [
        'id', 'sport', 'sequence', 'minute', 'side', 'kind', 'player_name', 'player_id',
        'related_name', 'detail', 'note', 'recorded_by', 'created_at', 'updated_at',
    ];

    public function handle(SportScoreEngine $engine, MatchEventRecorder $recorder): int
    {
        $match = LiveMatch::query()->find((int) $this->argument('match'));
        if ($match === null) {
            $this->error('No such match.');

            return self::FAILURE;
        }
        if (strtolower((string) $match->sport) === 'cricket') {
            $this->error('Cricket is scored through match_actions; this command restores match_events only.');

            return self::FAILURE;
        }

        $eventId = $this->option('event');
        $jsonPath = $this->option('from-json');
        if (($eventId === null) === ($jsonPath === null)) {
            $this->error('Give exactly one of --event or --from-json.');

            return self::FAILURE;
        }

        [$candidate, $error] = $eventId !== null
            ? $this->undoneCandidate($match, (int) $eventId)
            : $this->jsonCandidate($match, (string) $jsonPath);
        if ($error !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        // Preview: replay the live log with and without the event, writing nothing.
        $active = MatchEvent::query()->where('live_match_id', $match->id)->inOrder()->get();
        $withEvent = $active->push($candidate)->sortBy('sequence')->values();
        $before = $engine->replay($match, MatchEvent::query()->where('live_match_id', $match->id)->inOrder()->get());
        $after = $engine->replay($match, $withEvent);

        $this->table(['', 'Value'], [
            ['Match', "#{$match->id} {$match->home} vs {$match->away} ({$match->sport})"],
            ['Event', sprintf('seq %d · %s %s · %s · %s', $candidate->sequence, $candidate->side, $candidate->kind,
                $candidate->detail ?? '-', $candidate->player_name ?? '-')],
            ['Stored score', "{$match->home_score}-{$match->away_score}"],
            ['Replayed now', "{$before['home']}-{$before['away']}"],
            ['With event restored', "{$after['home']}-{$after['away']}"],
        ]);

        if (! $this->option('apply')) {
            $this->warn('Dry run — nothing was changed. Re-run with --apply to restore.');

            return self::SUCCESS;
        }

        if ($eventId !== null) {
            $recorder->restore($match, $candidate);
        } else {
            DB::transaction(function () use ($match, $candidate, $recorder): void {
                DB::table('match_events')->insert($candidate->getAttributes());
                $recorder->resync($match->fresh() ?? $match);
            });
            \App\Events\MatchUpdated::dispatch($match->id);
        }
        app(MatchCompletion::class)->followThrough((int) $match->id);

        $match->refresh();
        Log::warning('match_event.restored_by_operator', [
            'match_id' => $match->id, 'sequence' => $candidate->sequence,
            'source' => $eventId !== null ? 'undone' : 'json', 'score' => "{$match->home_score}-{$match->away_score}",
        ]);
        $this->info("Restored. Score is now {$match->home_score}-{$match->away_score} ({$match->score_text}).");

        return self::SUCCESS;
    }

    /** @return array{0: MatchEvent|null, 1: string|null} */
    private function undoneCandidate(LiveMatch $match, int $id): array
    {
        $event = MatchEvent::query()->withoutGlobalScope(MatchEvent::ACTIVE_SCOPE)->find($id);
        if ($event === null || (int) $event->live_match_id !== (int) $match->id) {
            return [null, 'That event does not belong to this match.'];
        }
        if (! $event->isUndone()) {
            return [null, 'That event is not undone — nothing to restore.'];
        }

        return [$event, null];
    }

    /** @return array{0: MatchEvent|null, 1: string|null} */
    private function jsonCandidate(LiveMatch $match, string $path): array
    {
        $full = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
        if (! is_file($full)) {
            return [null, "No file at {$full}."];
        }
        $row = json_decode((string) file_get_contents($full), true);
        if (! is_array($row)) {
            return [null, 'The file is not a JSON object.'];
        }
        if ((int) ($row['live_match_id'] ?? 0) !== (int) $match->id) {
            return [null, 'The backup row names a different match.'];
        }
        if (! isset($row['sequence'], $row['kind'])) {
            return [null, 'The backup row needs at least sequence and kind.'];
        }
        if (strtolower((string) ($row['sport'] ?? $match->sport)) !== strtolower((string) $match->sport)) {
            return [null, 'The backup row is for a different sport.'];
        }

        $all = MatchEvent::query()->withoutGlobalScope(MatchEvent::ACTIVE_SCOPE);
        if (isset($row['id']) && (clone $all)->whereKey((int) $row['id'])->exists()) {
            return [null, "An event with id {$row['id']} already exists — it may already have been restored."];
        }
        if ((clone $all)->where('live_match_id', $match->id)->where('sequence', (int) $row['sequence'])->exists()) {
            return [null, "Sequence {$row['sequence']} is already taken in this match — restoring would reorder the log."];
        }

        $attrs = array_intersect_key($row, array_flip(self::COLUMNS));
        $attrs['live_match_id'] = $match->id;
        $event = new MatchEvent();
        $event->forceFill($attrs);

        return [$event, null];
    }
}
