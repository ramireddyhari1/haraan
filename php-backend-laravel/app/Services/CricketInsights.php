<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The Insights tab: what actually happened in this match, and a short read on why.
 *
 * The division of labour here is the whole design, and it is not negotiable:
 *
 *   · EVERY NUMBER is computed from the ball log, by {@see facts()}. Deterministic, the
 *     same arithmetic the scorecard uses, and correct whether or not any model is
 *     reachable.
 *   · The MODEL only writes prose ABOUT those numbers. It is handed labelled facts and
 *     told it may not introduce a figure of its own.
 *
 * A language model asked to "analyse this match" will invent a strike rate that sounds
 * plausible, and a fabricated statistic on a screen full of real ones poisons all of them —
 * the reader cannot tell which is which, so they stop trusting the tab. Computing first and
 * writing second is what makes this feature safe to ship.
 *
 * The figures render with no API key at all. That is the normal degraded state, and it is
 * still most of the value.
 */
final class CricketInsights
{
    private const DEFAULT_MODEL = 'gemini-3.6-flash';
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /** Runs after the response is already sent, but still pins a PHP worker. Keep it short. */
    private const TIMEOUT_SECONDS = 12;

    public function isConfigured(): bool
    {
        return $this->vertexKeyPath() !== '' || trim((string) config('services.gemini.key')) !== '';
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

    // ─────────────────────────── The figures ───────────────────────────

    /**
     * Replay the ball log and derive everything the tab shows.
     *
     * @return array{innings: array<int,array<string,mixed>>, totalBalls: int}
     */
    public function facts(LiveMatch $match): array
    {
        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id', 'asc')
            ->get();

        // Squad id -> name, so the crease and the face-offs can be named. An exact id join,
        // never a name match: a wrong name beside a bowling figure is a lie about a person.
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

        $innings = [];
        $cur = null;
        $totalBalls = 0;

        $close = function () use (&$cur, &$innings): void {
            if ($cur !== null) {
                $innings[] = $this->summarise($cur);
                $cur = null;
            }
        };

        foreach ($actions as $act) {
            $type = (string) $act->action_type;
            $p = json_decode($act->payload, true) ?: [];

            if ($type === 'start') {
                $close();
                $bt = (int) ($p['batting_team'] ?? 1);
                $cur = [
                    'battingTeam' => $bt,
                    'battingName' => (string) ($bt === 2
                        ? ($match->away_full ?: $match->away)
                        : ($match->home_full ?: $match->home)),
                    'runs' => 0, 'wickets' => 0, 'legalBalls' => 0,
                    'fours' => 0, 'sixes' => 0, 'dots' => 0, 'boundaryRuns' => 0,
                    'overRuns' => [], 'overWkts' => [], 'overBalls' => [],
                    'partnerships' => [],
                    'striker' => $nameOf($p['striker_id'] ?? null),
                    'nonStriker' => $nameOf($p['non_striker_id'] ?? null),
                    'bowler' => $nameOf($p['bowler_id'] ?? null),
                    'stand' => ['runs' => 0, 'balls' => 0, 'a' => '', 'b' => ''],
                    // Every run type counted, so the breakdown is tallied rather than inferred.
                    'tally' => ['dots' => 0, 'ones' => 0, 'twos' => 0, 'threes' => 0,
                                'fours' => 0, 'sixes' => 0, 'extras' => 0],
                    'faceoff' => [],
                    // Boundaries whose direction the scorer actually recorded. Absent for
                    // every ball scored before the picker existed and every one skipped —
                    // the wheel draws what was seen, never what was assumed.
                    'shots' => [],
                ];
                $cur['stand']['a'] = $cur['striker'];
                $cur['stand']['b'] = $cur['nonStriker'];
                continue;
            }
            if ($cur === null) {
                continue;
            }
            if ($type === 'change_bowler') {
                $cur['bowler'] = $nameOf($p['bowler_id'] ?? null);
                continue;
            }
            if ($type === 'change_batsman') {
                if (($p['role'] ?? 'striker') === 'striker') {
                    $cur['striker'] = $nameOf($p['id'] ?? null);
                } else {
                    $cur['nonStriker'] = $nameOf($p['id'] ?? null);
                }
                continue;
            }

            $isLegal = true; $runsOffBat = 0; $extras = 0; $wicket = false;
            switch ($type) {
                case 'runs':   $runsOffBat = (int) ($p['value'] ?? 0); break;
                case 'wide':   $isLegal = false; $extras = (int) ($p['value'] ?? 1); break;
                case 'noball': $isLegal = false; $runsOffBat = (int) ($p['runs_off_bat'] ?? 0); $extras = 1; break;
                case 'bye':    $extras = (int) ($p['value'] ?? 1); break;
                case 'legbye': $extras = (int) ($p['value'] ?? 1); break;
                case 'wicket': $wicket = true; break;
                default: continue 2;
            }

            $total = $runsOffBat + $extras;
            $cur['runs'] += $total;
            $cur['stand']['runs'] += $total;

            $overIndex = intdiv($cur['legalBalls'], 6);
            $cur['overRuns'][$overIndex] = ($cur['overRuns'][$overIndex] ?? 0) + $total;
            if ($wicket) {
                $cur['overWkts'][$overIndex] = ($cur['overWkts'][$overIndex] ?? 0) + 1;
            }

            // The scorer's own shorthand for this delivery, kept per over so the tab can
            // draw the innings the way the board does — a row of pips per over. This is the
            // most cricket-native view there is, and it costs nothing to carry.
            $cur['overBalls'][$overIndex][] = match (true) {
                $wicket => 'W',
                $type === 'wide' => 'wd',
                $type === 'noball' => 'nb',
                $type === 'bye' => 'b' . $extras,
                $type === 'legbye' => 'lb' . $extras,
                default => (string) $runsOffBat,
            };

            if ($isLegal) {
                $cur['legalBalls']++;
                $cur['stand']['balls']++;
                $totalBalls++;
            }

            // Face-off: this bowler against this batter. Wides are excluded — the batter did
            // not face them in any sense a head-to-head should count.
            $bw = $cur['bowler'];
            $st = $cur['striker'];
            if ($bw !== '' && $st !== '' && $type !== 'wide') {
                $k = $bw . "\x1f" . $st;
                $cur['faceoff'][$k] ??= ['bowler' => $bw, 'batter' => $st, 'balls' => 0, 'runs' => 0, 'wickets' => 0];
                $cur['faceoff'][$k]['balls']++;
                $cur['faceoff'][$k]['runs'] += $runsOffBat;
                if ($wicket) {
                    $cur['faceoff'][$k]['wickets']++;
                }
            }

            // A zone is only meaningful on a scoring shot off the bat, and only when it
            // was captured. `zone` is absent on almost every historical ball.
            $zone = $p['zone'] ?? null;
            if ($type === 'runs' && is_numeric($zone) && (int) $zone >= 0 && (int) $zone <= 7 && $runsOffBat > 0) {
                $cur['shots'][] = [
                    'zone' => (int) $zone,
                    'runs' => $runsOffBat,
                    'batter' => $cur['striker'],
                    'over' => intdiv($cur['legalBalls'], 6) + 1,
                    // Exact landing point when the scorer tapped one. Older captures
                    // recorded only a region, so these may be absent even where a zone is
                    // present — the wheel falls back to the region's centre for those.
                    'x' => isset($p['x']) ? round((float) $p['x'], 3) : null,
                    'y' => isset($p['y']) ? round((float) $p['y'], 3) : null,
                ];
            }

            if ($type === 'runs') {
                switch ($runsOffBat) {
                    case 0: $cur['dots']++; $cur['tally']['dots']++; break;
                    case 1: $cur['tally']['ones']++; break;
                    case 2: $cur['tally']['twos']++; break;
                    case 3: $cur['tally']['threes']++; break;
                    case 4: $cur['fours']++; $cur['boundaryRuns'] += 4; $cur['tally']['fours']++; break;
                    case 6: $cur['sixes']++; $cur['boundaryRuns'] += 6; $cur['tally']['sixes']++; break;
                }
            }
            $cur['tally']['extras'] += $extras;

            if ($wicket) {
                $cur['wickets']++;
                $cur['partnerships'][] = $cur['stand'];
                $newName = $nameOf($p['new_batsman_id'] ?? null);
                $cur['striker'] = $newName;
                $cur['stand'] = ['runs' => 0, 'balls' => 0, 'a' => $newName, 'b' => $cur['nonStriker']];
            }

            // Strike rotation, so the next ball is attributed to the right batter.
            $swap = ($type === 'bye' || $type === 'legbye') ? $extras : $runsOffBat;
            if (! $wicket && $swap % 2 === 1) {
                [$cur['striker'], $cur['nonStriker']] = [$cur['nonStriker'], $cur['striker']];
            }
            if ($isLegal && $cur['legalBalls'] % 6 === 0) {
                [$cur['striker'], $cur['nonStriker']] = [$cur['nonStriker'], $cur['striker']];
            }
        }
        $close();

        return ['innings' => $innings, 'totalBalls' => $totalBalls];
    }

    /**
     * Turn one innings' running tally into the shapes the tab renders.
     *
     * Phases are PROPORTIONAL, not the T20 powerplay/middle/death everyone hardcodes: a
     * gully match is as likely to be 5 or 8 overs as 20, and "overs 1-6 are the powerplay"
     * is nonsense in a 6-over game. First 30% / middle 50% / last 20%, named plainly.
     *
     * @param array<string,mixed> $cur
     * @return array<string,mixed>
     */
    private function summarise(array $cur): array
    {
        $overs = $cur['overRuns'];
        ksort($overs);
        $overCount = count($overs);
        $legal = (int) $cur['legalBalls'];

        $rate = static fn (int $runs, int $balls): float => $balls > 0 ? round($runs * 6 / $balls, 2) : 0.0;

        // Balls actually bowled in a given over index — the last one may be part-bowled.
        $ballsInOver = static function (int $i) use ($legal, $overCount): int {
            return $i === $overCount - 1 ? max(1, $legal - ($i * 6)) : 6;
        };

        // ── Phases of play ──
        $phases = [];
        if ($overCount > 0) {
            $startEnd = max(1, (int) ceil($overCount * 0.30));
            $middleEnd = max($startEnd, (int) ceil($overCount * 0.80));
            $buckets = [
                'Start' => range(0, $startEnd - 1),
                'Middle' => $middleEnd > $startEnd ? range($startEnd, $middleEnd - 1) : [],
                'Finish' => $overCount > $middleEnd ? range($middleEnd, $overCount - 1) : [],
            ];
            foreach ($buckets as $label => $idxs) {
                if ($idxs === []) {
                    continue;
                }
                $runs = 0; $balls = 0; $wkts = 0;
                foreach ($idxs as $i) {
                    $runs += (int) ($overs[$i] ?? 0);
                    $wkts += (int) ($cur['overWkts'][$i] ?? 0);
                    $balls += $ballsInOver($i);
                }
                $phases[] = [
                    'label' => $label,
                    'overs' => count($idxs),
                    'runs' => $runs,
                    'wickets' => $wkts,
                    'runRate' => $rate($runs, $balls),
                ];
            }
        }

        // ── Match progress: cumulative score after each over ──
        $progress = [];
        $running = 0;
        $runningWkts = 0;
        foreach (array_keys($overs) as $i) {
            $running += (int) $overs[$i];
            $runningWkts += (int) ($cur['overWkts'][$i] ?? 0);
            $progress[] = [
                'over' => $i + 1,
                'runs' => (int) $overs[$i],
                'wickets' => (int) ($cur['overWkts'][$i] ?? 0),
                'total' => $running,
                'totalWickets' => $runningWkts,
                'balls' => array_values((array) ($cur['overBalls'][$i] ?? [])),
            ];
        }

        // ── Game-changing overs ──
        // Not simply "the most runs": an over that took two wickets can change a match more
        // than one that went for twelve. Scored on both, so the list is about IMPACT rather
        // than about run-scoring alone. Wickets are weighted at roughly the cost of an over.
        $changing = [];
        foreach (array_keys($overs) as $i) {
            $r = (int) $overs[$i];
            $w = (int) ($cur['overWkts'][$i] ?? 0);
            $swing = $r + ($w * 9);
            if ($swing <= 0) {
                continue;
            }
            $changing[] = ['over' => $i + 1, 'runs' => $r, 'wickets' => $w, 'swing' => $swing];
        }
        usort($changing, static fn (array $a, array $b): int => $b['swing'] <=> $a['swing']);
        $changing = array_slice($changing, 0, 3);

        // The single most expensive over, kept for the summary line.
        $bestOver = null;
        if ($overCount > 0) {
            $maxIdx = array_keys($overs, max($overs))[0];
            $bestOver = ['over' => $maxIdx + 1, 'runs' => (int) $overs[$maxIdx]];
        }

        // ── Partnerships ──
        $stands = $cur['partnerships'];
        if (($cur['stand']['runs'] ?? 0) > 0 || ($cur['stand']['balls'] ?? 0) > 0) {
            $unbroken = $cur['stand'];
            $unbroken['unbroken'] = true;
            $stands[] = $unbroken;
        }
        $partnerships = [];
        foreach ($stands as $n => $st) {
            $pair = array_values(array_filter([$st['a'] ?? '', $st['b'] ?? '']));
            $partnerships[] = [
                'wicket' => $n + 1,
                'runs' => (int) $st['runs'],
                'balls' => (int) $st['balls'],
                'batters' => $pair === [] ? '' : implode(' & ', $pair),
                'unbroken' => (bool) ($st['unbroken'] ?? false),
            ];
        }
        $best = null;
        foreach ($partnerships as $st) {
            if ($best === null || $st['runs'] > $best['runs']) {
                $best = $st;
            }
        }

        // ── Face-offs: the contests that actually got bowled ──
        // Ordered by balls, because a head-to-head is only interesting once it has lasted.
        $faceoffs = array_values($cur['faceoff']);
        usort($faceoffs, static fn (array $a, array $b): int => [$b['balls'], $b['runs']] <=> [$a['balls'], $a['runs']]);
        $faceoffs = array_slice(array_filter($faceoffs, static fn (array $f): bool => $f['balls'] >= 3), 0, 5);
        foreach ($faceoffs as &$f) {
            $f['strikeRate'] = $f['balls'] > 0 ? round($f['runs'] * 100 / $f['balls'], 1) : 0.0;
        }
        unset($f);

        return [
            'battingTeam' => $cur['battingTeam'],
            'battingName' => $cur['battingName'],
            'runs' => (int) $cur['runs'],
            'wickets' => (int) $cur['wickets'],
            'overs' => intdiv($legal, 6) . '.' . ($legal % 6),
            'runRate' => $rate((int) $cur['runs'], $legal),
            'phases' => $phases,
            'progress' => $progress,
            'changingOvers' => $changing,
            'partnerships' => $partnerships,
            'faceoffs' => $faceoffs,
            'breakdown' => $cur['tally'],
            'shots' => $cur['shots'],
            // Runs per zone, so the wheel can show weight as well as individual shots.
            'shotZones' => (static function (array $shots): array {
                $z = [];
                foreach ($shots as $sh) {
                    $k = (int) $sh['zone'];
                    $z[$k] ??= ['zone' => $k, 'shots' => 0, 'runs' => 0];
                    $z[$k]['shots']++;
                    $z[$k]['runs'] += (int) $sh['runs'];
                }
                ksort($z);

                return array_values($z);
            })($cur['shots']),
            'bestOver' => $bestOver,
            'bestPartnership' => $best,
            'fours' => (int) $cur['fours'],
            'sixes' => (int) $cur['sixes'],
            'boundaryPercent' => $cur['runs'] > 0
                ? (int) round($cur['boundaryRuns'] * 100 / $cur['runs'])
                : 0,
            'dotPercent' => $legal > 0 ? (int) round($cur['dots'] * 100 / $legal) : 0,
        ];
    }

    // ─────────────────────────── The words ───────────────────────────

    /**
     * The written read, cached on the match.
     *
     * Rewritten when the match has moved on since it was last written — an analysis of the
     * first four overs is worthless once twenty have been bowled — and never blocking:
     * every failure path returns null and the tab shows its figures alone.
     */
    public function analysis(LiveMatch $match, array $facts, bool $allowWrite = true): ?string
    {
        $stored = trim((string) ($match->insights ?? ''));
        $writtenAt = $match->insights_balls === null ? -1 : (int) $match->insights_balls;
        $now = (int) ($facts['totalBalls'] ?? 0);

        // Fresh enough: same ball count, or the match is over and nothing more can change.
        if ($stored !== '' && $writtenAt === $now) {
            return $stored;
        }
        if (! $allowWrite || ! $this->isConfigured() || $now < 6) {
            // Under an over of cricket there is nothing to analyse; the figures speak.
            return $stored !== '' ? $stored : null;
        }

        $line = $this->write($match, $facts);
        if ($line === null) {
            return $stored !== '' ? $stored : null;
        }

        $match->forceFill([
            'insights' => $line,
            'insights_at' => Carbon::now(),
            'insights_balls' => $now,
        ])->save();

        return $line;
    }

    private function write(LiveMatch $match, array $facts): ?string
    {
        $model = (string) (config('services.gemini.model') ?: self::DEFAULT_MODEL);

        $body = [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt()]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $this->factsPrompt($match, $facts)]],
            ]],
            'generationConfig' => [
                'temperature' => 0.7,
                // Generous: on Gemini 3.x THINKING TOKENS COUNT AGAINST THIS BUDGET, and a
                // budget sized to the visible answer comes back truncated mid-sentence.
                'maxOutputTokens' => 900,
                'candidateCount' => 1,
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];

        [$url, $headers] = $this->endpoint($model);
        if ($url === null) {
            return null;
        }

        try {
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
                Log::warning('insights: HTTP ' . ($response?->status() ?? 0), [
                    'match' => $match->id,
                    'body' => mb_substr((string) ($response?->body() ?? ''), 0, 300),
                ]);

                return null;
            }

            return $this->clean((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        } catch (\Throwable $e) {
            Log::warning('insights failed: ' . $e->getMessage(), ['match' => $match->id]);

            return null;
        }
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
        You are a cricket analyst writing the "Insights" panel for a grassroots match.

        You are given the match's REAL figures, already computed. Write 2 to 4 short
        observations about what those figures mean.

        Absolute rules:
        - NEVER state a number that is not in the facts you were given. Do not calculate,
          estimate, round, or infer any new figure. If you want to mention a number, copy it
          exactly from the facts.
        - Never invent a player, a shot, a delivery, a dismissal, a crowd, or a conditions
          detail. You were not told them.
        - Do NOT join two separate facts into one claim that implies they happened together
          or that one caused the other. The biggest stand and the most expensive over are
          different facts; you were not told they were the same passage of play. Each
          sentence should rest on facts you can point at.
        - Explain what the figures MEAN — a high dot-ball share means pressure, a big
          boundary share means the runs came in bursts, a collapse means wickets clustered.
          The reader can already see the numbers; tell them what they add up to.
        - One observation per line. No bullets, no dashes, no numbering, no markdown, no
          headings. Just the sentences, one per line.
        - 10 to 26 words per line. Plain, confident, specific.
        - Indian grassroots cricket. Warm and knowledgeable, never sarcastic about a player
          or a team.
        - If the match is still in progress, write about what has happened so far and do not
          predict the result.
        TXT;
    }

    /** @param array<string,mixed> $facts */
    private function factsPrompt(LiveMatch $match, array $facts): string
    {
        $lines = [];
        $lines[] = 'Match: ' . ($match->home_full ?: $match->home) . ' v ' . ($match->away_full ?: $match->away);
        $lines[] = 'State: ' . (strtolower((string) $match->status) === 'live' ? 'still in progress' : (string) $match->status);

        foreach (($facts['innings'] ?? []) as $i => $inn) {
            $n = $i + 1;
            $lines[] = '';
            $lines[] = "Innings $n — {$inn['battingName']}: {$inn['runs']}/{$inn['wickets']} in {$inn['overs']} overs, run rate {$inn['runRate']}";
            $lines[] = "  Boundaries: {$inn['fours']} fours, {$inn['sixes']} sixes ({$inn['boundaryPercent']}% of runs came in boundaries)";
            $lines[] = "  Dot balls: {$inn['dotPercent']}% of deliveries";
            foreach (($inn['phases'] ?? []) as $ph) {
                $lines[] = "  {$ph['label']} ({$ph['overs']} overs): {$ph['runs']} runs at {$ph['runRate']} per over";
            }
            if (! empty($inn['bestOver'])) {
                $lines[] = "  Most expensive over: over {$inn['bestOver']['over']} went for {$inn['bestOver']['runs']}";
            }
            if (! empty($inn['bestPartnership'])) {
                $lines[] = "  Best stand: {$inn['bestPartnership']['runs']} runs off {$inn['bestPartnership']['balls']} balls";
            }
        }

        return "Write the insights for this match.\n\n" . implode("\n", $lines);
    }

    /**
     * Models add bullets, numbering and headings however firmly you ask them not to. Strip
     * the furniture, drop anything empty, and cap the count so the panel cannot be flooded.
     */
    private function clean(string $text): ?string
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = trim($raw);
            $line = preg_replace('/^\s*(?:[-*\x{2022}\x{2013}\x{2014}]|\d+[.)])\s*/u', '', $line) ?? $line;
            // preg_replace with /u, NOT trim() with a charlist. \x{...} is REGEX
            // syntax: inside a PHP string it stays literal, so a "charlist" written that
            // way is really the characters \ x { } 2 0 1 C D 8 9 - and trim() then
            // eats the D off "Despite". Seen in production as "espite facing 67% dots".
            // A byte charlist is wrong for UTF-8 regardless: it can strip the lead byte
            // of a multibyte character and leave broken text behind.
            $line = preg_replace('/^[\"\x{201C}\x{2018}]+|[\"\x{201D}\x{2019}]+$/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '' || mb_strlen($line) > 220) {
                continue;
            }
            $out[] = $line;
            if (count($out) === 4) {
                break;
            }
        }

        return $out === [] ? null : implode("\n", $out);
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
                Log::warning('insights: could not mint a Vertex access token');

                return [null, []];
            }
            // "global" is its own HOST, not a region prefix — a us-central1-style URL 404s
            // for the current Gemini models. Same trap as CricketCommentary.
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
