<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * One sentence at the top of the Insights tab, written by Gemini and checked by us.
 *
 * The competition shows eight charts and leaves the reader to work out what any of it
 * meant. This is the answer to that: a single line saying what was remarkable about the
 * innings, phrased over figures that are already on the screen.
 *
 * THE VERIFICATION IS THE POINT, not the generation. A cricket app that misreports a
 * score is finished, and a model asked for a sentence about numbers will occasionally
 * produce a number that was never in its input. So every numeral in the returned line is
 * checked against the set of figures we actually sent, and a line containing anything
 * else is thrown away whole. There is no partial acceptance and no repair pass: the
 * caller already owns a rule-based sentence that is always true, so the cost of
 * rejection is a duller headline rather than a missing one.
 *
 * That asymmetry is deliberate. Being occasionally boring is survivable. Being
 * confidently wrong about a score, once, in front of the team that played the match,
 * is not.
 */
class InningsHeadline
{
    /** Long enough for a real sentence, short enough that it cannot become a paragraph. */
    private const MAX_CHARS = 110;

    public function __construct(private readonly GeminiText $gemini) {}

    /**
     * @param  array  $innings  one entry of CricketInsights::facts()['innings']
     * @return string|null null whenever the line cannot be trusted — never a guess
     */
    public function for(int $matchId, array $innings): ?string
    {
        $runs = (int) ($innings['runs'] ?? 0);
        if ($runs <= 0 || ! $this->gemini->isConfigured()) {
            return null;
        }

        $allowed = $this->allowedNumbers($innings);

        // Keyed on the score so a live innings gets a fresh line as it moves, and a
        // finished one is written once and then served from cache for a day.
        $key = "innings-headline:$matchId:" . ($innings['battingTeam'] ?? 1)
            . ":$runs:" . ($innings['wickets'] ?? 0) . ':' . ($innings['overs'] ?? '');

        return Cache::remember($key, now()->addDay(), function () use ($innings, $allowed): ?string {
            $text = $this->gemini->generate(
                $this->systemPrompt(),
                $this->factsPrompt($innings),
                maxTokens: 120,
                context: 'innings-headline',
            );

            return $text === null ? null : $this->verify($text, $allowed);
        });
    }

    /**
     * Every number the model is allowed to say back to us.
     *
     * Built from the same array the prompt is built from, so the two can never drift:
     * anything absent here was never shown to the model, and a sentence containing it is
     * therefore invented no matter how plausible it reads.
     *
     * @return array<int|string, true>
     */
    private function allowedNumbers(array $innings): array
    {
        $allowed = [];
        $add = static function ($value) use (&$allowed): void {
            if (is_numeric($value)) {
                // Indexed as strings so "23.63" and "10.4" survive intact — casting an
                // over count to int would let the model say "10" for a 10.4-over innings
                // and pass, which is a different fact.
                $allowed[(string) (0 + $value)] = true;
                $allowed[(string) $value] = true;
            }
        };

        foreach (['runs', 'wickets', 'overs', 'runRate', 'fours', 'sixes',
                  'boundaryPercent', 'dotPercent'] as $field) {
            $add($innings[$field] ?? null);
        }
        $add(data_get($innings, 'bestOver.over'));
        $add(data_get($innings, 'bestOver.runs'));
        $add(data_get($innings, 'bestPartnership.runs'));
        $add(data_get($innings, 'bestPartnership.balls'));
        $add(data_get($innings, 'bestPartnership.wicket'));
        foreach ((array) ($innings['phases'] ?? []) as $phase) {
            $add($phase['runs'] ?? null);
            $add($phase['runRate'] ?? null);
            $add($phase['overs'] ?? null);
        }
        foreach ((array) ($innings['breakdown'] ?? []) as $count) {
            $add($count);
        }

        return $allowed;
    }

    /**
     * Accept the line only if every number in it was one of ours.
     *
     * @param  array<int|string, true>  $allowed
     */
    private function verify(string $text, array $allowed): ?string
    {
        $line = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        // A model that ignored "one sentence" and wrote three gets truncated to the
        // first, rather than rejected — the first sentence is usually the headline.
        if (str_contains($line, '. ')) {
            $line = substr($line, 0, strpos($line, '. ') + 1);
        }
        $line = trim($line, " \t\n\r\0\x0B\"'*");

        if ($line === '' || mb_strlen($line) > self::MAX_CHARS) {
            return null;
        }

        preg_match_all('/\d+(?:\.\d+)?/', $line, $matches);
        foreach ($matches[0] as $number) {
            if (! isset($allowed[$number]) && ! isset($allowed[(string) (0 + $number)])) {
                \Illuminate\Support\Facades\Log::info('innings-headline rejected', [
                    'number' => $number,
                    'line' => $line,
                ]);

                return null;
            }
        }

        return $line;
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
        You write one-line headlines about a single cricket innings for a scoring app.

        Rules, all of them absolute:
        - Exactly ONE sentence, under 110 characters.
        - Use ONLY numbers that appear in the facts given to you. Never calculate a new
          number, never round one, never estimate. If you want to say something you have
          no number for, say something else instead.
        - Say what was REMARKABLE about this innings, the thing a player would tell
          someone about afterwards. Not a summary of every figure.
        - Write like a cricket writer, not a report generator. No "impressive display of
          batting prowess", no "showcased", no adjectives doing a number's job.
        - No advice, no coaching, no questions, no emoji, no quotation marks.

        Good: Kadapa's 252 came almost entirely off the bat's meat - 28 sixes in 10.4 overs.
        Good: One unbroken stand of 224 made nearly the whole total.
        Bad: An impressive batting performance showcasing excellent boundary hitting.
        TXT;
    }

    private function factsPrompt(array $innings): string
    {
        $lines = [
            'Team: ' . ($innings['battingName'] ?? 'Unknown'),
            'Score: ' . ($innings['runs'] ?? 0) . '/' . ($innings['wickets'] ?? 0)
                . ' in ' . ($innings['overs'] ?? '0') . ' overs',
            'Run rate: ' . ($innings['runRate'] ?? 0),
            'Fours: ' . ($innings['fours'] ?? 0) . ', Sixes: ' . ($innings['sixes'] ?? 0),
            'Runs in boundaries: ' . ($innings['boundaryPercent'] ?? 0) . '%',
            'Dot balls: ' . ($innings['dotPercent'] ?? 0) . '% of the innings',
        ];

        foreach ((array) ($innings['phases'] ?? []) as $phase) {
            $lines[] = "Phase {$phase['label']}: {$phase['runs']} runs in {$phase['overs']} overs at {$phase['runRate']}";
        }
        if ($over = ($innings['bestOver'] ?? null)) {
            $lines[] = "Biggest over: over {$over['over']} went for {$over['runs']}";
        }
        if ($stand = ($innings['bestPartnership'] ?? null)) {
            $lines[] = "Best stand: {$stand['runs']} off {$stand['balls']} for the {$stand['wicket']} wicket"
                . ' by ' . ($stand['batters'] ?? 'two batters')
                . (($stand['unbroken'] ?? false) ? ', unbroken' : '');
        }

        return implode("\n", $lines);
    }
}
