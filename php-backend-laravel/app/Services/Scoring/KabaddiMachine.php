<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Kabaddi, one raid at a time.
 *
 * A kabaddi score is only half the story; the other half is the MAT — how many players each
 * side still has in. That decides everything a viewer watches for: a defence down to three can
 * pull off a SUPER TACKLE worth two, a side that loses its last player concedes an ALL OUT
 * worth two more and comes back at full strength, and a team that has raided empty twice must
 * score on the third raid (DO-OR-DIE) or lose the raider.
 *
 * What the scorer records, one event per raid outcome:
 *
 *   point  raid:N[:b]   side = raiding team. N defenders touched out, optional bonus (+1).
 *   point  bonus        side = raiding team. Bonus only, nobody out.
 *   point  tackle       side = defending team. The raider is out; a super tackle is DERIVED
 *                       (worth 2) when the defence had three or fewer on the mat.
 *   point  dod_out      side = defending team. A failed do-or-die: raider out, one point.
 *   raid   empty        side = raiding team. Nothing scored.
 *   point  technical    side = the team awarded it. No outs.
 *
 * The mat, revivals and all-outs are replayed, never stored. This only runs when the match was
 * created for mat scoring (`sport_state.rules.mat`); older matches were scored as bare tallies
 * with a manual "all out" button, and replaying those through a mat would rewrite results.
 * The legacy details (raid, super_raid, all_out) still read correctly here.
 */
final class KabaddiMachine
{
    public array $score = ['home' => 0, 'away' => 0];

    /** Players on the mat. */
    public array $onMat;

    /** Players out, in the order they went (revived first-out-first-in). */
    public array $out = ['home' => 0, 'away' => 0];

    /** Consecutive empty raids by each team — the third is do-or-die. */
    public array $emptyStreak = ['home' => 0, 'away' => 0];

    /** Which team raids next. Null until the first raid or a `serve` event names it. */
    public ?string $raiding = null;

    private ?string $firstRaider = null;

    /** @var array<string, array<string, int>> */
    public array $stats;

    /** @var array<string, array<string, array<string, int>>> per player, per side */
    public array $players = ['home' => [], 'away' => []];

    private int $size;

    /**
     * Version 2 rules (matches created from 2026-09-16, with the mat tracked):
     *  - an EMPTY do-or-die raid is a failed do-or-die: the raider is out and the defence
     *    scores one, exactly as if the raider had been tackled;
     *  - the BONUS line is live only with six or more defenders on the mat;
     *  - a bonus point is not a touch point, so it revives no one.
     */
    private bool $v2;

    /** @param array<string, mixed> $format */
    public function __construct(array $format = [], private bool $trackMat = true, int $rulesVersion = 1)
    {
        $this->v2 = $rulesVersion >= 2 && $trackMat;
        $this->size = max(1, (int) ($format['players'] ?? 7));
        $this->onMat = ['home' => $this->size, 'away' => $this->size];
        $blank = [
            'raids' => 0, 'successful_raids' => 0, 'empty_raids' => 0, 'raids_tackled' => 0,
            'raid_points' => 0, 'bonus_points' => 0, 'tackle_points' => 0, 'all_out_points' => 0,
            'super_raids' => 0, 'super_tackles' => 0, 'all_outs_inflicted' => 0,
            'do_or_die_raids' => 0, 'do_or_die_won' => 0, 'technical_points' => 0,
        ];
        $this->stats = ['home' => $blank, 'away' => $blank];
    }

    public static function other(string $side): string
    {
        return $side === 'home' ? 'away' : 'home';
    }

    public function tracksMat(): bool
    {
        return $this->trackMat;
    }

    public function isDoOrDie(?string $raider = null): bool
    {
        $raider ??= $this->raiding;

        return $raider !== null && $this->emptyStreak[$raider] >= 2;
    }

    /** The scorer names who raids next (the toss, or a correction). */
    public function setRaiding(string $side): void
    {
        $this->raiding = $side;
        $this->firstRaider ??= $side;
    }

    /** Half time: the side that did NOT raid first opens the second half. */
    public function period(): void
    {
        if ($this->firstRaider !== null) {
            $this->raiding = self::other($this->firstRaider);
        }
        $this->emptyStreak = ['home' => 0, 'away' => 0];
    }

    /**
     * One raid outcome.
     *
     * @return array{value: int, raider: string|null, tags: array<int, string>, all_out: string|null, do_or_die: bool}
     *         value = points credited to the event's side (all-out bonus included)
     */
    public function apply(string $kind, ?string $side, string $detail, string $player = ''): array
    {
        $detail = strtolower(trim($detail));
        $result = ['value' => 0, 'raider' => null, 'tags' => [], 'all_out' => null, 'do_or_die' => false];
        if (! in_array($side, ['home', 'away'], true)) {
            return $result;
        }

        // An empty raid scores nothing but is still a raid: it moves the turn and the streak.
        if ($kind === 'raid') {
            $dod = $this->isDoOrDie($side);
            if ($dod && $this->v2) {
                // A do-or-die raid that scores nothing loses the raider.
                return $this->tackle(self::other($side), true, '');
            }
            $this->beginRaid($side, $dod);
            $this->stats[$side]['empty_raids']++;
            $this->emptyStreak[$side]++;
            $this->player($side, $player, 'raids');
            $this->endRaid($side);

            return ['value' => 0, 'raider' => $side, 'tags' => $dod ? ['do_or_die'] : [], 'all_out' => null, 'do_or_die' => $dod];
        }

        if ($kind !== 'point') {
            return $result;
        }

        [$base, $touches, $bonus] = $this->parse($detail);

        return match ($base) {
            'raid', 'super_raid', 'bonus' => $this->raidPoints($side, $touches, $bonus, $player),
            'tackle', 'dod_out' => $this->tackle($side, $base === 'dod_out', $player),
            'all_out' => $this->manualAllOut($side),
            default => $this->technical($side),
        };
    }

    /** @return array{0: string, 1: int, 2: bool} base, defenders touched, bonus */
    private function parse(string $detail): array
    {
        if (str_starts_with($detail, 'raid:')) {
            $parts = explode(':', $detail);
            $touches = max(0, min(7, (int) ($parts[1] ?? 1)));
            $bonus = in_array('b', array_slice($parts, 2), true);

            return ['raid', $touches, $bonus];
        }

        return match ($detail) {
            '', 'raid' => ['raid', 1, false],
            'super_raid' => ['super_raid', 3, false],
            'bonus' => ['bonus', 0, true],
            'tackle', 'super_tackle' => ['tackle', 0, false],
            'dod_out' => ['dod_out', 0, false],
            'all_out' => ['all_out', 0, false],
            default => ['technical', 0, false],
        };
    }

    private function raidPoints(string $raider, int $touches, bool $bonus, string $player): array
    {
        $defender = self::other($raider);
        $dod = $this->isDoOrDie($raider);
        $this->beginRaid($raider, $dod);

        // You cannot touch out more defenders than are standing.
        if ($this->trackMat) {
            $touches = min($touches, $this->onMat[$defender]);
        }
        $tags = [];
        // The bonus line only counts against a defence of six or more.
        if ($bonus && $this->v2 && $this->onMat[$defender] < 6) {
            $bonus = false;
            $tags[] = 'bonus_disallowed';
        }
        $points = $touches + ($bonus ? 1 : 0);

        if ($points > 0) {
            $this->stats[$raider]['successful_raids']++;
            $this->emptyStreak[$raider] = 0;
            if ($dod) {
                $this->stats[$raider]['do_or_die_won']++;
                $tags[] = 'do_or_die';
            }
        } else {
            $this->stats[$raider]['empty_raids']++;
            $this->emptyStreak[$raider]++;
        }
        $this->stats[$raider]['raid_points'] += $touches;
        $this->stats[$raider]['bonus_points'] += $bonus ? 1 : 0;
        $this->player($raider, $player, 'raids');
        $this->player($raider, $player, 'raid_points', $touches);
        $this->player($raider, $player, 'bonus_points', $bonus ? 1 : 0);

        if ($points >= 3) {
            $this->stats[$raider]['super_raids']++;
            $this->player($raider, $player, 'super_raids');
            $tags[] = 'super_raid';
        }

        $this->score[$raider] += $points;
        $allOut = null;
        $value = $points;

        if ($this->trackMat) {
            $this->knockOut($defender, $touches);
            // Touch points revive teammates; under v2 a bonus point does not.
            $this->revive($raider, $this->v2 ? $touches : $points);
            if ($this->onMat[$defender] === 0 && $touches > 0) {
                $value += $this->allOut($defender);
                $allOut = $defender;
                $tags[] = 'all_out';
            }
        }

        $this->endRaid($raider);

        return ['value' => $value, 'raider' => $raider, 'tags' => $tags, 'all_out' => $allOut, 'do_or_die' => $dod];
    }

    private function tackle(string $defender, bool $doOrDieFail, string $player): array
    {
        $raider = self::other($defender);
        $dod = $doOrDieFail || $this->isDoOrDie($raider);
        $this->beginRaid($raider, $dod);
        $this->stats[$raider]['raids_tackled']++;
        $this->emptyStreak[$raider] = 0;

        // A super tackle: the defence stopped the raid with three or fewer on the mat.
        $super = ! $doOrDieFail && $this->trackMat && $this->onMat[$defender] <= 3;
        $points = $super ? 2 : 1;
        $tags = [];
        if ($super) {
            $this->stats[$defender]['super_tackles']++;
            $this->player($defender, $player, 'super_tackles');
            $tags[] = 'super_tackle';
        }
        if ($dod) {
            $tags[] = 'do_or_die';
        }

        $this->stats[$defender]['tackle_points'] += $points;
        $this->player($defender, $player, 'tackle_points', $points);
        $this->score[$defender] += $points;
        $allOut = null;
        $value = $points;

        if ($this->trackMat) {
            $this->knockOut($raider, 1);
            // A super tackle's extra point is a bonus; it revives no one.
            $this->revive($defender, 1);
            if ($this->onMat[$raider] === 0) {
                $value += $this->allOut($raider);
                $allOut = $raider;
                $tags[] = 'all_out';
            }
        }

        $this->endRaid($raider);

        return ['value' => $value, 'raider' => $raider, 'tags' => $tags, 'all_out' => $allOut, 'do_or_die' => $dod];
    }

    /** The legacy manual "All out" button: two points, the other side back to full. */
    private function manualAllOut(string $side): array
    {
        $this->score[$side] += 2;
        $this->stats[$side]['all_out_points'] += 2;
        $this->stats[$side]['all_outs_inflicted']++;
        if ($this->trackMat) {
            $victim = self::other($side);
            $this->onMat[$victim] = $this->size;
            $this->out[$victim] = 0;
        }

        return ['value' => 2, 'raider' => null, 'tags' => ['all_out'], 'all_out' => self::other($side), 'do_or_die' => false];
    }

    private function technical(string $side): array
    {
        $this->score[$side] += 1;
        $this->stats[$side]['technical_points']++;
        if ($this->trackMat) {
            $this->revive($side, 1);
        }

        return ['value' => 1, 'raider' => null, 'tags' => [], 'all_out' => null, 'do_or_die' => false];
    }

    /** Clearing the mat: two points to the other side, and the whole team back on. */
    private function allOut(string $victim): int
    {
        $winner = self::other($victim);
        $this->score[$winner] += 2;
        $this->stats[$winner]['all_out_points'] += 2;
        $this->stats[$winner]['all_outs_inflicted']++;
        $this->onMat[$victim] = $this->size;
        $this->out[$victim] = 0;

        return 2;
    }

    private function beginRaid(string $raider, bool $dod): void
    {
        $this->firstRaider ??= $raider;
        $this->stats[$raider]['raids']++;
        if ($dod) {
            $this->stats[$raider]['do_or_die_raids']++;
        }
    }

    private function endRaid(string $raider): void
    {
        $this->raiding = self::other($raider);
    }

    private function knockOut(string $side, int $n): void
    {
        $n = min($n, $this->onMat[$side]);
        $this->onMat[$side] -= $n;
        $this->out[$side] += $n;
    }

    /** One player back per point scored, while anyone is out. */
    private function revive(string $side, int $points): void
    {
        $n = min($points, $this->out[$side]);
        $this->out[$side] -= $n;
        $this->onMat[$side] += $n;
    }

    private function player(string $side, string $name, string $stat, int $by = 1): void
    {
        $name = trim($name);
        if ($name === '' || $by === 0) {
            return;
        }
        $this->players[$side][$name][$stat] = ($this->players[$side][$name][$stat] ?? 0) + $by;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $players = [];
        foreach (['home', 'away'] as $side) {
            foreach ($this->players[$side] as $name => $s) {
                $players[] = [
                    'side' => $side,
                    'name' => (string) $name,
                    'raid_points' => $s['raid_points'] ?? 0,
                    'bonus_points' => $s['bonus_points'] ?? 0,
                    'tackle_points' => $s['tackle_points'] ?? 0,
                    'raids' => $s['raids'] ?? 0,
                    'super_raids' => $s['super_raids'] ?? 0,
                    'super_tackles' => $s['super_tackles'] ?? 0,
                    'total' => ($s['raid_points'] ?? 0) + ($s['bonus_points'] ?? 0) + ($s['tackle_points'] ?? 0),
                ];
            }
        }
        usort($players, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'mat' => $this->trackMat ? [
                'size' => $this->size,
                'on' => [$this->onMat['home'], $this->onMat['away']],
            ] : null,
            'raiding' => $this->raiding,
            'do_or_die' => $this->isDoOrDie(),
            'empty_streak' => [$this->emptyStreak['home'], $this->emptyStreak['away']],
            'kabaddi_stats' => $this->stats,
            'kabaddi_players' => $players,
        ];
    }
}
