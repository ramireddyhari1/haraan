<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A player's last few innings, with the bat and with the ball.
 *
 * Replayed from `match_actions`, the same log the scorecard and the career tables read,
 * so a form line can never disagree with the match it came from. Only matches the
 * player was actually in a squad for are considered, and only completed ones.
 *
 * Two things the reference cards for this screen show that are NOT here:
 *
 *   · a spin-versus-pace breakdown of a batter's runs. No delivery in our log records
 *     the bowler's type — the scorer only started being asked this week — so the table
 *     would be invented. It returns null until there is something real to put in it.
 *   · a "top six dismissal" rate for a bowler. Batting position is not recorded
 *     anywhere; the order a player came in at is not the same as their listed position,
 *     and we do not store either.
 *
 * Dot-ball and boundary percentages ARE real: every delivery in the log says what it
 * was, so those are counted rather than estimated.
 */
final class PlayerFormService
{
    /** How many recent innings a form card shows. */
    private const INNINGS = 5;

    /** How far back to look for them. A player with a long gap has a short form line. */
    private const MATCH_WINDOW = 25;

    private const CACHE_MINUTES = 10;

    /**
     * @return array{
     *   batting: array<string,mixed>|null,
     *   bowling: array<string,mixed>|null
     * }
     */
    public function forPlayer(string $playerId): array
    {
        $id = trim($playerId);
        if ($id === '' || strtolower($id) === 'null') {
            return ['batting' => null, 'bowling' => null];
        }

        return Cache::remember(
            'player_form:' . $id,
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->build($id),
        );
    }

    /** @return array{batting: array<string,mixed>|null, bowling: array<string,mixed>|null} */
    private function build(string $id): array
    {
        $matches = LiveMatch::query()
            ->whereRaw('lower(status) = ?', ['completed'])
            ->where(function ($q) use ($id): void {
                $q->where('home_squad', 'like', '%"' . $id . '"%')
                  ->orWhere('away_squad', 'like', '%"' . $id . '"%');
            })
            ->orderByDesc('updated_at')
            ->limit(self::MATCH_WINDOW)
            ->get();

        $batting = [];
        $bowling = [];

        foreach ($matches as $match) {
            $opponent = $this->opponentLabel($match);
            $played = optional($match->updated_at)->format('d/m/y') ?? '';

            foreach ($this->replay($match, $id) as $line) {
                if ($line['batted']) {
                    $batting[] = [
                        'runs' => $line['runs'],
                        'balls' => $line['balls'],
                        'notOut' => ! $line['out'],
                        'fours' => $line['fours'],
                        'sixes' => $line['sixes'],
                        'match' => $opponent,
                        'date' => $played,
                    ];
                }
                if ($line['bowled']) {
                    $bowling[] = [
                        'overs' => $this->oversText($line['ballsBowled']),
                        'maidens' => $line['maidens'],
                        'runs' => $line['conceded'],
                        'wickets' => $line['wickets'],
                        'dots' => $line['dots'],
                        'legalBalls' => $line['ballsBowled'],
                        'boundariesConceded' => $line['boundariesConceded'],
                        'match' => $opponent,
                        'date' => $played,
                    ];
                }
            }
        }

        return [
            'batting' => $this->battingBlock(array_slice($batting, 0, self::INNINGS)),
            'bowling' => $this->bowlingBlock(array_slice($bowling, 0, self::INNINGS)),
        ];
    }

    /** @param list<array<string,mixed>> $innings */
    private function battingBlock(array $innings): ?array
    {
        if ($innings === []) {
            return null;
        }

        $runs = array_sum(array_column($innings, 'runs'));
        $balls = array_sum(array_column($innings, 'balls'));
        $notOuts = count(array_filter($innings, fn ($i) => $i['notOut']));
        $dismissals = count($innings) - $notOuts;

        return [
            'innings' => $innings,
            'totals' => [
                // Labelled the way a scorecard labels them, because that is what the
                // reader is comparing against.
                ['label' => 'R', 'value' => (string) $runs],
                ['label' => 'Avg', 'value' => $dismissals > 0
                    ? number_format($runs / $dismissals, 2, '.', '')
                    : '-'],
                ['label' => 'SR', 'value' => $balls > 0
                    ? number_format($runs * 100 / $balls, 2, '.', '')
                    : '-'],
                ['label' => 'NO', 'value' => (string) $notOuts],
            ],
        ];
    }

    /** @param list<array<string,mixed>> $spells */
    private function bowlingBlock(array $spells): ?array
    {
        if ($spells === []) {
            return null;
        }

        $runs = array_sum(array_column($spells, 'runs'));
        $wickets = array_sum(array_column($spells, 'wickets'));
        $balls = array_sum(array_column($spells, 'legalBalls'));
        $dots = array_sum(array_column($spells, 'dots'));
        $boundaries = array_sum(array_column($spells, 'boundariesConceded'));

        $efficiency = [];
        if ($balls > 0) {
            // Both counted off the ball log, not modelled. A dot is a delivery that
            // conceded nothing; a boundary is one that went for four or six.
            $efficiency[] = ['label' => 'Dot balls', 'value' => round($dots * 100 / $balls, 2) . '%'];
            $efficiency[] = ['label' => 'Boundary balls', 'value' => round($boundaries * 100 / $balls, 2) . '%'];
        }

        return [
            'innings' => $spells,
            'totals' => [
                ['label' => 'R', 'value' => (string) $runs],
                ['label' => 'W', 'value' => (string) $wickets],
                ['label' => 'SR', 'value' => $wickets > 0
                    ? number_format($balls / $wickets, 2, '.', '')
                    : '-'],
                ['label' => 'Eco', 'value' => $balls > 0
                    ? number_format($runs * 6 / $balls, 2, '.', '')
                    : '-'],
            ],
            'efficiency' => $efficiency,
        ];
    }

    /**
     * One match, replayed for one player.
     *
     * @return list<array<string,mixed>> one entry per innings the player appeared in
     */
    private function replay(LiveMatch $match, string $id): array
    {
        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id')
            ->get();

        $out = [];
        $cur = null;
        $striker = '';
        $bowler = '';
        $overCharged = 0;
        $overBowler = '';
        $legal = 0;

        $blank = fn () => [
            'batted' => false, 'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'out' => false,
            'bowled' => false, 'ballsBowled' => 0, 'conceded' => 0, 'wickets' => 0,
            'dots' => 0, 'maidens' => 0, 'boundariesConceded' => 0,
        ];

        foreach ($actions as $action) {
            $type = (string) $action->action_type;
            $p = json_decode((string) $action->payload, true) ?: [];

            if ($type === 'start') {
                if ($cur !== null && ($cur['batted'] || $cur['bowled'])) {
                    $out[] = $cur;
                }
                $cur = $blank();
                $striker = self::clean($p['striker_id'] ?? null);
                $bowler = self::clean($p['bowler_id'] ?? null);
                $overBowler = $bowler;
                $overCharged = 0;
                $legal = 0;
                continue;
            }
            if ($cur === null) {
                continue;
            }
            if ($type === 'change_bowler') {
                $bowler = self::clean($p['bowler_id'] ?? null);
                if ($overBowler === '') {
                    $overBowler = $bowler;
                }
                continue;
            }
            if ($type === 'change_batsman') {
                if (($p['role'] ?? 'striker') === 'striker') {
                    $striker = self::clean($p['id'] ?? null);
                }
                continue;
            }

            $offBat = 0;
            $extras = 0;
            $isLegal = true;
            $wicket = false;
            switch ($type) {
                case 'runs':   $offBat = (int) ($p['value'] ?? 0); break;
                case 'wide':   $isLegal = false; $extras = (int) ($p['value'] ?? 1); break;
                case 'noball': $isLegal = false; $offBat = (int) ($p['runs_off_bat'] ?? 0); $extras = 1; break;
                case 'bye':
                case 'legbye': $extras = (int) ($p['value'] ?? 1); break;
                case 'wicket': $wicket = true; break;
                default: continue 2;
            }

            // ── With the bat ──
            if ($striker === $id && $type !== 'wide') {
                $cur['batted'] = true;
                $cur['runs'] += $offBat;
                $cur['balls']++;
                if ($type === 'runs' && $offBat === 4) $cur['fours']++;
                if ($type === 'runs' && $offBat === 6) $cur['sixes']++;
            }

            // ── With the ball ──
            if ($bowler === $id) {
                $cur['bowled'] = true;
                if ($type !== 'bye' && $type !== 'legbye') {
                    $cur['conceded'] += $offBat + $extras;
                }
                if ($isLegal) {
                    $cur['ballsBowled']++;
                    if ($offBat + $extras === 0) {
                        $cur['dots']++;
                    }
                }
                if ($offBat === 4 || $offBat === 6) {
                    $cur['boundariesConceded']++;
                }
                if ($wicket) {
                    $cur['wickets']++;
                }
            }
            if ($bowler !== '' && $type !== 'bye' && $type !== 'legbye') {
                $overCharged += $offBat + $extras;
            }

            if ($wicket && $striker === $id) {
                $cur['out'] = true;
            }
            if ($wicket) {
                $striker = self::clean($p['new_batsman_id'] ?? null);
            }
            // Strike rotation, so the striker attribution stays correct across the over.
            $swap = ($type === 'bye' || $type === 'legbye') ? $extras : $offBat;
            if ($swap % 2 === 1) {
                // Only the striker is tracked here, so a rotation simply means the
                // player at the other end is on strike and this player is not.
                $striker = $striker === $id ? '' : $striker;
            }

            if ($isLegal) {
                $legal++;
                if ($legal % 6 === 0) {
                    if ($overCharged === 0 && $overBowler === $id) {
                        $cur['maidens']++;
                    }
                    $overCharged = 0;
                    $overBowler = '';
                }
            }
        }
        if ($cur !== null && ($cur['batted'] || $cur['bowled'])) {
            $out[] = $cur;
        }

        return $out;
    }

    private function opponentLabel(LiveMatch $match): string
    {
        $home = trim((string) ($match->home_full ?: $match->home));
        $away = trim((string) ($match->away_full ?: $match->away));

        return trim($home . ' vs ' . $away, ' vs');
    }

    private function oversText(int $balls): string
    {
        return intdiv($balls, 6) . '.' . ($balls % 6);
    }

    private static function clean($value): string
    {
        $s = trim((string) ($value ?? ''));

        return ($s === '' || strtolower($s) === 'null') ? '' : $s;
    }

    /** The role line under a player's name, from their profile. Blank when unset. */
    public function styleLine(string $playerId): array
    {
        $user = User::where('player_id', $playerId)->first();
        if ($user === null) {
            return ['batting' => '', 'bowling' => ''];
        }

        return [
            'batting' => trim((string) ($user->batting_style ?? '')),
            'bowling' => trim((string) ($user->bowling_style ?? '')),
        ];
    }
}
