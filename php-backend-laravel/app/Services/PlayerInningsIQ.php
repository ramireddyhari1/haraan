<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cricket IQ — one player's innings, explained.
 *
 * The Insights tab already said what the MATCH did. This says what the PLAYER did inside
 * it, which is a different product: a scorecard tells you a person made 99 off 22, and
 * this says the innings turned in the sixth over, that the runs came almost entirely in
 * boundaries, and that the strike stopped rotating in the middle.
 *
 * Three rules hold the whole thing up:
 *
 *  1. Every figure is replayed from `match_actions` — the same ball log the scorecard is
 *     built from. There is no second source of truth to drift from.
 *  2. Every FINDING is derived by rule from figures that are also printed on screen, so a
 *     reader can check the verdict against the evidence directly underneath it. Nothing
 *     is asserted that the numbers do not already say.
 *  3. The written line at the top is a model's phrasing of those same facts, it is
 *     labelled, and its absence costs nothing. Facts first, prose last, always optional.
 *
 * What this deliberately does NOT do: compare a player against other players, against a
 * league average, or against a previous season. We hold one match's log here. "18% better
 * than your average" needs a baseline this service cannot see, and a confident number with
 * nothing behind it poisons every honest one beside it.
 */
class PlayerInningsIQ
{
    /**
     * Balls a batter is given to play themselves in before "after settling" starts.
     *
     * Ten is a judgement, not a discovery — it is roughly two overs of strike, which is
     * how long club cricketers actually talk about taking. It is stated on screen next to
     * the split so nobody has to guess what the number means.
     */
    private const SETTLE_BALLS = 10;

    /** Below this, an innings is too short to say anything about beyond what happened. */
    private const MIN_BALLS_FOR_FINDINGS = 6;

    private const NARRATIVE_TTL_MINUTES = 30;

    public function __construct(private readonly GeminiText $gemini)
    {
    }

    /**
     * Everything the Cricket IQ block needs for one match, optionally focused on a player.
     *
     * @return array<string,mixed>|null null when nobody has faced a ball yet
     */
    public function forMatch(LiveMatch $match, ?string $batter = null): ?array
    {
        $byBatter = $this->replay($match);
        if ($byBatter === []) {
            return null;
        }

        // Who batted, best first. This is the picker's order as well as the default: the
        // top scorer is who a reader opening the block almost always means.
        uasort($byBatter, static fn (array $a, array $b): int => $b['runs'] <=> $a['runs']);
        $names = array_keys($byBatter);

        $chosen = $batter !== null && isset($byBatter[$batter]) ? $batter : $names[0];
        $b = $byBatter[$chosen];

        $facts = $this->summarise($chosen, $b);
        $facts['batters'] = array_values($names);
        $facts['narrative'] = $this->cachedNarrative($match, $facts);

        return $facts;
    }

    /**
     * Generate (and cache) the written line, after the response has gone out.
     *
     * Same discipline as the career read: a synchronous model call inside a request that a
     * phone is waiting on turns a 200ms endpoint into a 20-second one, and kills the dev
     * server outright when it exceeds max_execution_time.
     */
    public function refreshAfterResponse(LiveMatch $match, array $facts): void
    {
        if (! $this->gemini->isConfigured()) {
            return;
        }
        $key = $this->narrativeKey($match, $facts);
        if (Cache::has($key)) {
            return;
        }

        app()->terminating(function () use ($key, $facts): void {
            $text = $this->gemini->generate(
                $this->systemPrompt(),
                $this->factsPrompt($facts),
                420,
                'innings iq',
            );
            if ($text !== null) {
                Cache::put($key, $this->clean($text), now()->addMinutes(self::NARRATIVE_TTL_MINUTES));
            }
        });
    }

    // ─────────────────────────── The ball log ───────────────────────────

    /**
     * Attribute every legal ball to whoever was on strike for it.
     *
     * This mirrors the striker bookkeeping in {@see CricketInsights} rather than sharing
     * it, because that service accumulates the INNINGS and this one accumulates a PERSON —
     * the same loop, two different questions.
     *
     * @return array<string,array<string,mixed>>
     */
    private function replay(LiveMatch $match): array
    {
        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id', 'asc')
            ->get();

        $nameById = [];
        foreach ([$match->home_squad ?: [], $match->away_squad ?: []] as $squad) {
            foreach ((array) $squad as $m) {
                if (is_array($m)) {
                    $id = trim((string) ($m['id'] ?? ''));
                    $nm = trim((string) ($m['name'] ?? ''));
                    if ($id !== '' && strtolower($id) !== 'null' && $nm !== '') {
                        $nameById[$id] = $nm;
                    }
                }
            }
        }
        $nameOf = static function ($id) use ($nameById): string {
            $k = trim((string) ($id ?? ''));
            if ($k === '' || strtolower($k) === 'null') {
                return '';
            }

            return $nameById[$k] ?? $k;
        };

        /** @var array<string,array<string,mixed>> $out */
        $out = [];
        $blank = static fn (): array => [
            'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'dots' => 0,
            'ones' => 0, 'twos' => 0, 'boundaryRuns' => 0,
            'perOver' => [],      // over index => runs off the bat
            'ballsPerOver' => [], // over index => legal balls faced
            'sequence' => [],     // runs off the bat, in order, for the settle split
        ];

        $striker = '';
        $nonStriker = '';
        $legalBalls = 0;
        $started = false;

        foreach ($actions as $act) {
            $type = (string) $act->action_type;
            $p = json_decode($act->payload, true) ?: [];

            if ($type === 'start') {
                $striker = $nameOf($p['striker_id'] ?? null);
                $nonStriker = $nameOf($p['non_striker_id'] ?? null);
                $legalBalls = 0;
                $started = true;
                continue;
            }
            if (! $started) {
                continue;
            }
            if ($type === 'change_bowler') {
                continue;
            }
            if ($type === 'change_batsman') {
                if (($p['role'] ?? 'striker') === 'striker') {
                    $striker = $nameOf($p['id'] ?? null);
                } else {
                    $nonStriker = $nameOf($p['id'] ?? null);
                }
                continue;
            }

            $isLegal = true;
            $runsOffBat = 0;
            $extras = 0;
            $wicket = false;
            switch ($type) {
                case 'runs':   $runsOffBat = (int) ($p['value'] ?? 0); break;
                case 'wide':   $isLegal = false; $extras = (int) ($p['value'] ?? 1); break;
                case 'noball': $isLegal = false; $runsOffBat = (int) ($p['runs_off_bat'] ?? 0); $extras = 1; break;
                case 'bye':    $extras = (int) ($p['value'] ?? 1); break;
                case 'legbye': $extras = (int) ($p['value'] ?? 1); break;
                case 'wicket': $wicket = true; break;
                default: continue 2;
            }

            $overIndex = intdiv($legalBalls, 6);

            if ($striker !== '') {
                $out[$striker] ??= $blank();
                $s = &$out[$striker];

                // Runs off the bat belong to the batter even off a no-ball; the extra does
                // not. Byes and leg-byes are the innings' runs, never the batter's.
                $s['runs'] += $runsOffBat;
                $s['boundaryRuns'] += ($runsOffBat === 4 || $runsOffBat === 6) ? $runsOffBat : 0;
                $s['perOver'][$overIndex] = ($s['perOver'][$overIndex] ?? 0) + $runsOffBat;

                if ($isLegal) {
                    $s['balls']++;
                    $s['ballsPerOver'][$overIndex] = ($s['ballsPerOver'][$overIndex] ?? 0) + 1;
                    $s['sequence'][] = $runsOffBat;

                    // A dot is a legal ball faced that produced nothing off the bat. A bye
                    // counts: the batter still did not score off it.
                    match ($runsOffBat) {
                        0 => $s['dots']++,
                        1 => $s['ones']++,
                        2 => $s['twos']++,
                        4 => $s['fours']++,
                        6 => $s['sixes']++,
                        default => null,
                    };
                }
                unset($s);
            }

            if ($isLegal) {
                $legalBalls++;
            }

            // Strike rotation, mirroring the innings replay exactly — including the order
            // of the two swaps and the fact that a wicket suppresses the odd-run one. This
            // bookkeeping decides WHOSE runs these are, so it has to agree with the
            // scorecard ball for ball; an independent implementation that drifts by one
            // swap puts somebody else's innings under a player's name.
            if ($wicket) {
                $striker = $nameOf($p['new_batsman_id'] ?? null);
            }
            $swap = ($type === 'bye' || $type === 'legbye') ? $extras : $runsOffBat;
            if (! $wicket && $swap % 2 === 1) {
                [$striker, $nonStriker] = [$nonStriker, $striker];
            }
            if ($isLegal && $legalBalls % 6 === 0) {
                [$striker, $nonStriker] = [$nonStriker, $striker];
            }
        }

        // Anybody who padded up but never faced a ball is not an innings.
        return array_filter($out, static fn (array $s): bool => $s['balls'] > 0);
    }

    // ─────────────────────────── The reading ───────────────────────────

    /** @return array<string,mixed> */
    private function summarise(string $name, array $b): array
    {
        $runs = (int) $b['runs'];
        $balls = (int) $b['balls'];
        $sr = $balls > 0 ? round($runs * 100 / $balls, 2) : 0.0;

        $boundaryPercent = $runs > 0 ? (int) round($b['boundaryRuns'] * 100 / $runs) : 0;
        $dotPercent = $balls > 0 ? (int) round($b['dots'] * 100 / $balls) : 0;
        $rotationPercent = $balls > 0 ? (int) round(($b['ones'] + $b['twos']) * 100 / $balls) : 0;

        $settle = $this->window(array_slice($b['sequence'], 0, self::SETTLE_BALLS));
        $after = $this->window(array_slice($b['sequence'], self::SETTLE_BALLS));

        $phases = $this->phases($b);
        $turning = $this->turningPoint($b);

        $facts = [
            'player' => $name,
            'runs' => $runs,
            'balls' => $balls,
            'strikeRate' => $sr,
            'fours' => (int) $b['fours'],
            'sixes' => (int) $b['sixes'],
            'dots' => (int) $b['dots'],
            'boundaryPercent' => $boundaryPercent,
            'dotPercent' => $dotPercent,
            'rotationPercent' => $rotationPercent,
            'settleBalls' => self::SETTLE_BALLS,
            'settle' => $settle,
            'after' => $after,
            'phases' => $phases,
            'turningPoint' => $turning,
        ];

        $facts['findings'] = $balls >= self::MIN_BALLS_FOR_FINDINGS
            ? $this->findings($facts)
            : [];
        $facts['note'] = $balls >= self::MIN_BALLS_FOR_FINDINGS
            ? null
            : "Too few balls faced to read anything into yet — $balls so far.";
        $facts['evidence'] = $this->evidence($facts);

        return $facts;
    }

    /**
     * A strike rate as it should be SAID.
     *
     * 342.42 is the right number to compute with and the wrong one to put in a sentence —
     * two decimal places in prose is the sound of a spreadsheet reading itself aloud.
     */
    private function sr(float $v): string
    {
        return (string) (int) round($v);
    }

    /** @return array{balls:int,runs:int,strikeRate:float} */
    private function window(array $seq): array
    {
        $balls = count($seq);
        $runs = array_sum($seq);

        return [
            'balls' => $balls,
            'runs' => (int) $runs,
            'strikeRate' => $balls > 0 ? round($runs * 100 / $balls, 2) : 0.0,
        ];
    }

    /**
     * The innings in thirds of the batter's OWN time at the crease.
     *
     * Not powerplay/middle/death. Those are properties of the innings, and a number 7 who
     * walks in at the death has no powerplay to report on — the phase table would be two
     * thirds empty and the one populated row would look like a weakness. Thirds of a
     * player's own stay are always defined and always comparable to each other.
     *
     * @return list<array<string,mixed>>
     */
    private function phases(array $b): array
    {
        $seq = $b['sequence'];
        $n = count($seq);
        if ($n < 6) {
            return [];
        }

        $cut = (int) ceil($n / 3);
        $labels = ['Start', 'Middle', 'Late'];
        $out = [];
        foreach ([0, 1, 2] as $i) {
            $slice = array_slice($seq, $i * $cut, $cut);
            if ($slice === []) {
                continue;
            }
            $w = $this->window($slice);
            $out[] = [
                'label' => $labels[$i],
                'runs' => $w['runs'],
                'balls' => $w['balls'],
                'strikeRate' => $w['strikeRate'],
            ];
        }

        return $out;
    }

    /** The over this batter scored most in. @return array<string,mixed>|null */
    private function turningPoint(array $b): ?array
    {
        if ($b['perOver'] === []) {
            return null;
        }
        $best = null;
        foreach ($b['perOver'] as $over => $runs) {
            if ($runs > 0 && ($best === null || $runs > $best['runs'])) {
                $best = [
                    'over' => $over + 1,
                    'runs' => (int) $runs,
                    'balls' => (int) ($b['ballsPerOver'][$over] ?? 0),
                ];
            }
        }

        return $best;
    }

    /**
     * Turning point, strength, opportunity — each chosen by rule, each with its evidence.
     *
     * The candidates are scored against thresholds that are stated in the `why` line, so
     * "Your strength: boundary conversion" is always followed by the number that made it
     * the strength. A verdict a reader cannot check is just an opinion in a nice font.
     *
     * @return list<array<string,string>>
     */
    private function findings(array $f): array
    {
        $out = [];

        if ($f['turningPoint'] !== null && $f['turningPoint']['runs'] >= 8) {
            $tp = $f['turningPoint'];
            $share = $f['runs'] > 0 ? (int) round($tp['runs'] * 100 / $f['runs']) : 0;
            $out[] = [
                'kind' => 'turning',
                'label' => 'Your turning point',
                'value' => "Over {$tp['over']} — {$tp['runs']} runs",
                'why' => "{$tp['runs']} of your {$f['runs']} came in that over, off {$tp['balls']} balls — $share% of your innings.",
            ];
        }

        // Strengths and weaknesses drawn from the same four measures, so the two verdicts
        // can never contradict each other.
        $fastest = $this->extremePhase($f['phases'], true);
        $slowest = $this->extremePhase($f['phases'], false);

        $strengths = [];
        if ($f['boundaryPercent'] >= 55) {
            $strengths[] = [
                'score' => $f['boundaryPercent'],
                'value' => 'Boundary conversion',
                'why' => "{$f['boundaryPercent']}% of your runs came in fours and sixes ({$f['fours']} fours, {$f['sixes']} sixes).",
            ];
        }
        if ($f['rotationPercent'] >= 45) {
            $strengths[] = [
                'score' => $f['rotationPercent'],
                'value' => 'Strike rotation',
                'why' => "You turned {$f['rotationPercent']}% of the balls you faced into ones and twos.",
            ];
        }
        if ($f['after']['balls'] >= 4 && $f['settle']['balls'] >= 4
            && $f['after']['strikeRate'] > $f['settle']['strikeRate'] * 1.3) {
            $strengths[] = [
                'score' => (int) $f['after']['strikeRate'],
                'value' => 'Acceleration',
                'why' => 'You struck at ' . $this->sr($f['settle']['strikeRate'])
                    . " over your first {$f['settle']['balls']} balls and "
                    . $this->sr($f['after']['strikeRate']) . ' after that.',
            ];
        }
        if ($fastest !== null && $strengths === []) {
            $strengths[] = [
                'score' => (int) $fastest['strikeRate'],
                'value' => $this->phasePhrase($fastest['label'], true),
                'why' => "{$fastest['runs']} off {$fastest['balls']} balls, striking at "
                    . $this->sr($fastest['strikeRate']) . '.',
            ];
        }
        if ($strengths !== []) {
            usort($strengths, static fn ($a, $b): int => $b['score'] <=> $a['score']);
            $out[] = [
                'kind' => 'strength',
                'label' => 'Your strength',
                'value' => $strengths[0]['value'],
                'why' => $strengths[0]['why'],
            ];
        }

        $gaps = [];
        if ($f['dotPercent'] >= 35) {
            $gaps[] = [
                'score' => $f['dotPercent'],
                'value' => 'Dot-ball pressure',
                'why' => "{$f['dotPercent']}% of the balls you faced went for nothing ({$f['dots']} of {$f['balls']}).",
            ];
        }
        if ($f['rotationPercent'] < 30 && $f['balls'] >= 10) {
            $gaps[] = [
                'score' => 100 - $f['rotationPercent'],
                'value' => 'Strike rotation',
                'why' => "Only {$f['rotationPercent']}% of your balls produced a one or a two.",
            ];
        }
        if ($slowest !== null && $fastest !== null
            && $slowest['label'] !== $fastest['label']
            && $slowest['strikeRate'] < $fastest['strikeRate'] * 0.6) {
            $gaps[] = [
                'score' => 60,
                'value' => $this->phasePhrase($slowest['label'], false),
                'why' => "{$slowest['runs']} off {$slowest['balls']} balls at " . $this->sr($slowest['strikeRate'])
                    . ', against ' . $this->sr($fastest['strikeRate'])
                    . ' in your ' . $this->phaseNoun($fastest['label']) . '.',
            ];
        }
        if ($gaps !== []) {
            usort($gaps, static fn ($a, $b): int => $b['score'] <=> $a['score']);
            $out[] = [
                'kind' => 'opportunity',
                'label' => 'Your opportunity',
                'value' => $gaps[0]['value'],
                'why' => $gaps[0]['why'],
            ];
        }

        return $out;
    }

    /**
     * A third of an innings, named the way a cricketer would name it.
     *
     * "Your Start slowed down" is a label leaking into a sentence. These read as English.
     */
    private function phasePhrase(string $label, bool $fast): string
    {
        return match ([$label, $fast]) {
            ['Start', true] => 'A fast start',
            ['Start', false] => 'A slow start',
            ['Middle', true] => 'Your best through the middle',
            ['Middle', false] => 'A quiet middle',
            ['Late', true] => 'Finishing strongly',
            default => 'A slow finish',
        };
    }

    private function phaseNoun(string $label): string
    {
        return match ($label) {
            'Start' => 'first few overs',
            'Middle' => 'middle',
            default => 'closing overs',
        };
    }

    /** @return array<string,mixed>|null */
    private function extremePhase(array $phases, bool $highest): ?array
    {
        $best = null;
        foreach ($phases as $ph) {
            if ($ph['balls'] < 3) {
                continue;
            }
            if ($best === null
                || ($highest && $ph['strikeRate'] > $best['strikeRate'])
                || (! $highest && $ph['strikeRate'] < $best['strikeRate'])) {
                $best = $ph;
            }
        }

        return $best;
    }

    /**
     * The rows behind the verdicts — what "show me the data" opens.
     *
     * @return list<array<string,string>>
     */
    private function evidence(array $f): array
    {
        $rows = [
            ['label' => 'Runs off the bat', 'value' => (string) $f['runs']],
            ['label' => 'Balls faced', 'value' => (string) $f['balls']],
            ['label' => 'Strike rate', 'value' => (string) $f['strikeRate']],
            ['label' => 'Fours / sixes', 'value' => "{$f['fours']} / {$f['sixes']}"],
            ['label' => 'Runs in boundaries', 'value' => "{$f['boundaryPercent']}%"],
            ['label' => 'Balls for no run', 'value' => "{$f['dotPercent']}% ({$f['dots']})"],
            ['label' => 'Balls turned into 1s and 2s', 'value' => "{$f['rotationPercent']}%"],
        ];

        if ($f['settle']['balls'] > 0 && $f['after']['balls'] > 0) {
            $rows[] = [
                'label' => "First {$f['settle']['balls']} balls",
                'value' => "{$f['settle']['runs']} runs at " . $this->sr($f['settle']['strikeRate']),
            ];
            $rows[] = [
                'label' => 'After that',
                'value' => "{$f['after']['runs']} runs at " . $this->sr($f['after']['strikeRate']),
            ];
        }

        foreach ($f['phases'] as $ph) {
            $rows[] = [
                'label' => "{$ph['label']} of your innings",
                'value' => "{$ph['runs']} off {$ph['balls']} at " . $this->sr($ph['strikeRate']),
            ];
        }

        return $rows;
    }

    // ─────────────────────────── The written line ───────────────────────────

    private function narrativeKey(LiveMatch $match, array $facts): string
    {
        // Keyed on balls faced, so the line is rewritten as the innings moves on and never
        // describes an innings that has since changed shape.
        return 'iq:' . $match->id . ':' . md5((string) $facts['player']) . ':' . $facts['balls'];
    }

    private function cachedNarrative(LiveMatch $match, array $facts): ?string
    {
        $v = Cache::get($this->narrativeKey($match, $facts));

        return is_string($v) && $v !== '' ? $v : null;
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You write two sentences about one batter's innings for a cricket app.

Absolute rules:
- Use ONLY the numbers given to you. Never introduce a number that is not in the input.
- Never mention bowler type, pace, spin, field settings, shot names, conditions, pressure,
  the match situation or the opposition. None of that is in the data and you cannot know it.
- Never compare this innings to any other innings, player, average or season.
- No praise, no coaching, no advice. Describe the shape of the innings, nothing else.
- Plain English, second person ("you"), no emoji, no markdown, no headings.
- Two sentences maximum. Under 45 words.

Write about how the innings was PACED and where the runs came from. If the numbers do not
support an interesting observation, say what happened plainly.
TXT;
    }

    private function factsPrompt(array $f): string
    {
        $lines = [
            "Runs off the bat: {$f['runs']} from {$f['balls']} balls (strike rate {$f['strikeRate']}).",
            "Fours: {$f['fours']}. Sixes: {$f['sixes']}.",
            "{$f['boundaryPercent']}% of the runs came in boundaries.",
            "{$f['dotPercent']}% of balls faced produced no run.",
            "{$f['rotationPercent']}% of balls faced produced one or two runs.",
        ];
        if ($f['settle']['balls'] > 0 && $f['after']['balls'] > 0) {
            $lines[] = "First {$f['settle']['balls']} balls: {$f['settle']['runs']} runs at strike rate {$f['settle']['strikeRate']}.";
            $lines[] = "After that: {$f['after']['runs']} runs from {$f['after']['balls']} balls at strike rate {$f['after']['strikeRate']}.";
        }
        foreach ($f['phases'] as $ph) {
            $lines[] = "{$ph['label']} third of the innings: {$ph['runs']} runs off {$ph['balls']} balls at strike rate {$ph['strikeRate']}.";
        }
        if ($f['turningPoint'] !== null) {
            $lines[] = "Best over for this batter: over {$f['turningPoint']['over']}, {$f['turningPoint']['runs']} runs off {$f['turningPoint']['balls']} balls.";
        }

        return implode("\n", $lines);
    }

    private function clean(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        $text = trim(str_replace(['**', '*', '#', '`'], '', $text));

        return mb_substr($text, 0, 400);
    }
}
