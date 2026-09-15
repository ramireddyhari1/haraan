<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\LiveMatch;
use App\Models\MatchEvent;
use App\Models\User;
use App\Support\MediaUrl;
use App\Support\SportRules;
use Illuminate\Support\Collection;

/**
 * Insights for every sport that is not cricket, read off the recorded event log.
 *
 * Split in two on purpose:
 *
 *  · THIS class does what every sport shares: replay the log into scoring MOMENTS (the
 *    same replay the score engine does, so a figure here can never disagree with the
 *    board), and from those moments the flow of the match — lead changes, the biggest
 *    lead, the longest run, the comeback — plus who scored what.
 *  · A per-sport BUILDER turns that into the sport's own language: goals and assists,
 *    threes and free throws, raid and tackle points, set closers, deuce points.
 *
 * Adding a sport is one builder and one line in builderFor(). Nothing here is estimated:
 * a stat the scorer never records (possession, rebounds, aces) is absent, not zero.
 */
class SportInsights
{
    /** @return array<string, mixed> */
    public function for(LiveMatch $match): array
    {
        $sport = SportRules::normalise((string) ($match->sport ?: 'football'));
        $family = SportRules::family($sport);
        $state = is_array($match->sport_state) ? $match->sport_state : [];
        $format = is_array($state['format'] ?? null) ? $state['format'] : [];
        $status = strtolower(trim((string) $match->status));

        $events = MatchEvent::query()
            ->where('live_match_id', $match->id)
            ->inOrder()
            ->get();

        $moments = $this->replay($sport, $family, $events, $format);

        $ctx = new InsightContext(
            match: $match,
            sport: $sport,
            family: $family,
            format: $format,
            events: $events,
            moments: $moments,
            live: $status === 'live',
            finished: $status === 'completed',
            homeName: (string) ($match->home ?: 'Home'),
            awayName: (string) ($match->away ?: 'Away'),
        );

        $flow = $this->flow($ctx);
        $ctx->flow = $flow;
        $ctx->contributors = $this->contributors($ctx);

        $built = $this->builderFor($sport)->build($ctx);

        // Photos only through the match's own squad: the name a scorer credited is matched
        // against the squad of THAT side, and only an entry linked to an account has a face.
        // Matching names against the whole user table would put a stranger's photo on a card.
        $players = array_map(function (array $p) use ($match): array {
            $p['avatar'] = $this->avatar($match, (string) $p['side'], (string) $p['name']);

            return $p;
        }, $built['players'] ?? []);

        return [
            'matchId' => (string) $match->id,
            'sport' => $sport,
            'family' => $family,
            'live' => $ctx->live,
            'finished' => $ctx->finished,
            'events' => $events->count(),
            'moments' => count($moments),
            'flow' => $flow,
            'players' => $players,
            'team' => $built['team'] ?? (object) [],
            'reads' => array_values(array_filter($built['reads'] ?? [])),
            'untracked' => $built['untracked'] ?? [],
        ];
    }

    private function builderFor(string $sport): SportInsightBuilder
    {
        return match ($sport) {
            'football' => new FootballInsights(),
            'basketball' => new BasketballInsights(),
            'kabaddi' => new KabaddiInsights(),
            'tennis' => new TennisInsights(),
            // volleyball, table tennis, badminton — and any future rally sport
            default => new RallyInsights(),
        };
    }

    /**
     * The log as scoring moments, in order.
     *
     * Each moment carries the score in the scope that matters for its sport: `seg_*` is
     * the score inside the current segment (a quarter's points, a set's rallies, a set's
     * games in tennis), `total_*` the running match total of the sport's unit.
     *
     * @param  Collection<int, MatchEvent>  $events
     * @param  array<string, mixed>  $format
     * @return array<int, array<string, mixed>>
     */
    private function replay(string $sport, string $family, Collection $events, array $format): array
    {
        return match ($family) {
            SportRules::POINTS => $this->replayPoints($sport, $events),
            SportRules::SETS => $this->replaySets($sport, $events, $format),
            SportRules::TENNIS => $this->replayTennis($events, $format),
            default => $this->replayTally($events),
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function replayTally(Collection $events): array
    {
        $out = [];
        $home = 0;
        $away = 0;
        $half = 0;
        $segHome = 0;
        $segAway = 0;

        foreach ($events as $e) {
            if ($e->kind === MatchEvent::PERIOD) {
                $half++;
                $segHome = 0;
                $segAway = 0;
                continue;
            }
            $own = $e->kind === MatchEvent::OWN_GOAL;
            if ((! $own && $e->kind !== MatchEvent::GOAL) || ! in_array($e->side, ['home', 'away'], true)) {
                continue;
            }
            // An own goal is scored FOR the other side, and by nobody on it.
            $side = $own ? ($e->side === 'home' ? 'away' : 'home') : $e->side;
            if ($side === 'home') {
                $home++;
                $segHome++;
            } else {
                $away++;
                $segAway++;
            }
            $m = $this->moment($e, $side, 1, $own ? '' : (string) $e->player_name, $half, $segHome, $segAway, $home, $away);
            $m['own_goal'] = $own;
            $m['by'] = trim((string) $e->player_name);
            $m['assist'] = $own ? '' : trim((string) $e->related_name);
            $out[] = $m;
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function replayPoints(string $sport, Collection $events): array
    {
        $out = [];
        $home = 0;
        $away = 0;
        $period = 0;
        $segHome = 0;
        $segAway = 0;

        foreach ($events as $e) {
            if ($e->kind === MatchEvent::PERIOD) {
                $period++;
                $segHome = 0;
                $segAway = 0;
                continue;
            }
            if ($e->kind !== MatchEvent::POINT || ! in_array($e->side, ['home', 'away'], true)) {
                continue;
            }
            $value = SportRules::pointValue($sport, $e->detail);
            if ($e->side === 'home') {
                $home += $value;
                $segHome += $value;
            } else {
                $away += $value;
                $segAway += $value;
            }
            $m = $this->moment($e, $e->side, $value, (string) $e->player_name, $period, $segHome, $segAway, $home, $away);
            $m['detail'] = strtolower(trim((string) $e->detail));
            $out[] = $m;
        }

        return $out;
    }

    /**
     * Rally sports, mirroring SportScoreEngine::sets() exactly — including ignoring points
     * after the match is decided — so a set here is the set on the board.
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaySets(string $sport, Collection $events, array $format): array
    {
        $bestOf = (int) ($format['bestOf'] ?? SportRules::defaultBestOf($sport));
        $out = [];
        $index = 0;
        $setHome = 0;
        $setAway = 0;
        $setsHome = 0;
        $setsAway = 0;
        $totalHome = 0;
        $totalAway = 0;

        foreach ($events as $e) {
            if ($e->kind !== MatchEvent::POINT || ! in_array($e->side, ['home', 'away'], true)) {
                continue;
            }
            if ($setsHome > intdiv($bestOf, 2) || $setsAway > intdiv($bestOf, 2)) {
                continue;
            }
            $target = SportRules::setTarget($sport, $index, $format);
            // Deuce territory: both sides one point from the target before this rally.
            $deuce = $setHome >= $target['target'] - 1 && $setAway >= $target['target'] - 1;

            if ($e->side === 'home') {
                $setHome++;
                $totalHome++;
            } else {
                $setAway++;
                $totalAway++;
            }
            $m = $this->moment($e, $e->side, 1, (string) $e->player_name, $index, $setHome, $setAway, $totalHome, $totalAway);
            $m['deuce'] = $deuce;
            $m['sets_home'] = $setsHome;
            $m['sets_away'] = $setsAway;

            if (SportRules::setIsWon($setHome, $setAway, $target)) {
                $m['closes'] = 'set';
                $setHome > $setAway ? $setsHome++ : $setsAway++;
                $m['sets_home'] = $setsHome;
                $m['sets_away'] = $setsAway;
                $setHome = 0;
                $setAway = 0;
                $index++;
            }
            $out[] = $m;
        }

        return $out;
    }

    /**
     * Tennis, mirroring SportScoreEngine::tennis(). `seg_*` is GAMES in the set, because a
     * lead in tennis is a lead in games; the point ladder is kept on the moment separately.
     *
     * @return array<int, array<string, mixed>>
     */
    private function replayTennis(Collection $events, array $format): array
    {
        $gamesToSet = (int) ($format['gamesTo'] ?? 6);
        $out = [];
        $pHome = 0;
        $pAway = 0;
        $gHome = 0;
        $gAway = 0;
        $setsHome = 0;
        $setsAway = 0;
        $index = 0;
        $totalHome = 0;
        $totalAway = 0;

        foreach ($events as $e) {
            if ($e->kind !== MatchEvent::POINT || ! in_array($e->side, ['home', 'away'], true)) {
                continue;
            }
            $deuce = $pHome >= 3 && $pAway >= 3;
            if ($e->side === 'home') {
                $pHome++;
                $totalHome++;
            } else {
                $pAway++;
                $totalAway++;
            }

            $closes = null;
            if (max($pHome, $pAway) >= 4 && abs($pHome - $pAway) >= 2) {
                $pHome > $pAway ? $gHome++ : $gAway++;
                $pHome = 0;
                $pAway = 0;
                $closes = 'game';
            }

            $m = $this->moment($e, $e->side, 1, (string) $e->player_name, $index, $gHome, $gAway, $totalHome, $totalAway);
            $m['deuce'] = $deuce;
            $m['closes'] = $closes;

            if ($closes === 'game' && max($gHome, $gAway) >= $gamesToSet && abs($gHome - $gAway) >= 2) {
                $m['closes'] = 'set';
                $gHome > $gAway ? $setsHome++ : $setsAway++;
                $gHome = 0;
                $gAway = 0;
                $index++;
            }
            $m['sets_home'] = $setsHome;
            $m['sets_away'] = $setsAway;
            $out[] = $m;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function moment(MatchEvent $e, string $side, int $value, string $player, int $segment, int $segHome, int $segAway, int $totalHome, int $totalAway): array
    {
        return [
            'seq' => (int) $e->sequence,
            'minute' => $e->minute,
            'side' => $side,
            'value' => $value,
            'player' => trim($player),
            'segment' => $segment,
            'seg_home' => $segHome,
            'seg_away' => $segAway,
            'total_home' => $totalHome,
            'total_away' => $totalAway,
            'closes' => null,
            'deuce' => false,
        ];
    }

    /**
     * How the match moved: every figure is a count over the moments above.
     *
     * The LEAD is measured where a lead means something — the running total for a goals or
     * points sport, the rally score inside each set for volleyball/TT/badminton (a 24-22 set
     * lead is a lead; a match-long rally total is not), and games inside the set for tennis.
     *
     * @return array<string, mixed>
     */
    private function flow(InsightContext $ctx): array
    {
        $moments = $ctx->moments;
        $bySegment = in_array($ctx->family, [SportRules::SETS, SportRules::TENNIS], true);
        $tennis = $ctx->family === SportRules::TENNIS;

        $leadChanges = 0;
        $ties = 0;
        $leader = null;     // 'home' | 'away' | null (level)
        $lastSeg = -1;
        $biggest = null;
        $series = [];

        foreach ($moments as $i => $m) {
            if ($bySegment && $m['segment'] !== $lastSeg) {
                $leader = null;
                $lastSeg = $m['segment'];
            }
            // Tennis: the lead only moves when a game is won.
            if ($tennis && $m['closes'] === null) {
                continue;
            }
            [$h, $a] = $bySegment ? [$m['seg_home'], $m['seg_away']] : [$m['total_home'], $m['total_away']];
            $now = $h === $a ? null : ($h > $a ? 'home' : 'away');
            if ($now !== null && $leader !== null && $now !== $leader) {
                $leadChanges++;
            }
            if ($now === null && ($h + $a) > 0) {
                $ties++;
            }
            if ($now !== null) {
                $leader = $now;
            }

            $margin = abs($h - $a);
            if (! $tennis && ($biggest === null || $margin > $biggest['margin'])) {
                $biggest = [
                    'side' => $now, 'margin' => $margin,
                    'home' => $h, 'away' => $a, 'segment' => $m['segment'],
                    'minute' => $m['minute'],
                ];
            }
            $series[] = ['d' => $h - $a, 's' => $m['segment']];
        }
        if ($biggest !== null && $biggest['margin'] === 0) {
            $biggest = null;
        }

        // Runs: unanswered scoring by one side, over the sport's own unit.
        $runs = [];
        $cur = null;
        foreach ($moments as $m) {
            if ($cur !== null && $cur['side'] === $m['side']) {
                $cur['value'] += $m['value'];
                $cur['count']++;
                $cur['end_home'] = $m[$bySegment ? 'seg_home' : 'total_home'];
                $cur['end_away'] = $m[$bySegment ? 'seg_away' : 'total_away'];
                continue;
            }
            if ($cur !== null) {
                $runs[] = $cur;
            }
            $cur = [
                'side' => $m['side'], 'value' => $m['value'], 'count' => 1,
                'segment' => $m['segment'],
                'end_home' => $m[$bySegment ? 'seg_home' : 'total_home'],
                'end_away' => $m[$bySegment ? 'seg_away' : 'total_away'],
            ];
        }
        if ($cur !== null) {
            $runs[] = $cur;
        }
        $longest = null;
        foreach ($runs as $r) {
            if ($longest === null || $r['value'] > $longest['value']) {
                $longest = $r;
            }
        }
        $current = $runs === [] ? null : end($runs);

        return [
            'lead_changes' => $leadChanges,
            'ties' => $ties,
            'biggest_lead' => $biggest,
            // A "run" of one is just a score.
            'longest_run' => $longest !== null && $longest['count'] >= 2 ? $longest : null,
            'current_run' => $ctx->live && $current && $current['count'] >= 2 ? $current : null,
            'series' => $this->sample($series, 120),
            'segments' => $this->segments($ctx),
            'comeback' => $this->comeback($ctx),
            'total_home' => array_sum(array_map(fn ($m) => $m['side'] === 'home' ? $m['value'] : 0, $moments)),
            'total_away' => array_sum(array_map(fn ($m) => $m['side'] === 'away' ? $m['value'] : 0, $moments)),
        ];
    }

    /**
     * Per-segment score: a quarter's points, a set's rallies, a set's games — each with
     * its winner when it is over.
     *
     * @return array<int, array<string, mixed>>
     */
    private function segments(InsightContext $ctx): array
    {
        $out = [];
        foreach ($ctx->moments as $m) {
            $k = $m['segment'];
            $out[$k] = [
                'index' => $k,
                'label' => $this->segmentLabel($ctx, $k),
                'home' => $m['seg_home'],
                'away' => $m['seg_away'],
                'closed' => in_array($m['closes'], ['set'], true),
            ];
        }

        // A football match nobody marked half-time on is one stretch, not a "1st half".
        if ($ctx->family === SportRules::TALLY && count($out) < 2) {
            return [];
        }

        return array_values($out);
    }

    private function segmentLabel(InsightContext $ctx, int $index): string
    {
        return match ($ctx->family) {
            SportRules::POINTS => $ctx->sport === 'basketball' ? 'Q'.($index + 1) : ($index === 0 ? '1st half' : '2nd half'),
            SportRules::SETS => SportRules::setNoun($ctx->sport).' '.($index + 1),
            SportRules::TENNIS => 'Set '.($index + 1),
            default => $index === 0 ? '1st half' : '2nd half',
        };
    }

    /**
     * The biggest deficit the side now ahead (or the winner) climbed out of. Null unless it
     * is a real one — trailing by a single basket is not a comeback.
     *
     * @return array<string, mixed>|null
     */
    private function comeback(InsightContext $ctx): ?array
    {
        $moments = $ctx->moments;
        if ($moments === []) {
            return null;
        }
        $last = end($moments);
        $setsFamily = in_array($ctx->family, [SportRules::SETS, SportRules::TENNIS], true);

        [$fh, $fa] = $setsFamily
            ? [$last['sets_home'] ?? 0, $last['sets_away'] ?? 0]
            : [$last['total_home'], $last['total_away']];
        if ($fh === $fa) {
            return null;
        }
        $side = $fh > $fa ? 'home' : 'away';

        $worst = 0;
        $at = null;
        foreach ($moments as $m) {
            [$h, $a] = $setsFamily
                ? [$m['sets_home'] ?? 0, $m['sets_away'] ?? 0]
                : [$m['total_home'], $m['total_away']];
            $deficit = $side === 'home' ? $a - $h : $h - $a;
            if ($deficit > $worst) {
                $worst = $deficit;
                $at = ['home' => $h, 'away' => $a];
            }
        }

        $threshold = match ($ctx->sport) {
            'football' => 1,
            'basketball' => 8,
            'kabaddi' => 5,
            default => 1,   // a set down, in a sets sport
        };

        return $worst >= $threshold ? [
            'side' => $side,
            'deficit' => $worst,
            'unit' => $setsFamily ? 'sets' : 'score',
            'from' => $at,
            'completed' => $ctx->finished,
        ] : null;
    }

    /**
     * What each credited player did, in the moments every sport shares. Uncredited scoring
     * is counted per side so a share is always "of the team's total", never inflated by
     * pretending the unnamed points did not happen.
     *
     * @return array<string, mixed>
     */
    private function contributors(InsightContext $ctx): array
    {
        $players = [];
        $team = ['home' => 0, 'away' => 0];
        $unattributed = ['home' => 0, 'away' => 0];
        $prevLeader = null;
        $bySegment = in_array($ctx->family, [SportRules::SETS, SportRules::TENNIS], true);
        $lastSeg = -1;
        $runKey = null;
        $runLen = 0;

        foreach ($ctx->moments as $m) {
            $team[$m['side']] += $m['value'];
            if ($bySegment && $m['segment'] !== $lastSeg) {
                $prevLeader = null;
                $lastSeg = $m['segment'];
            }
            [$h, $a] = $bySegment ? [$m['seg_home'], $m['seg_away']] : [$m['total_home'], $m['total_away']];
            $now = $h === $a ? null : ($h > $a ? 'home' : 'away');
            $tookLead = $now === $m['side'] && $prevLeader !== $m['side'];
            // A set-closing rally resets the set score — whoever closed it led it.
            if ($m['closes'] === 'set' && $ctx->family === SportRules::SETS) {
                $tookLead = false;
            }
            $prevLeader = $now ?? $prevLeader;

            if ($m['player'] === '') {
                $unattributed[$m['side']] += $m['value'];
                $runKey = null;
                $runLen = 0;
                continue;
            }
            $key = $m['side'].'|'.mb_strtolower($m['player']);
            $players[$key] ??= [
                'side' => $m['side'], 'name' => $m['player'],
                'value' => 0, 'count' => 0, 'closers' => 0, 'deuce' => 0,
                'lead_takers' => 0, 'best_run' => 0, 'segments' => [],
            ];
            $p = &$players[$key];
            $p['value'] += $m['value'];
            $p['count']++;
            $p['segments'][$m['segment']] = ($p['segments'][$m['segment']] ?? 0) + $m['value'];
            if (in_array($m['closes'], ['set', 'game'], true)) {
                $p['closers']++;
            }
            if ($m['deuce']) {
                $p['deuce']++;
            }
            if ($tookLead) {
                $p['lead_takers']++;
            }
            // A player's own run: consecutive moments of the MATCH that were all theirs.
            $runLen = $runKey === $key ? $runLen + 1 : 1;
            $runKey = $key;
            $p['best_run'] = max($p['best_run'], $runLen);
            unset($p);
        }

        return ['players' => $players, 'team' => $team, 'unattributed' => $unattributed];
    }

    /** @param array<int, mixed> $rows */
    private function sample(array $rows, int $max): array
    {
        $n = count($rows);
        if ($n <= $max) {
            return $rows;
        }
        $out = [];
        for ($i = 0; $i < $max - 1; $i++) {
            $out[] = $rows[(int) floor($i * ($n - 1) / ($max - 1))];
        }
        $out[] = $rows[$n - 1];

        return $out;
    }

    /** @var array<string, string|null> */
    private array $avatarCache = [];

    private function avatar(LiveMatch $match, string $side, string $name): ?string
    {
        $squad = ($side === 'home' ? $match->home_squad : $match->away_squad) ?? [];
        $needle = mb_strtolower(trim($name));
        foreach ($squad as $entry) {
            if (! is_array($entry) || mb_strtolower(trim((string) ($entry['name'] ?? ''))) !== $needle) {
                continue;
            }
            $id = trim((string) ($entry['id'] ?? ''));
            if ($id === '' || strtolower($id) === 'null') {
                return null;
            }
            if (! array_key_exists($id, $this->avatarCache)) {
                $raw = User::query()->where('player_id', $id)->value('avatar');
                $this->avatarCache[$id] = MediaUrl::resolve($raw !== null ? (string) $raw : null);
            }

            return $this->avatarCache[$id];
        }

        return null;
    }
}
