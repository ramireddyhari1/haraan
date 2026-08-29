<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlayerCareerBatting;
use App\Models\PlayerCareerBowling;
use App\Models\PlayerCareerFielding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The read on a player's career, in words.
 *
 * Same division of labour as {@see CricketInsights}, and for the same reason: every
 * FIGURE here is replayed from the ball log by CareerBattingService and handed to the
 * model as a labelled fact. The model may only say what those figures MEAN. It is
 * forbidden to produce a number of its own.
 *
 * A model asked to "analyse this player" will happily invent an average that sounds
 * right, and one fabricated statistic sitting among twenty real ones costs the reader
 * their trust in all of them — they cannot tell which is which. On a player's own
 * profile that is worse than having no analysis at all.
 *
 * With no API key configured the profile simply carries no analysis. The career page is
 * built to be complete without it; this is the layer on top.
 */
final class PlayerCareerAnalysis
{
    private const DEFAULT_MODEL = 'gemini-3.6-flash';
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';
    private const TIMEOUT_SECONDS = 12;

    /** Nothing to say about a career that is barely a career. */
    private const MIN_INNINGS = 2;

    public function isConfigured(): bool
    {
        return $this->vertexKeyPath() !== '' || trim((string) config('services.gemini.key')) !== '';
    }

    /**
     * The lines already written for this player, if any. Reads the cache and nothing
     * else: a profile request must never wait on a language model. The first load of a
     * changed career therefore carries the previous read, or none, and the next one
     * carries the new one.
     *
     * @return array{lines: list<string>, generated_at: ?string}|null
     */
    public function cached(User $user): ?array
    {
        $pid = (string) $user->player_id;
        if ($pid === '') {
            return null;
        }
        $row = DB::table('player_career_analysis')->where('player_id', $pid)->first();
        if ($row === null) {
            return null;
        }
        $lines = json_decode((string) $row->lines, true);

        return is_array($lines) && $lines !== []
            ? ['lines' => array_values($lines), 'generated_at' => (string) $row->updated_at]
            : null;
    }

    /**
     * Write the read for this player's CURRENT figures, once the response has gone.
     *
     * The fingerprint IS the career: this regenerates exactly when the analysis has
     * become wrong, and never merely because time passed. A timer would either burn
     * quota on players who have not batted since, or leave a paragraph describing a
     * career two matches out of date.
     */
    public function refreshAfterResponse(User $user, array $facts): void
    {
        $pid = (string) $user->player_id;
        if ($pid === '' || (int) ($facts['innings'] ?? 0) < self::MIN_INNINGS || ! $this->isConfigured()) {
            return;
        }

        $print = md5(json_encode($facts, JSON_THROW_ON_ERROR));
        $row = DB::table('player_career_analysis')->where('player_id', $pid)->first();
        if ($row !== null && (string) $row->fingerprint === $print) {
            return; // Already describes exactly these figures.
        }

        // Runs after the response is flushed. It still pins a worker for the length of
        // the model call, which is why the call is short and happens at most once per
        // change to the career.
        app()->terminating(function () use ($user, $facts, $print, $row, $pid): void {
            $written = $this->write($user, $facts);
            if ($written === []) {
                return;
            }
            DB::table('player_career_analysis')->updateOrInsert(
                ['player_id' => $pid],
                [
                    'fingerprint' => $print,
                    'lines' => json_encode($written),
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );
        });
    }

    /**
     * The facts, straight off the career tables. Nothing derived here that the profile
     * does not already print — the model is being told what the reader can see.
     *
     * @return array<string,mixed>
     */
    public function facts(User $user): array
    {
        $pid = (string) $user->player_id;
        $bat = $pid === '' ? null : PlayerCareerBatting::where('player_id', $pid)->first();
        $bowl = $pid === '' ? null : PlayerCareerBowling::where('player_id', $pid)->first();
        $field = $pid === '' ? null : PlayerCareerFielding::where('player_id', $pid)->first();

        $zones = [];
        foreach (($bat?->zones ?? []) as $z) {
            $label = PlayerCareerBatting::ZONE_LABELS[(int) ($z['zone'] ?? 0)] ?? 'Unknown';
            $zones[] = [
                'region' => $label,
                'shots' => (int) ($z['shots'] ?? 0),
                'runs' => (int) ($z['runs'] ?? 0),
            ];
        }
        usort($zones, fn ($a, $b) => $b['runs'] <=> $a['runs']);

        $runs = (int) ($bat->runs ?? 0);
        $boundaryRuns = ((int) ($bat->fours ?? 0)) * 4 + ((int) ($bat->sixes ?? 0)) * 6;

        return [
            'matches' => (int) ($user->career_matches ?? 0),
            'innings' => (int) ($bat->innings ?? 0),
            'runs' => $runs,
            'balls' => (int) ($bat->balls ?? 0),
            'highScore' => (int) ($bat->high_score ?? 0),
            'battingAverage' => $bat?->average(),
            'strikeRate' => $bat?->strikeRate(),
            'notOuts' => max(0, (int) ($bat->innings ?? 0) - (int) ($bat->outs ?? 0)),
            'fours' => (int) ($bat->fours ?? 0),
            'sixes' => (int) ($bat->sixes ?? 0),
            'fifties' => (int) ($bat->fifties ?? 0),
            'hundreds' => (int) ($bat->hundreds ?? 0),
            'boundaryPercent' => $runs > 0 ? (int) round($boundaryRuns * 100 / $runs) : 0,
            'shotRegions' => array_slice($zones, 0, 4),
            'bowlingInnings' => (int) ($bowl->innings ?? 0),
            'wickets' => (int) ($bowl->wickets ?? 0),
            'overs' => $bowl?->oversText(),
            'economy' => $bowl?->economy(),
            'bowlingAverage' => $bowl?->average(),
            'bestBowling' => $bowl?->bestText(),
            'maidens' => (int) ($bowl->maidens ?? 0),
            'catches' => (int) ($field->catches ?? 0),
            'runOuts' => (int) ($field->run_outs ?? 0),
            'stumpings' => (int) ($field->stumpings ?? 0),
        ];
    }

    // ─────────────────────────── The model ───────────────────────────

    /** @return list<string> */
    private function write(User $user, array $facts): array
    {
        $model = (string) (config('services.gemini.model') ?: self::DEFAULT_MODEL);

        $body = [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt()]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $this->factsPrompt($user, $facts)]],
            ]],
            'generationConfig' => [
                'temperature' => 0.6,
                // Thinking tokens count against this budget on Gemini 3.x, so a budget
                // sized to the visible answer returns truncated mid-sentence.
                'maxOutputTokens' => 700,
                'candidateCount' => 1,
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];

        [$url, $headers] = $this->endpoint($model);
        if ($url === null) {
            return [];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->withHeaders($headers)->post($url, $body);
            if (! $response->successful()) {
                Log::warning('career analysis: HTTP ' . $response->status(), [
                    'player' => $user->player_id,
                    'body' => mb_substr((string) $response->body(), 0, 300),
                ]);

                return [];
            }

            return $this->clean((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        } catch (\Throwable $e) {
            Log::warning('career analysis failed: ' . $e->getMessage(), ['player' => $user->player_id]);

            return [];
        }
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
        You are a cricket analyst writing three short observations about ONE player, for
        that player to read on their own profile.

        You are given their REAL career figures, already computed. Say what those figures
        mean about the kind of cricketer they are.

        Absolute rules:
        - NEVER state a number that is not in the facts you were given. Do not calculate,
          estimate, round, average, or infer any new figure. To mention a number, copy it
          exactly from the facts.
        - Never invent a match, an opponent, a ground, a dismissal, a shot or a team-mate.
          You were not told them.
        - Do not join two separate facts into a claim that one caused the other.
        - Explain what the figures MEAN: a high strike rate with a low average is a player
          who scores fast and gets out, a high boundary share means the runs come in
          bursts, wickets with few overs means they strike rather than contain, runs
          concentrated in one region means a preferred scoring area.
        - Write to the player, but in the third person. Never sarcastic, never flattering,
          never a coaching instruction.
        - If a discipline has almost nothing in it, do not write about it.
        - Exactly one observation per line. No bullets, no dashes, no numbering, no
          markdown, no headings.
        - 10 to 24 words per line. Plain, specific, confident.
        - Indian grassroots cricket. This is a gully and local-league player, not a pro.
        TXT;
    }

    /** @param array<string,mixed> $facts */
    private function factsPrompt(User $user, array $facts): string
    {
        $lines = [];
        $lines[] = 'Player: ' . ($user->name ?: 'This player');
        $role = trim((string) ($user->player_role ?? ''));
        if ($role !== '') {
            $lines[] = 'Stated role: ' . $role;
        }
        $lines[] = '';
        $lines[] = 'BATTING';
        $lines[] = "matches {$facts['matches']}, innings {$facts['innings']}, not outs {$facts['notOuts']}";
        $lines[] = "runs {$facts['runs']} off {$facts['balls']} balls, highest {$facts['highScore']}";
        $lines[] = 'average ' . ($facts['battingAverage'] ?? 'not out yet')
            . ', strike rate ' . ($facts['strikeRate'] ?? 'no balls faced');
        $lines[] = "fours {$facts['fours']}, sixes {$facts['sixes']}, fifties {$facts['fifties']}, hundreds {$facts['hundreds']}";
        $lines[] = "share of runs in boundaries {$facts['boundaryPercent']} percent";

        if ($facts['shotRegions'] !== []) {
            $lines[] = '';
            $lines[] = 'SCORING REGIONS (only boundaries the scorer placed)';
            foreach ($facts['shotRegions'] as $z) {
                $lines[] = "{$z['region']}: {$z['runs']} runs from {$z['shots']} shots";
            }
        }

        if ((int) $facts['bowlingInnings'] > 0) {
            $lines[] = '';
            $lines[] = 'BOWLING';
            $lines[] = "innings {$facts['bowlingInnings']}, overs {$facts['overs']}, wickets {$facts['wickets']}";
            $lines[] = 'economy ' . ($facts['economy'] ?? 'none')
                . ', average ' . ($facts['bowlingAverage'] ?? 'no wickets')
                . ', best ' . ($facts['bestBowling'] ?? 'none')
                . ", maidens {$facts['maidens']}";
        }

        $fielding = (int) $facts['catches'] + (int) $facts['runOuts'] + (int) $facts['stumpings'];
        if ($fielding > 0) {
            $lines[] = '';
            $lines[] = 'FIELDING';
            $lines[] = "catches {$facts['catches']}, run outs {$facts['runOuts']}, stumpings {$facts['stumpings']}";
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function clean(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = trim($raw);
            // preg_replace with /u, NOT trim() with a charlist: a byte charlist can strip
            // the lead byte off a multibyte character and leave broken text behind.
            $line = preg_replace('/^\s*(?:[-*\x{2022}\x{2013}\x{2014}]|\d+[.)])\s*/u', '', $line) ?? $line;
            $line = preg_replace('/^[\"\x{201C}\x{2018}]+|[\"\x{201D}\x{2019}]+$/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '' || mb_strlen($line) > 200) {
                continue;
            }
            $out[] = $line;
            if (count($out) === 3) {
                break;
            }
        }

        return $out;
    }

    private function vertexKeyPath(): string
    {
        $path = trim((string) config('services.vertex.credentials'));
        if ($path === '') {
            return '';
        }
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }

        return is_readable($path) ? $path : '';
    }

    /** @return array{0: ?string, 1: array<string,string>} */
    private function endpoint(string $model): array
    {
        $keyPath = $this->vertexKeyPath();
        if ($keyPath !== '') {
            $project = (string) (config('services.vertex.project') ?: 'haraan');
            $location = (string) (config('services.vertex.location') ?: 'us-central1');
            $token = app(GoogleServiceAccountToken::class)->get($keyPath, self::SCOPE);
            if ($token === null) {
                return [null, []];
            }
            // "global" is its own HOST, not a region prefix — a us-central1-style URL
            // 404s for the current Gemini models.
            $host = $location === 'global'
                ? 'aiplatform.googleapis.com'
                : "{$location}-aiplatform.googleapis.com";

            return [
                "https://{$host}/v1/projects/{$project}"
                    . "/locations/{$location}/publishers/google/models/{$model}:generateContent",
                ['Authorization' => 'Bearer ' . $token],
            ];
        }

        $key = trim((string) config('services.gemini.key'));
        if ($key === '') {
            return [null, []];
        }

        return [
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
            ['x-goog-api-key' => $key],
        ];
    }
}
