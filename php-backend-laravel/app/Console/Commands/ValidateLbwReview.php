<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeliveryReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Runs a set of human-reviewed deliveries through the review pipeline and scores it.
 *
 * This exists because nothing is currently known about whether the readings are right.
 * The pipeline has been proven to RUN and to refuse honestly on footage that was not
 * cricket; that says nothing about whether it reads a real appeal correctly, and no
 * amount of further engineering is worth doing until somebody has measured it.
 *
 * Two rules the command enforces on itself:
 *
 *  · It never invents an expected result. A case with no human annotation is REPORTED,
 *    counted and left unscored — never quietly assumed correct.
 *  · It never converts observations into out / not out. Accuracy here means "did the
 *    model see what a person saw", not "did it get the decision right", because there is
 *    no decision to get right.
 *
 * Usage:
 *   php artisan lbw:validate
 *   php artisan lbw:validate --dataset=storage/app/lbw-validation/dataset.json --json
 */
class ValidateLbwReview extends Command
{
    protected $signature = 'lbw:validate
        {--dataset= : Path to the dataset manifest (defaults to the standard location)}
        {--limit=0 : Stop after this many cases, 0 for all}
        {--json : Write the full report as JSON beside the dataset}';

    protected $description = 'Run human-reviewed cricket clips through DeliveryReview and score the observations';

    /** Where a case's annotation lives, mapped to the factor the model reports. */
    private const SCORED = [
        'pitching' => 'pitching',
        'impact' => 'impact',
        'wickets' => 'line',
    ];

    public function handle(DeliveryReview $service): int
    {
        $manifestPath = (string) ($this->option('dataset')
            ?: storage_path('app/lbw-validation/dataset.json'));

        if (! is_readable($manifestPath)) {
            $this->error("No dataset at $manifestPath");
            $this->line('');
            $this->line('Create one with: php artisan lbw:validate:scaffold');
            $this->line('It needs REAL footage of REAL appeals, annotated by a person who');
            $this->line('watched them. Nothing else produces a meaningful number.');

            return self::FAILURE;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $cases = is_array($manifest['cases'] ?? null) ? $manifest['cases'] : [];
        if ($cases === []) {
            $this->warn('Dataset has no cases. Nothing to validate.');

            return self::FAILURE;
        }

        if (! $service->isConfigured()) {
            $this->error('Vertex is not configured; validation would measure nothing.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $cases = array_slice($cases, 0, $limit);
        }

        $clipDir = dirname($manifestPath) . DIRECTORY_SEPARATOR . 'clips';
        $results = [];

        $this->line("Running " . count($cases) . " case(s)…");
        $this->line('');

        foreach ($cases as $case) {
            $results[] = $this->runCase($service, $case, $clipDir);
        }

        $report = $this->summarise($results);
        $this->render($report, $results);

        if ($this->option('json')) {
            $out = dirname($manifestPath) . DIRECTORY_SEPARATOR . 'report-' . date('Ymd-His') . '.json';
            file_put_contents($out, json_encode(
                ['report' => $report, 'cases' => $results],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));
            $this->line('');
            $this->info("Report written to $out");
        }

        return self::SUCCESS;
    }

    private function runCase(DeliveryReview $service, array $case, string $clipDir): array
    {
        $id = (string) ($case['id'] ?? 'unnamed');
        $file = $clipDir . DIRECTORY_SEPARATOR . (string) ($case['clip'] ?? '');
        $row = [
            'id' => $id,
            'ok' => false,
            'bytes' => 0,
            'ms' => 0,
            'review' => null,
            'error' => null,
        ];

        if (! is_readable($file)) {
            $row['error'] = 'clip not found';
            $this->line("  <fg=red>✗</> $id — clip missing");

            return $row;
        }
        $row['bytes'] = (int) filesize($file);

        // The service reads from the public disk, so the clip is staged there for the run
        // and removed afterwards. Validation must exercise the REAL code path, not a
        // parallel one written for testing — a harness that calls Vertex its own way
        // measures the harness.
        $staged = 'lbw-validation/' . $id . '-' . bin2hex(random_bytes(3)) . '.'
            . (pathinfo($file, PATHINFO_EXTENSION) ?: 'mp4');
        Storage::disk('public')->put($staged, (string) file_get_contents($file));

        $startedAt = microtime(true);
        $review = $service->review($staged, (string) ($case['role'] ?? 'lbw'));
        $row['ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        Storage::disk('public')->delete($staged);

        if ($review === null) {
            $row['error'] = $service->lastFailure();
            $this->line("  <fg=red>✗</> $id — {$row['error']} ({$row['ms']}ms)");

            return $row;
        }

        $row['ok'] = true;
        $row['review'] = $review;
        $row['scored'] = $this->score($case, $review);

        $marks = [];
        foreach ($row['scored'] as $key => $s) {
            $marks[] = $key . ':' . $s['outcome'];
        }
        $this->line("  <fg=green>✓</> $id — {$row['ms']}ms — " . (implode(' ', $marks) ?: 'unscored'));

        return $row;
    }

    /**
     * Compare the model's readings against the human's, factor by factor.
     *
     * Outcomes are deliberately four, not two. "cannot_tell" against a human who COULD
     * tell is a miss of a different kind from a wrong answer — the first is the system
     * being appropriately humble and merely unhelpful, the second is it being wrong. A
     * pass rate that mixed them would hide the only failure that actually matters.
     */
    private function score(array $case, array $review): array
    {
        $expected = is_array($case['expected'] ?? null) ? $case['expected'] : [];
        $out = [];

        foreach (self::SCORED as $annotationKey => $factorKey) {
            if (! isset($expected[$annotationKey])) {
                continue;
            }
            $human = strtolower(trim((string) $expected[$annotationKey]));
            $model = (string) data_get($review, "factors.$factorKey.reading", '');
            $certain = data_get($review, "factors.$factorKey.certain") === true;

            $outcome = match (true) {
                $model === '' => 'absent',
                $model === 'cannot_tell' => 'abstained',
                $this->matches($factorKey, $human, $model) => 'hit',
                default => 'miss',
            };

            $out[$annotationKey] = [
                'human' => $human,
                'model' => $model,
                'certain' => $certain,
                'outcome' => $outcome,
            ];
        }

        return $out;
    }

    /**
     * The human annotates in cricket's words; the model answers in our enum. For the
     * wicket factor those vocabularies differ ("hitting" vs "would_hit"), so they are
     * mapped rather than compared as strings.
     */
    private function matches(string $factorKey, string $human, string $model): bool
    {
        if ($factorKey === 'line') {
            $human = match ($human) {
                'hitting', 'would_hit', 'hit' => 'would_hit',
                'missing', 'would_miss', 'miss' => 'would_miss',
                default => $human,
            };
        }

        return $human === $model;
    }

    private function summarise(array $results): array
    {
        $ok = array_values(array_filter($results, fn ($r) => $r['ok']));
        $times = array_column($ok, 'ms');
        $sizes = array_column($results, 'bytes');

        $factorTotals = [];
        $cannotTell = ['n' => 0, 'total' => 0];
        $certainty = ['certain_hit' => 0, 'certain_miss' => 0, 'unsure_hit' => 0, 'unsure_miss' => 0];

        foreach ($ok as $r) {
            foreach (($r['scored'] ?? []) as $key => $s) {
                $factorTotals[$key] ??= ['hit' => 0, 'miss' => 0, 'abstained' => 0, 'absent' => 0];
                $factorTotals[$key][$s['outcome']]++;

                if (in_array($s['outcome'], ['hit', 'miss'], true)) {
                    $bucket = ($s['certain'] ? 'certain_' : 'unsure_') . $s['outcome'];
                    $certainty[$bucket]++;
                }
            }
            // cannot_tell rate across ALL five factors, scored or not — it is the headline
            // number for whether this is worth building on.
            foreach (DeliveryReview::FACTORS as $factor) {
                $reading = data_get($r['review'], "factors.$factor.reading");
                if ($reading === null) {
                    continue;
                }
                $cannotTell['total']++;
                if ($reading === 'cannot_tell') {
                    $cannotTell['n']++;
                }
            }
        }

        $failures = [];
        foreach ($results as $r) {
            if (! $r['ok'] && $r['error'] !== null) {
                $failures[$r['error']] = ($failures[$r['error']] ?? 0) + 1;
            }
        }
        arsort($failures);

        return [
            'total' => count($results),
            'succeeded' => count($ok),
            'failed' => count($results) - count($ok),
            'factors' => $factorTotals,
            'cannotTellRate' => $cannotTell['total'] > 0
                ? round($cannotTell['n'] * 100 / $cannotTell['total'], 1)
                : null,
            'certainty' => $certainty,
            'avgMs' => $times === [] ? null : (int) round(array_sum($times) / count($times)),
            'minBytes' => $sizes === [] ? null : min(array_filter($sizes) ?: [0]),
            'maxBytes' => $sizes === [] ? null : max($sizes),
            'failureModes' => $failures,
        ];
    }

    private function render(array $r, array $results): void
    {
        $this->line('');
        $this->info('── Validation report ─────────────────────────────');
        $this->line("Clips:      {$r['total']}   succeeded {$r['succeeded']}   failed {$r['failed']}");
        $this->line('Avg time:   ' . ($r['avgMs'] === null ? 'n/a' : $r['avgMs'] . 'ms'));
        $this->line('Clip size:  ' . $this->mb($r['minBytes']) . ' – ' . $this->mb($r['maxBytes']));
        $this->line('cannot_tell rate: ' . ($r['cannotTellRate'] === null ? 'n/a' : $r['cannotTellRate'] . '%'));
        $this->line('');

        if ($r['factors'] === []) {
            $this->warn('No annotated cases — accuracy could not be measured.');
            $this->line('Add "expected" blocks with human-reviewed values to score the run.');
        } else {
            $this->line('Per factor (against human annotation):');
            foreach ($r['factors'] as $key => $f) {
                $answered = $f['hit'] + $f['miss'];
                $pct = $answered > 0 ? round($f['hit'] * 100 / $answered) . '%' : 'n/a';
                $this->line(sprintf(
                    '  %-9s hit %-3d miss %-3d abstained %-3d   accuracy when it answered: %s',
                    $key, $f['hit'], $f['miss'], $f['abstained'], $pct,
                ));
            }
            $c = $r['certainty'];
            $this->line('');
            $this->line('Certainty vs correctness:');
            $this->line("  said certain:  {$c['certain_hit']} right / {$c['certain_miss']} wrong");
            $this->line("  said unsure:   {$c['unsure_hit']} right / {$c['unsure_miss']} wrong");
            if ($c['certain_miss'] > 0) {
                $this->warn('  Confidently wrong readings exist — certainty is not trustworthy.');
            }
        }

        if ($r['failureModes'] !== []) {
            $this->line('');
            $this->line('Failure modes:');
            foreach ($r['failureModes'] as $mode => $n) {
                $this->line("  {$n}×  {$mode}");
            }
        }

        $unscored = count(array_filter($results, fn ($x) => ($x['scored'] ?? []) === [] && $x['ok']));
        if ($unscored > 0) {
            $this->line('');
            $this->warn("$unscored successful case(s) had no human annotation and were not scored.");
        }
    }

    private function mb(?int $bytes): string
    {
        return $bytes === null ? 'n/a' : round($bytes / 1048576, 2) . 'MB';
    }
}
