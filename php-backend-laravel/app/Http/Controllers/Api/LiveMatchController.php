<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public, read-only live-match detail for the Android app's Match Details screen.
 * Maps the loosely-structured LiveMatch record (managed via the web Control Room)
 * onto the flat shape the app's MatchUiState expects. Derived/ambiguous fields
 * (target, RRR, run equation) are omitted when the source can't supply them.
 */
class LiveMatchController extends Controller
{
    /**
     * Public list for the GameHub live-scores feed. Live matches first, then the
     * most recently updated. Returns a compact row shape the app list renders.
     * GET /api/live-matches
     */
    public function index(Request $request): JsonResponse
    {
        // Optional auth: signed-in users get their district's LOCAL matches plus
        // FEATURED; guests get FEATURED only. ?scope=local|featured|all narrows it.
        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;
        $scope = $request->query('scope');
        $scope = is_string($scope) ? strtolower($scope) : null;

        $matches = LiveMatch::query()
            ->visibleTo($viewer, $scope)
            ->orderByRaw("CASE WHEN LOWER(status) = 'live' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $data = $matches->map(function (LiveMatch $m): array {
            // Which side is batting (latest over's tag)? Drives score/overs attribution and
            // lets the app put the batting side on top of the card.
            $overSummary = is_array($m->over_summary) ? $m->over_summary : [];
            $battingTeam = 1;
            for ($i = count($overSummary) - 1; $i >= 0; $i--) {
                $tag = $overSummary[$i]['batting'] ?? null;
                if ($tag !== null && $tag !== '') {
                    $battingTeam = ($tag === $m->away || $tag === 'away') ? 2 : 1;
                    break;
                }
            }
            $overs = (string) ($m->overs ?? '');
            $scoreText = (string) ($m->score_text ?: '');
            $homeScore = ($battingTeam === 1 && $scoreText !== '') ? $scoreText : (string) ($m->home_score ?? 0);
            $awayScore = ($battingTeam === 2 && $scoreText !== '') ? $scoreText : (string) ($m->away_score ?? 0);
            return [
                'id'          => (string) $m->id,
                'team1'       => (string) $m->home,
                'team2'       => (string) $m->away,
                'team1Logo'   => $this->absoluteLogo($m->home_logo),
                'team2Logo'   => $this->absoluteLogo($m->away_logo),
                'team1Emblem' => (string) ($m->home_emblem ?? ''),
                'team2Emblem' => (string) ($m->away_emblem ?? ''),
                'score1'      => $homeScore,
                'score2'      => $awayScore,
                'overs1'      => $battingTeam === 2 ? '' : $overs,
                'overs2'      => $battingTeam === 2 ? $overs : '',
                'battingTeam' => $battingTeam,
                'status'      => (string) ($m->status ?? ''),
                'venue'       => (string) ($m->venue ?? ''),
                'competition' => (string) ($m->competition ?? ''),
                'isLive'      => strtolower((string) $m->status) === 'live',
                'visibility'  => (string) ($m->visibility ?? LiveMatch::VIS_LOCAL),
                'district'    => (string) ($m->district ?? ''),
                'locality'    => (string) ($m->locality ?? ''),
            ];
        })->all();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $match = LiveMatch::find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        // A LOCAL or private match must not be reachable by guessing its id.
        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;
        if (!$match->isVisibleTo($viewer)) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        return response()->json($this->matchDetail($match, $viewer));
    }

    /**
     * Detail lookup by private-match share code. The code itself is the grant —
     * anyone holding it (even a guest) may watch. Public matches are not exposed
     * here; use the id route for those.
     * GET /api/live-matches/code/{code}
     */
    public function showByCode(Request $request, string $code): JsonResponse
    {
        $match = LiveMatch::byJoinCode($code)->where('is_private', true)->first();
        if ($match === null) {
            return response()->json(['error' => 'No match found for that code.'], 404);
        }

        // The creator may open their own private match by code; identify them if a token
        // happens to ride along so they still get the scorer entry.
        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;

        return response()->json($this->matchDetail($match, $viewer));
    }

    /** Build the flat detail payload the app's Match Details screen expects. */
    private function matchDetail(LiveMatch $match, ?User $viewer = null): array
    {
        $overSummary = is_array($match->over_summary) ? $match->over_summary : [];
        $batters = is_array($match->batters) ? $match->batters : [];
        $bowler = is_array($match->bowler) ? $match->bowler : [];
        $probability = is_array($match->probability) ? $match->probability : [];

        // Which side is batting? Use the most recent over's `batting` tag if present,
        // matching the home/away short name; default to home (team1).
        $battingTeam = 1;
        for ($i = count($overSummary) - 1; $i >= 0; $i--) {
            $tag = $overSummary[$i]['batting'] ?? null;
            if ($tag !== null && $tag !== '') {
                $battingTeam = ($tag === $match->away || $tag === 'away') ? 2 : 1;
                break;
            }
        }

        $homeScoreText = $match->score_text ?: ((string) ($match->home_score ?? 0));
        $awayScoreText = (string) ($match->away_score ?? 0);
        // The hero shows the batting side as the headline score.
        $score = $battingTeam === 2 ? $awayScoreText : $homeScoreText;
        $opponentScore = $battingTeam === 2 ? $homeScoreText : $awayScoreText;

        // Crease batters: [0] = striker, [1] = non-striker.
        $striker = $batters[0] ?? null;
        $nonStriker = $batters[1] ?? null; // detail payload built below

        // This over + last-3 momentum, derived from over_summary (newest last).
        $thisOver = [];
        if (!empty($overSummary)) {
            $last = $overSummary[count($overSummary) - 1];
            $thisOver = array_values(array_filter(
                array_map('strval', $last['balls'] ?? []),
                fn ($b) => $b !== ''
            ));
        }
        $recentOvers = [];
        foreach (array_slice($overSummary, -3) as $idx => $over) {
            $balls = array_map('strval', $over['balls'] ?? []);
            $recentOvers[] = [
                'label' => (string) ($over['over'] ?? ($over['label'] ?? '')),
                'runs' => (int) ($over['runs'] ?? $this->sumBalls($balls)),
                'balls' => $balls,
            ];
        }

        // Only the match creator may score it — this gates the "Score" button in the
        // app's Match Details header. Absent/false for everyone else (and guests).
        $canScore = $viewer !== null && (int) $match->user_id === (int) $viewer->id;

        // Replay once, then derive the live partnership + last wicket from the current innings.
        $cards = $this->buildInningsCards($match);
        $liveCard = !empty($cards) ? end($cards) : null;
        $partnership = null;
        $lastWicket = null;
        if ($liveCard !== null) {
            $partnership = [
                'runs'  => (int) ($liveCard['partnershipRuns'] ?? 0),
                'balls' => (int) ($liveCard['partnershipBalls'] ?? 0),
            ];
            if (!empty($liveCard['fow'])) {
                $lw = end($liveCard['fow']);
                $name = (string) ($lw['batter'] ?? '');
                $stat = null;
                foreach (($liveCard['batters'] ?? []) as $bb) {
                    if ((string) ($bb['name'] ?? '') === $name) { $stat = $bb; break; }
                }
                if ($name !== '') {
                    $lastWicket = [
                        'name'  => $name,
                        'runs'  => (int) ($stat['runs'] ?? 0),
                        'balls' => (int) ($stat['balls'] ?? 0),
                    ];
                }
            }
        }

        return [
            'creatorId' => (int) $match->user_id,
            'canScore' => $canScore,
            'isPrivate' => (bool) $match->is_private,
            'joinCode' => (string) ($match->join_code ?? ''),
            'homeSquad' => $match->home_squad ?: [],
            'awaySquad' => $match->away_squad ?: [],
            'team1' => $match->home,
            'team1Full' => $match->home_full ?: $match->home,
            'team1Logo' => $this->absoluteLogo($match->home_logo),
            'team1Emblem' => (string) ($match->home_emblem ?? ''),
            'team2' => $match->away,
            'team2Full' => $match->away_full ?: $match->away,
            'team2Logo' => $this->absoluteLogo($match->away_logo),
            'team2Emblem' => (string) ($match->away_emblem ?? ''),
            'score' => $score,
            'overs' => (string) ($match->overs ?? ''),
            'crr' => (string) ($match->crr ?? ''),
            'status' => $match->score_text ?: (string) ($match->status ?? ''),
            'isLive' => strtolower((string) $match->status) === 'live',
            'formatLabel' => (string) ($match->competition ?? ''),
            'venue' => (string) ($match->venue ?? ''),
            'inningsLabel' => (string) ($match->status ?? ''),
            'battingTeam' => $battingTeam,
            // How many innings have begun (one 'start' action each) — lets the scorer know
            // whether the 2nd innings is already underway after a reload.
            'innings' => max(1, (int) DB::table('match_actions')
                ->where('match_id', $match->id)
                ->where('action_type', 'start')
                ->count()),
            'opponentScore' => $opponentScore,
            'winProbTeam1' => isset($probability['home']) ? round(((float) $probability['home']) / 100.0, 4) : 0.5,
            'striker' => $striker['name'] ?? '',
            'strikerStats' => $striker ? "{$striker['runs']} ({$striker['balls']})" : '',
            'nonStriker' => $nonStriker['name'] ?? '',
            'nonStrikerStats' => $nonStriker ? "{$nonStriker['runs']} ({$nonStriker['balls']})" : '',
            'bowler' => $bowler['name'] ?? '',
            'bowlerStats' => $bowler ? trim(($bowler['figures'] ?? '') . ' (' . ($bowler['overs'] ?? '') . ')') : '',
            'thisOver' => $thisOver,
            'recentOvers' => $recentOvers,
            'toss' => (string) ($match->decision ?? ''),
            // Full per-innings scorecards, replayed from the ball-by-ball log so the
            // scorecard tab shows EVERY innings, batter and bowler — not just the live two.
            'inningsCards' => $cards,
            // Ball-by-ball commentary feed (newest first), replayed from the log.
            'commentary' => $this->buildCommentary($match),
            // Current partnership + last wicket, derived from the live innings — real
            // values (no "0(0)" / "N/A" placeholders) or null when there's nothing yet.
            'partnership' => $partnership,
            'lastWicket' => $lastWicket,
        ];
    }

    /**
     * Replay the action log into a ball-by-ball commentary feed (newest delivery first).
     * Each entry carries the over marker, a human line, and the outcome flags the app
     * uses to colour the ball bubble.
     */
    private function buildCommentary(LiveMatch $match): array
    {
        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id', 'asc')
            ->get();

        $feed = [];
        $inningsNo = 0;
        $battingName = '';
        $striker = '';
        $nonStriker = '';
        $bowler = '';
        $legalBalls = 0;

        foreach ($actions as $act) {
            $type = (string) $act->action_type;
            $p = json_decode($act->payload, true) ?: [];

            if ($type === 'start') {
                $inningsNo++;
                $bt = (int) ($p['batting_team'] ?? 1);
                $battingName = (string) ($bt === 2 ? ($match->away_full ?: $match->away) : ($match->home_full ?: $match->home));
                $striker = $this->resolvePlayerName($match, $p['striker_id'] ?? null);
                $nonStriker = $this->resolvePlayerName($match, $p['non_striker_id'] ?? null);
                $bowler = $this->resolvePlayerName($match, $p['bowler_id'] ?? null);
                $legalBalls = 0;
                $feed[] = ['innings' => $inningsNo, 'over' => '', 'kind' => 'header',
                    'text' => "Innings $inningsNo — $battingName", 'label' => '', 'runs' => 0,
                    'wicket' => false, 'boundary' => false, 'battingName' => $battingName];
                continue;
            }
            if ($type === 'change_bowler') {
                $bowler = $this->resolvePlayerName($match, $p['bowler_id'] ?? null);
                continue;
            }
            if ($type === 'change_batsman') {
                $name = $this->resolvePlayerName($match, $p['id'] ?? null);
                if (($p['role'] ?? 'striker') === 'striker') $striker = $name; else $nonStriker = $name;
                continue;
            }

            $isLegal = true; $runsOffBat = 0; $extras = 0; $wicket = false;
            $label = ''; $outcome = '';
            switch ($type) {
                case 'runs':
                    $runsOffBat = (int) ($p['value'] ?? 0);
                    $label = (string) $runsOffBat;
                    $outcome = match ($runsOffBat) { 0 => 'no run', 1 => '1 run', 4 => 'FOUR', 6 => 'SIX', default => "$runsOffBat runs" };
                    break;
                case 'wide':   $isLegal = false; $extras = (int) ($p['value'] ?? 1); $label = 'wd'; $outcome = 'wide' . ($extras > 1 ? " +" . ($extras - 1) : ''); break;
                case 'noball': $isLegal = false; $runsOffBat = (int) ($p['runs_off_bat'] ?? 0); $extras = 1; $label = 'nb'; $outcome = 'no ball' . ($runsOffBat > 0 ? ", $runsOffBat run(s)" : ''); break;
                case 'bye':    $extras = (int) ($p['value'] ?? 1); $label = 'b'; $outcome = "$extras bye" . ($extras > 1 ? 's' : ''); break;
                case 'legbye': $extras = (int) ($p['value'] ?? 1); $label = 'lb'; $outcome = "$extras leg bye" . ($extras > 1 ? 's' : ''); break;
                case 'wicket': $wicket = true; $label = 'W'; $outcome = 'OUT! ' . $this->dismissalText($p, $bowler); break;
                default: continue 2;
            }

            $overNo = intdiv($legalBalls, 6);
            $ballInOver = ($legalBalls % 6) + 1;
            $overMark = "$overNo.$ballInOver";
            $outBatter = $striker;

            $feed[] = [
                'innings'  => $inningsNo,
                'over'     => $overMark,
                'kind'     => 'ball',
                'text'     => trim(($bowler !== '' ? "$bowler to " : '') . ($striker !== '' ? "$striker, " : '') . $outcome),
                'label'    => $label,
                'runs'     => $runsOffBat + $extras,
                'wicket'   => $wicket,
                'boundary' => ($type === 'runs' && ($runsOffBat === 4 || $runsOffBat === 6)),
                'battingName' => $battingName,
            ];

            if ($isLegal) {
                $legalBalls++;
            }

            // Strike rotation (so the next line names the right batter).
            $runsToSwap = ($type === 'bye' || $type === 'legbye') ? $extras : $runsOffBat;
            if ($runsToSwap % 2 === 1) { [$striker, $nonStriker] = [$nonStriker, $striker]; }
            if ($wicket) { $striker = $this->resolvePlayerName($match, $p['new_batsman_id'] ?? null); }
            if ($isLegal && $legalBalls % 6 === 0) { [$striker, $nonStriker] = [$nonStriker, $striker]; }
        }

        return array_reverse($feed);
    }

    /**
     * Replay the ball-by-ball action log into complete per-innings scorecards.
     * The live match row only keeps the current crease + bowler, so the full card
     * (all batters, all bowlers, extras, fall of wickets) is rebuilt here from
     * `match_actions`, which retains the entire history.
     */
    private function buildInningsCards(LiveMatch $match): array
    {
        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id', 'asc')
            ->get();

        $cards = [];
        $cur = null;       // working innings
        $finalize = function () use (&$cards, &$cur) {
            if ($cur === null) {
                return;
            }
            $extrasTotal = $cur['extras']['wd'] + $cur['extras']['nb'] + $cur['extras']['b'] + $cur['extras']['lb'];
            $overs = intdiv($cur['legalBalls'], 6) . '.' . ($cur['legalBalls'] % 6);
            $rr = $cur['legalBalls'] > 0 ? sprintf('%.2f', $cur['runs'] / ($cur['legalBalls'] / 6.0)) : '0.00';
            $cards[] = [
                'number'       => $cur['number'],
                'battingTeam'  => $cur['battingTeam'],
                'battingName'  => $cur['battingName'],
                'runs'         => $cur['runs'],
                'wickets'      => $cur['wickets'],
                'overs'        => $overs,
                'runRate'      => $rr,
                'extras'       => ['total' => $extrasTotal] + $cur['extras'],
                'batters'      => array_values($cur['batters']),
                'bowlers'      => array_values($cur['bowlers']),
                'fow'          => $cur['fow'],
                'partnershipRuns'  => $cur['pRuns'],
                'partnershipBalls' => $cur['pBalls'],
            ];
            $cur = null;
        };

        $ensureBatter = function (&$cur, string $name) {
            if ($name === '') {
                return;
            }
            if (!isset($cur['batters'][$name])) {
                $cur['batters'][$name] = [
                    'name' => $name, 'runs' => 0, 'balls' => 0, 'fours' => 0,
                    'sixes' => 0, 'out' => false, 'dismissal' => 'not out', 'order' => count($cur['batters']) + 1,
                ];
            }
        };
        $ensureBowler = function (&$cur, string $name) {
            if ($name === '') {
                return;
            }
            if (!isset($cur['bowlers'][$name])) {
                $cur['bowlers'][$name] = [
                    'name' => $name, 'balls' => 0, 'runs' => 0, 'wickets' => 0,
                    'maidens' => 0, 'order' => count($cur['bowlers']) + 1,
                ];
            }
        };

        foreach ($actions as $act) {
            $type = (string) $act->action_type;
            $p = json_decode($act->payload, true) ?: [];

            if ($type === 'start') {
                $finalize();
                $battingTeam = (int) ($p['batting_team'] ?? 1);
                $battingName = $battingTeam === 2
                    ? ($match->away_full ?: $match->away)
                    : ($match->home_full ?: $match->home);
                $cur = [
                    'number' => count($cards) + 1,
                    'battingTeam' => $battingTeam,
                    'battingName' => (string) $battingName,
                    'runs' => 0, 'wickets' => 0, 'legalBalls' => 0,
                    'extras' => ['wd' => 0, 'nb' => 0, 'b' => 0, 'lb' => 0],
                    'batters' => [], 'bowlers' => [], 'fow' => [],
                    'striker' => $this->resolvePlayerName($match, $p['striker_id'] ?? null),
                    'nonStriker' => $this->resolvePlayerName($match, $p['non_striker_id'] ?? null),
                    'bowler' => $this->resolvePlayerName($match, $p['bowler_id'] ?? null),
                    'overRuns' => 0,
                    // Current (unbroken) partnership — reset on each wicket.
                    'pRuns' => 0, 'pBalls' => 0,
                ];
                $ensureBatter($cur, $cur['striker']);
                $ensureBatter($cur, $cur['nonStriker']);
                $ensureBowler($cur, $cur['bowler']);
                continue;
            }

            if ($cur === null) {
                continue;
            }

            if ($type === 'change_bowler') {
                $cur['bowler'] = $this->resolvePlayerName($match, $p['bowler_id'] ?? null);
                $ensureBowler($cur, $cur['bowler']);
                continue;
            }
            if ($type === 'change_batsman') {
                $name = $this->resolvePlayerName($match, $p['id'] ?? null);
                if (($p['role'] ?? 'striker') === 'striker') {
                    $cur['striker'] = $name;
                } else {
                    $cur['nonStriker'] = $name;
                }
                $ensureBatter($cur, $name);
                continue;
            }

            // ── A delivery ──
            $isLegal = true;
            $runsOffBat = 0;
            $extras = 0;
            $wicket = false;
            switch ($type) {
                case 'runs':   $runsOffBat = (int) ($p['value'] ?? 0); break;
                case 'wide':   $isLegal = false; $extras = (int) ($p['value'] ?? 1); $cur['extras']['wd'] += $extras; break;
                case 'noball': $isLegal = false; $runsOffBat = (int) ($p['runs_off_bat'] ?? 0); $extras = 1; $cur['extras']['nb'] += 1; break;
                case 'bye':    $extras = (int) ($p['value'] ?? 1); $cur['extras']['b'] += $extras; break;
                case 'legbye': $extras = (int) ($p['value'] ?? 1); $cur['extras']['lb'] += $extras; break;
                case 'wicket': $wicket = true; break;
                default: continue 2;
            }
            $total = $runsOffBat + $extras;
            $cur['runs'] += $total;
            $cur['pRuns'] += $total;
            if ($isLegal) {
                $cur['pBalls'] += 1;
            }

            // Start of a new over → reset the bowler's running over tally (for maidens).
            if ($isLegal && $cur['legalBalls'] % 6 === 0) {
                $cur['overRuns'] = 0;
            }

            // Striker: faces the ball (except a wide); byes/legbyes add no batting runs.
            $sName = $cur['striker'];
            if ($sName !== '' && isset($cur['batters'][$sName]) && $type !== 'wide') {
                $cur['batters'][$sName]['runs'] += $runsOffBat;
                $cur['batters'][$sName]['balls'] += 1;
                if ($type === 'runs' && $runsOffBat === 4) $cur['batters'][$sName]['fours'] += 1;
                if ($type === 'runs' && $runsOffBat === 6) $cur['batters'][$sName]['sixes'] += 1;
            }

            // Bowler: charged everything except byes/legbyes; counts legal balls; maidens.
            $bName = $cur['bowler'];
            if ($bName !== '' && isset($cur['bowlers'][$bName])) {
                if ($type !== 'bye' && $type !== 'legbye') {
                    $cur['bowlers'][$bName]['runs'] += $total;
                    $cur['overRuns'] += $total;
                }
                if ($isLegal) {
                    $cur['bowlers'][$bName]['balls'] += 1;
                }
                if ($wicket) {
                    $cur['bowlers'][$bName]['wickets'] += 1;
                }
            }

            if ($isLegal) {
                $cur['legalBalls'] += 1;
            }

            // Strike rotation on odd runs (byes/legbyes swap on their run count too).
            $runsToSwap = ($type === 'bye' || $type === 'legbye') ? $extras : $runsOffBat;
            if ($runsToSwap % 2 === 1) {
                [$cur['striker'], $cur['nonStriker']] = [$cur['nonStriker'], $cur['striker']];
            }

            if ($wicket) {
                $cur['wickets'] += 1;
                $outName = $cur['striker'];
                if ($outName !== '' && isset($cur['batters'][$outName])) {
                    $cur['batters'][$outName]['out'] = true;
                    $cur['batters'][$outName]['dismissal'] = $this->dismissalText($p, $bName);
                }
                $oversStr = intdiv($cur['legalBalls'], 6) . '.' . ($cur['legalBalls'] % 6);
                $cur['fow'][] = [
                    'wicketNo' => $cur['wickets'],
                    'score'    => $cur['runs'],
                    'over'     => $oversStr,
                    'batter'   => $outName,
                ];
                $newName = $this->resolvePlayerName($match, $p['new_batsman_id'] ?? null);
                $cur['striker'] = $newName;
                $ensureBatter($cur, $newName);
                // A wicket starts a fresh partnership.
                $cur['pRuns'] = 0;
                $cur['pBalls'] = 0;
            }

            // Over complete → swap ends + tally a maiden if the bowler conceded nothing.
            if ($isLegal && $cur['legalBalls'] % 6 === 0) {
                if ($bName !== '' && isset($cur['bowlers'][$bName]) && $cur['overRuns'] === 0) {
                    $cur['bowlers'][$bName]['maidens'] += 1;
                }
                [$cur['striker'], $cur['nonStriker']] = [$cur['nonStriker'], $cur['striker']];
            }
        }

        $finalize();
        return $cards;
    }

    /** Resolve a player id (or guest name) to a display name via the match squads. */
    private function resolvePlayerName(LiveMatch $match, $id): string
    {
        if (empty($id) || strtolower((string) $id) === 'null') {
            return '';
        }
        foreach (($match->home_squad ?? []) as $pl) {
            if (isset($pl['id']) && (string) $pl['id'] === (string) $id) {
                return (string) ($pl['name'] ?? $id);
            }
        }
        foreach (($match->away_squad ?? []) as $pl) {
            if (isset($pl['id']) && (string) $pl['id'] === (string) $id) {
                return (string) ($pl['name'] ?? $id);
            }
        }
        return (string) $id; // guests come through as their own name
    }

    /** Short dismissal label, e.g. "b Siva". Falls back to a plain "out". */
    private function dismissalText(array $payload, string $bowler): string
    {
        $how = strtolower((string) ($payload['dismissal'] ?? $payload['wicket_type'] ?? ''));
        $b = $bowler !== '' ? $bowler : '';
        return match (true) {
            $how === 'bowled'                       => $b !== '' ? "b $b" : 'bowled',
            $how === 'lbw'                          => 'lbw' . ($b !== '' ? " b $b" : ''),
            // Fielder isn't captured, so credit the bowler: "c b {bowler}".
            $how === 'caught'                       => $b !== '' ? "c b $b" : 'caught',
            $how === 'runout' || $how === 'run out' || $how === 'run_out' => 'run out',
            $how === 'stumped'                      => $b !== '' ? "st b $b" : 'stumped',
            $b !== ''                               => "b $b",
            default                                 => 'out',
        };
    }

    /** Make a stored logo path absolute so the app can load it directly. */
    private function absoluteLogo(?string $logo): string
    {
        $logo = trim((string) $logo);
        if ($logo === '') {
            return '';
        }
        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }
        return url($logo);
    }

    /** Sum the numeric run value of a list of ball codes (ignores W, wd, nb, etc.). */
    private function sumBalls(array $balls): int
    {
        $total = 0;
        foreach ($balls as $b) {
            if (is_numeric($b)) {
                $total += (int) $b;
            }
        }
        return $total;
    }
}
