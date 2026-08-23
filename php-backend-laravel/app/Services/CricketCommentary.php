<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Writes the broadcast line for one delivery.
 *
 * The board's commentary read like a scorer's shorthand — "ojfj to 11, SIX" — because
 * that is exactly what it was: bowler, batter, outcome, joined with commas. This turns
 * each ball into the sentence a commentator would actually say, and stores it on the
 * ball so the words never change under the reader.
 *
 * Three rules shape everything here:
 *
 *  1. **Scoring never waits for it.** The call is made after the response is already on
 *     its way to the scorer (see MatchesController::scoreAction). A model that is slow,
 *     rate-limited or down must never be able to make tapping FOUR feel slow.
 *  2. **It can only ever add.** Every failure path — no key, timeout, refusal, junk
 *     response — leaves `commentary` null, and buildCommentary falls back to the template
 *     that has always been there. The feature cannot subtract from a working board.
 *  3. **It may not invent.** The prompt carries the facts of the delivery and forbids
 *     inventing anything not in them. A commentary line that makes up a scoreboard is
 *     worse than a terse one, because a reader cannot tell which parts are true.
 */
final class CricketCommentary
{
    /** Flash is the right trade here: a one-sentence job, per ball, that must be cheap. */
    private const DEFAULT_MODEL = 'gemini-3.6-flash';

    /** Vertex bills to Cloud, which is where the project's credits live. */
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /**
     * Short on purpose. This runs after the response, but the PHP worker is still held
     * for its duration, so a hung call would pin a process rather than fail quietly.
     */
    private const TIMEOUT_SECONDS = 8;

    /**
     * Two ways in, checked in this order:
     *
     *  1. **Vertex AI** — a service account JSON, billed to Cloud. This is the configured
     *     path: the project's credits live in Cloud billing, and Vertex does not use
     *     prompts to train Google's models.
     *  2. **Gemini API key** — the AI Studio endpoint. Kept as a fallback so the feature
     *     still works on a machine that has a key but no service account file.
     *
     * Neither present = the board keeps its template lines, which is a valid state.
     */
    public function isConfigured(): bool
    {
        return $this->vertexKeyPath() !== '' || trim((string) config('services.gemini.key')) !== '';
    }

    /** The service account file, if one is configured AND actually readable. */
    private function vertexKeyPath(): string
    {
        $path = trim((string) config('services.vertex.credentials'));
        if ($path === '') {
            return '';
        }
        // Relative paths are resolved against the app root so .env can stay portable
        // between this machine and the server, where the tree lives elsewhere.
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }

        return is_readable($path) ? $path : '';
    }

    /**
     * Write the line for one action, and store it. Returns the line, or null when the
     * ball keeps its template line.
     *
     * @param array<string,mixed> $facts see MatchesController::commentaryFacts
     */
    public function writeFor(int $actionId, LiveMatch $match, array $facts): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $line = $this->generate($match, $facts);
        if ($line === null) {
            return null;
        }

        // Written straight onto the ball. No model call happens on the read path.
        DB::table('match_actions')->where('id', $actionId)->update(['commentary' => $line]);

        return $line;
    }

    /** One sentence for one delivery, or null if anything at all goes wrong. */
    private function generate(LiveMatch $match, array $facts): ?string
    {
        $model = (string) (config('services.gemini.model') ?: self::DEFAULT_MODEL);

        // Identical request body either way — Vertex and the AI Studio endpoint both speak
        // generateContent. Only the URL and the auth header differ, which is why both are
        // supported for the price of one branch.
        $body = [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt()]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $this->deliveryPrompt($match, $facts)]],
            ]],
            'generationConfig' => [
                // Enough warmth to vary the phrasing between balls, not enough to start
                // editorialising.
                'temperature' => 0.9,
                // Generous for a one-line job, because on Gemini 3.x THINKING TOKENS COUNT
                // AGAINST THIS BUDGET. At 90 the model spent the whole allowance reasoning
                // and returned fragments — "raj", "Bowled him,". The visible sentence is
                // ~30 tokens; the rest is headroom so it can never be cut off mid-word.
                'maxOutputTokens' => 400,
                'candidateCount' => 1,
                // No thinking. Expanding a scorer's shorthand into one sentence does not
                // need deliberation, and disabling it makes the call cheaper and faster —
                // which matters when it fires once per delivery.
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];

        [$url, $headers] = $this->endpoint($model);
        if ($url === null) {
            return null;
        }

        try {
            // Two attempts, because 429 is routine here rather than exceptional: Vertex
            // rate-limits per minute and a busy over fires several balls in a row. One
            // short wait clears most of them. Only transient statuses are retried — a 403
            // or a 404 will say the same thing however many times it is asked.
            $response = null;
            foreach ([0, 1] as $attempt) {
                if ($attempt > 0) {
                    usleep(1_200_000);
                }
                $response = Http::timeout(self::TIMEOUT_SECONDS)->withHeaders($headers)->post($url, $body);
                if ($response->successful() || ! in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                    break;
                }
            }

            if ($response === null || ! $response->successful()) {
                Log::warning('commentary: HTTP ' . ($response?->status() ?? 0), [
                    'match' => $match->id,
                    'body' => mb_substr((string) ($response?->body() ?? ''), 0, 300),
                ]);

                return null;
            }

            return $this->clean((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        } catch (\Throwable $e) {
            // Never rethrown: this runs after the response, so an exception here would land
            // in the log as a mystery 500 on a request the client already received.
            Log::warning('commentary failed: ' . $e->getMessage(), ['match' => $match->id]);

            return null;
        }
    }

    /**
     * Where to send it, and how to prove who we are.
     *
     * @return array{0: ?string, 1: array<string,string>} [url, headers]; url null = give up
     */
    private function endpoint(string $model): array
    {
        $keyPath = $this->vertexKeyPath();
        if ($keyPath !== '') {
            $project = (string) (config('services.vertex.project') ?: 'haraan');
            $location = (string) (config('services.vertex.location') ?: 'us-central1');
            $token = app(GoogleServiceAccountToken::class)->get($keyPath, self::SCOPE);

            if ($token === null) {
                Log::warning('commentary: could not mint a Vertex access token');

                return [null, []];
            }

            // "global" is its own host, not a region prefix — the current Gemini models are
            // served there and a us-central1-style URL 404s for them. Probed both ways
            // against the live API: global/gemini-3.6-flash answers, us-central1 does not.
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

    private function systemPrompt(): string
    {
        return <<<'TXT'
        You are a cricket commentator writing the ball-by-ball feed for a live scoreboard.

        Write ONE sentence for the delivery you are given. Rules:
        - You are given the facts as labelled fields. Use the roles exactly as labelled:
          the Bowler bowled, the Batter faced. Never swap them.
        - If a name is "not recorded", DO NOT invent one, and do not substitute the team
          name or any word from the outcome. Write the sentence without naming that person
          ("the bowler", "the batter", or simply describe the ball).
        - Use ONLY these facts. Never invent a shot, a field placing, a score, a bowler's
          pace, crowd reaction, or anything else you were not told. If you were not told
          how a boundary was struck, do not say how it was struck.
        - Name the bowler and the batter as given. Do not change or shorten their names,
          however odd they look — they are real names entered by the scorer.
        - 8 to 22 words. One sentence. No line breaks.
        - Plain text only: no markdown, no quotes around the sentence, no emoji, no
          scoreline, no over number — the feed already shows those.
        - Match the moment: a wicket is dramatic, a dot ball is tight, a six is loud, a
          leg bye is workmanlike. Do not treat every ball as a highlight.
        - Indian domestic/grassroots cricket. Warm, professional broadcast register.
          Never sarcastic about a player.
        TXT;
    }

    /**
     * The facts of the delivery, LABELLED — never a single collapsed string.
     *
     * The first version handed over the board's shorthand ("rajesh to sasi, SIX") and let
     * the model work out who was who. That reads fine until a name is missing, because the
     * shorthand degrades: an unknown batter gives "kishore to no run", and the model
     * confidently wrote about a batter called "no run"; an unknown bowler gives
     * "laddu, FOUR", and the batter got described as the bowler. Roles are stated here
     * instead, and a role we do not know is simply left out rather than guessed at.
     */
    private function deliveryPrompt(LiveMatch $match, array $facts): string
    {
        $lines = [];

        $bowler = trim((string) ($facts['bowler'] ?? ''));
        $striker = trim((string) ($facts['striker'] ?? ''));
        $outcome = trim((string) ($facts['outcome'] ?? ''));

        $lines[] = $bowler !== '' ? "Bowler: {$bowler}" : 'Bowler: not recorded';
        $lines[] = $striker !== '' ? "Batter on strike: {$striker}" : 'Batter on strike: not recorded';
        $lines[] = 'What happened: ' . ($outcome !== '' ? $outcome : (string) ($facts['line'] ?? ''));

        if (($facts['over'] ?? '') !== '') {
            $lines[] = 'Over: ' . $facts['over'];
        }
        if (($facts['battingName'] ?? '') !== '') {
            $lines[] = 'Batting side: ' . $facts['battingName'];
        }
        if (! empty($facts['wicket'])) {
            $lines[] = 'This delivery took a WICKET.';
        }
        if (($facts['format'] ?? '') !== '') {
            $lines[] = 'Format: ' . $facts['format'];
        }

        return "Write the commentary line for this delivery.

" . implode("
", $lines);
    }

    /**
     * Models wrap things in quotes, add a leading "Commentary:", or return two lines
     * however firmly you ask for one. Take the first line, strip the wrapping, and
     * refuse anything that came back empty or absurdly long.
     */
    private function clean(string $text): ?string
    {
        $line = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $line = preg_replace('/^(commentary|line)\s*:\s*/iu', '', $line) ?? $line;
        $line = trim($line, " \t\n\r\0\x0B\"'“”‘’");

        if ($line === '' || mb_strlen($line) > 300) {
            return null;
        }

        return $line;
    }
}
