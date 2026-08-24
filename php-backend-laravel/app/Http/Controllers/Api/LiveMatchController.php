<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\MatchViewer;
use App\Models\User;
use App\Support\MatchGeocoder;
use App\Support\MatchProximity;
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
        // Every public match is returned to every viewer, guests included.
        // ?scope=local|featured|all narrows to one slice.
        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;
        $scope = $request->query('scope');
        $scope = is_string($scope) ? strtolower($scope) : null;

        $near = $this->viewerPosition($request, $viewer);

        // Ranking happens in PHP, not SQL: the sort blends a haversine distance
        // with place-name tiers, which no portable ORDER BY can express. We pull a
        // bounded candidate set (freshest first) and rank that.
        $matches = LiveMatch::query()
            ->visibleTo($viewer, $scope)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        // Most matches were created without a GPS fix and carry only place names, so
        // give each one real coordinates geocoded from those names (in memory, cached)
        // — exactly what the web ActionBoard does. Without this every distanceKm comes
        // back null and the app can't show "2.4 km" chips or rank by true distance.
        // Only worth doing when the viewer has a position to measure against.
        if ($near->hasPosition()) {
            $geocoder = new MatchGeocoder();
            $matches->each(fn (LiveMatch $m) => $geocoder->ensureCoords($m));
        }

        $matches = $near->sort($matches)->take(40);

        $data = $matches->map(function (LiveMatch $m) use ($viewer, $near): array {
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

            // `score_text` is a CRICKET score ("120/4"), and only cricket can carry the
            // batting side's wickets in it. Every other sport has its scoreline derived
            // into home_score/away_score, where score_text holds the whole line ("2 - 1")
            // — using it here printed that whole line as team 1's score and left team 2
            // showing a bare number, so a 49-50 basketball game read "49 - 50 | 50".
            $isCricket = strtolower((string) ($m->sport ?: 'cricket')) === 'cricket';
            // A set sport's scoreline is SETS won, so a match in its first set reads "0 - 0"
            // even while a rally is being played — the card looked idle on a live game. The
            // points in the current set go in the small slot cricket uses for overs, so the
            // card reads "0 (2)" — sets big, rally beneath, which is how these sports are
            // actually scored.
            $setRally = ['', ''];
            if (! $isCricket) {
                $state = is_array($m->sport_state) ? $m->sport_state : [];
                $current = $state['current'] ?? null;
                $points = $state['points'] ?? null;   // tennis: the 15/30/40 ladder
                if (is_array($points) && count($points) >= 2) {
                    $setRally = [(string) $points[0], (string) $points[1]];
                } elseif (is_array($current) && count($current) >= 2
                    && ((int) $current[0] > 0 || (int) $current[1] > 0)) {
                    $setRally = [(string) $current[0], (string) $current[1]];
                }
            }
            $scoreText = $isCricket ? (string) ($m->score_text ?: '') : '';
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
                'overs1'      => $isCricket ? ($battingTeam === 2 ? '' : $overs) : $setRally[0],
                'overs2'      => $isCricket ? ($battingTeam === 2 ? $overs : '') : $setRally[1],
                'battingTeam' => $battingTeam,
                'status'      => (string) ($m->status ?? ''),
                // Which sport this is, so the app's Cricket/Badminton/Football boards
                // can filter for real. Defaults to cricket: every match created before
                // the column existed is one, and an untagged match must not vanish
                // from the only board that currently works.
                'sport'       => strtolower((string) ($m->sport ?: 'cricket')),
                'venue'       => (string) ($m->venue ?? ''),
                // Set only when this match sits on a confirmed Haraan booking — the card
                // shows the venue's real name in place of the typed one when it is.
                'venueBadge'  => $this->venueBadge($m),
                'competition' => (string) ($m->competition ?? ''),
                'isLive'      => strtolower((string) $m->status) === 'live',
                'visibility'  => (string) ($m->visibility ?? LiveMatch::VIS_LOCAL),
                'district'    => (string) ($m->district ?? ''),
                'locality'    => (string) ($m->locality ?? ''),
                // Whether the signed-in viewer created this match — lets the app tag their
                // own matches in the feed so they can spot them at a glance.
                'isMine'      => $viewer !== null && (int) $m->user_id === (int) $viewer->id,
                // Everyone sees every public match now, so "district" is a grouping
                // hint, not an access rule: true only when this match sits in the
                // viewer's own district. Always false for guests (no district).
                'isLocalToViewer' => $viewer !== null
                    && (string) ($viewer->district ?? '') !== ''
                    && (string) $m->district === (string) $viewer->district,

                // Admin curation, shown as a ⭐ on the card rather than its own
                // section — the list stays one scannable column.
                'isFeatured'  => (string) $m->visibility === LiveMatch::VIS_FEATURED,
                // Measured km from the viewer, or null when either side has no GPS
                // fix (every match created before 2026-07-21). The app shows the
                // chip only when this is present — never a guessed distance.
                'distanceKm'  => $this->roundKm($near->distanceKm($m)),
            ];
        })->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Where the viewer is, for ranking. Device location (sent by the app on every
     * feed fetch) wins over the profile, because the profile records where someone
     * signed up — not where they are standing today. Guests supply device location
     * too, which is the whole point: proximity must not require an account.
     */
    private function viewerPosition(Request $request, ?User $viewer): MatchProximity
    {
        $num = static function ($v): ?float {
            return is_numeric($v) ? (float) $v : null;
        };
        $str = static function ($v): string {
            return is_string($v) ? trim($v) : '';
        };

        $lat = $num($request->query('lat'));
        $lng = $num($request->query('lng'));

        return new MatchProximity(
            latitude: ($lat !== null && $lat >= -90 && $lat <= 90) ? $lat : null,
            longitude: ($lng !== null && $lng >= -180 && $lng <= 180) ? $lng : null,
            locality: $str($request->query('locality')),
            district: $str($request->query('district')) ?: (string) ($viewer->district ?? ''),
            state: $str($request->query('state')) ?: (string) ($viewer->state ?? ''),
        );
    }

    /** One decimal is all a distance chip needs; null stays null. */
    private function roundKm(?float $km): ?float
    {
        return $km === null ? null : round($km, 1);
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

    /**
     * Presence heartbeat for the Match Details screen: "I'm watching this match", answered
     * with how many people are watching it right now.
     * POST /api/live-matches/{id}/watching
     *
     * Deliberately NOT folded into the detail payload above: that response is ETag'd, and a
     * count that ticks on its own would turn every 12s score poll from a 304 into a full
     * re-download. This is a tiny separate call the app makes only while a match is live.
     */
    public function watching(Request $request, string $id): JsonResponse
    {
        $match = LiveMatch::find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;
        // Same visibility gate as show(): presence must not confirm a hidden match exists.
        if (!$match->isVisibleTo($viewer)) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        return $this->recordWatcher($request, $match, $viewer);
    }

    /**
     * Presence heartbeat for a PRIVATE match opened by share code — same contract as
     * watching(), with the code as the grant.
     * POST /api/live-matches/code/{code}/watching
     */
    public function watchingByCode(Request $request, string $code): JsonResponse
    {
        $match = LiveMatch::byJoinCode($code)->where('is_private', true)->first();
        if ($match === null) {
            return response()->json(['error' => 'No match found for that code.'], 404);
        }

        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;

        return $this->recordWatcher($request, $match, $viewer);
    }

    /** Upsert this viewer's presence row and answer with the live audience size. */
    private function recordWatcher(Request $request, LiveMatch $match, ?User $viewer): JsonResponse
    {
        $count = MatchViewer::heartbeat(
            (int) $match->id,
            $this->viewerKey($request, $viewer),
            $viewer?->id,
        );

        return response()->json([
            'watching' => $count,
            // So a client can pace itself off the server's window instead of hardcoding it.
            'windowSeconds' => MatchViewer::PRESENCE_WINDOW_SECONDS,
            // Whether this viewer may open the audience list. Answered here rather than made
            // the app's business, so the rule lives in one place and the app can't award
            // itself the privilege by flipping a local flag.
            'canSeeViewers' => $viewer !== null && (bool) $viewer->is_verified,
        ]);
    }

    /**
     * Who is watching this match right now.
     * GET /api/live-matches/{id}/viewers
     *
     * A verified account gets to see the room. Everybody else gets the number and nothing
     * else — the count is public, the audience is not.
     *
     * Signed-in viewers appear as themselves: the same name, handle, photo and tick their
     * public profile already shows, and nothing that isn't on it. Everyone else appears as
     * "Haraan Guest" — no install id, no IP, no device, nothing that could be turned back
     * into a person. That is the whole point of the guest row: the count stays honest
     * without the anonymous half of the audience being identified to anyone.
     */
    public function viewers(Request $request, string $id): JsonResponse
    {
        $match = LiveMatch::find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;
        if (!$match->isVisibleTo($viewer)) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        return $this->audience($match, $viewer);
    }

    /** The same list for a private match opened by share code. */
    public function viewersByCode(Request $request, string $code): JsonResponse
    {
        $match = LiveMatch::byJoinCode($code)->where('is_private', true)->first();
        if ($match === null) {
            return response()->json(['error' => 'No match found for that code.'], 404);
        }

        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;

        return $this->audience($match, $viewer);
    }

    /** Assemble the audience, gated on the blue tick. */
    private function audience(LiveMatch $match, ?User $viewer): JsonResponse
    {
        if ($viewer === null || !$viewer->is_verified) {
            return response()->json([
                'error' => 'Only verified accounts can see who is watching.',
            ], 403);
        }

        $rows = MatchViewer::query()
            ->where('match_id', $match->id)
            ->present()
            ->with('user:id,name,username,avatar,is_verified')
            ->orderByDesc('last_seen_at')
            ->limit(100)
            ->get();

        $people = [];
        $guests = 0;
        foreach ($rows as $row) {
            $user = $row->user;
            if ($user === null) {
                $guests++;
                continue;
            }
            $people[] = [
                'user_id'     => (int) $user->id,
                'name'        => (string) ($user->name ?? ''),
                'username'    => (string) ($user->username ?? ''),
                'avatar'      => \App\Support\MediaUrl::resolve($user->avatar),
                'is_verified' => (bool) $user->is_verified,
                'is_guest'    => false,
                'is_you'      => (int) $user->id === (int) $viewer->id,
            ];
        }

        // The anonymous half of the room, as rows rather than a footnote — the list should
        // add up to the number on the chip, or it reads as though people went missing.
        for ($i = 0; $i < $guests; $i++) {
            $people[] = [
                'user_id'     => null,
                'name'        => 'Haraan Guest',
                'username'    => '',
                'avatar'      => null,
                'is_verified' => false,
                'is_guest'    => true,
                'is_you'      => false,
            ];
        }

        return response()->json([
            'watching'      => $rows->count(),
            'signedIn'      => $rows->count() - $guests,
            'guests'        => $guests,
            'windowSeconds' => MatchViewer::PRESENCE_WINDOW_SECONDS,
            'viewers'       => $people,
        ]);
    }

    /**
     * The Haraan-venue badge for a match, or null.
     *
     * Resolved ONLY from `venue_booking_id` → a CONFIRMED booking → that booking's venue.
     * Deliberately never from the free-text `venue` column: that field is whatever the creator
     * typed, so keying a trust mark off it would let anyone earn one by spelling a turf's name
     * correctly. The same booking is what already auto-verifies the match at x1.25 XP
     * (VenueVerificationService — "the moat"); this just makes that visible, so the badge can
     * never disagree with the trust the platform already granted.
     *
     * There is no negative counterpart. A match on a maidan is not "unverified" — it is the
     * normal case, and marking it would be an insult dressed up as a feature.
     */
    private function venueBadge(LiveMatch $match): ?array
    {
        $booking = \App\Services\VenueVerificationService::findValidBooking($match->venue_booking_id);
        if ($booking === null) {
            return null;
        }

        $venue = $booking->venue;
        if ($venue === null || trim((string) $venue->name) === '') {
            return null;
        }

        return [
            'venueId' => (int) $venue->id,
            'name'    => (string) $venue->name,
            // The area, so the chip can stay short and still say WHERE.
            'area'    => (string) ($venue->location ?? ''),
        ];
    }

    /**
     * A stable, non-identifying key for whoever is asking. Signed-in viewers are keyed by
     * user id (so the same person on phone and web counts once). Guests send the app's
     * random install id, which we hash — and if there's none, we fall back to a hash of
     * ip + user-agent. No raw IP is ever stored.
     */
    private function viewerKey(Request $request, ?User $viewer): string
    {
        if ($viewer !== null) {
            return 'u:' . $viewer->id;
        }

        $client = (string) ($request->input('viewer') ?? '');
        $client = preg_replace('/[^A-Za-z0-9_-]/', '', $client) ?? '';
        if ($client !== '') {
            return 'd:' . substr(hash('sha256', $client), 0, 40);
        }

        return 'a:' . substr(hash('sha256', $request->ip() . '|' . (string) $request->userAgent()), 0, 40);
    }

    /**
     * Public accessor for the assembled detail payload, so the server-rendered web
     * match pages can render from the exact same data the app's Match Details screen
     * consumes (score, live crease, replayed innings cards, commentary feed).
     */
    public function detailPayload(LiveMatch $match, ?User $viewer = null): array
    {
        return $this->matchDetail($match, $viewer);
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
        // The creator may score their own match — until it is finished. A completed match
        // still offered the Score button, and points recorded there would edit a result
        // that has already been frozen and (once settled) paid out XP against.
        $canScore = $viewer !== null
            && (int) $match->user_id === (int) $viewer->id
            && strtolower((string) $match->status) !== 'completed';

        // Replay once, then derive the live partnership + last wicket from the current innings.
        $cards = $this->buildInningsCards($match);
        $liveCard = !empty($cards) ? end($cards) : null;

        // Hero headline scores must carry wickets — but `home_score`/`away_score` are
        // run-only integers and `score_text` is only ever set by the admin panel, so an
        // app-scored match would otherwise show "45" with no "/wkts". Rebuild each side's
        // score from the replayed innings (runs AND wickets); fall back to the raw columns
        // for admin-managed matches that have no ball-by-ball log.
        foreach ($cards as $c) {
            $t = (int) ($c['battingTeam'] ?? 1);
            $line = ((int) ($c['runs'] ?? 0)) . '/' . ((int) ($c['wickets'] ?? 0));
            if ($t === 2) { $awayScoreText = $line; } else { $homeScoreText = $line; }
        }
        $score = $battingTeam === 2 ? $awayScoreText : $homeScoreText;
        $opponentScore = $battingTeam === 2 ? $homeScoreText : $awayScoreText;
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
            // Which detail screen to open. MatchUiState.sport existed with the comment
            // "drives which scorer/view opens" but was never populated — so every match,
            // football included, rendered the cricket scorecard.
            'sport' => strtolower((string) ($match->sport ?: 'cricket')),
            // Football/badminton scoreline + timeline. Null for cricket, which keeps
            // its own per-ball payload below.
            'football' => strtolower((string) $match->sport) === 'football'
                ? app(\App\Services\MatchEventRecorder::class)->footballPayload($match)
                : null,
            // The rally / points board — volleyball, basketball, kabaddi, tennis, table
            // tennis. Null for cricket and football, which each have their own shape
            // above, so a client can switch on whichever one is present.
            'board' => app(\App\Services\MatchEventRecorder::class)->boardPayload($match),
            'formatLabel' => (string) ($match->competition ?? ''),
            'venue' => (string) ($match->venue ?? ''),
            'venueBadge' => $this->venueBadge($match),
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
            // Impact ranking (MVP tab) derived from the same replayed cards.
            'mvp' => $this->buildMvp($match, $cards, $viewer),
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
    /**
     * Public so the scoring path can ask for the same replay it would otherwise have to
     * reimplement: after a ball is recorded, MatchesController needs the line for THAT
     * delivery, and the crease it belongs to is only knowable by replaying the log.
     */
    public function commentaryFeed(LiveMatch $match): array
    {
        return $this->buildCommentary($match);
    }

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
        $careerCache = []; // id -> real career line (looked up once per match build)
        $photoCache  = []; // id -> profile photo URL (same, one lookup per player)
        // The crease is tracked by NAME for the commentary text and by ID for the face —
        // a dismissal card needs the out batter's id to fetch their photo, and the id is
        // the only thing that survives two players sharing a name.
        $strikerId = null;
        $nonStrikerId = null;

        foreach ($actions as $act) {
            $type = (string) $act->action_type;
            $p = json_decode($act->payload, true) ?: [];

            if ($type === 'start') {
                $inningsNo++;
                $bt = (int) ($p['batting_team'] ?? 1);
                $battingName = (string) ($bt === 2 ? ($match->away_full ?: $match->away) : ($match->home_full ?: $match->home));
                $strikerId = $p['striker_id'] ?? null;
                $nonStrikerId = $p['non_striker_id'] ?? null;
                $striker = $this->resolvePlayerName($match, $strikerId);
                $nonStriker = $this->resolvePlayerName($match, $nonStrikerId);
                $bowler = $this->resolvePlayerName($match, $p['bowler_id'] ?? null);
                $legalBalls = 0;
                $feed[] = ['innings' => $inningsNo, 'over' => '', 'kind' => 'header',
                    'text' => "Innings $inningsNo — $battingName", 'label' => '', 'runs' => 0,
                    'wicket' => false, 'boundary' => false, 'battingName' => $battingName];
                // Opening pair: each opener is a "new batter" arriving at the crease.
                $feed[] = $this->batterInLine($inningsNo, '', $striker, $strikerId, $battingName, $careerCache, $photoCache);
                $feed[] = $this->batterInLine($inningsNo, '', $nonStriker, $nonStrikerId, $battingName, $careerCache, $photoCache);
                continue;
            }
            if ($type === 'change_bowler') {
                $bowler = $this->resolvePlayerName($match, $p['bowler_id'] ?? null);
                continue;
            }
            if ($type === 'change_batsman') {
                $name = $this->resolvePlayerName($match, $p['id'] ?? null);
                if (($p['role'] ?? 'striker') === 'striker') {
                    $striker = $name; $strikerId = $p['id'] ?? null;
                } else {
                    $nonStriker = $name; $nonStrikerId = $p['id'] ?? null;
                }
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

            // What the board has always shown, and what a written line is expanded FROM.
            $shorthand = trim(($bowler !== '' ? "$bowler to " : '') . ($striker !== '' ? "$striker, " : '') . $outcome);
            $written = trim((string) ($act->commentary ?? ''));

            $overNo = intdiv($legalBalls, 6);
            $ballInOver = ($legalBalls % 6) + 1;
            $overMark = "$overNo.$ballInOver";
            $outBatter = $striker;
            $outBatterId = $strikerId;

            $feed[] = [
                'innings'  => $inningsNo,
                'over'     => $overMark,
                'kind'     => 'ball',
                // A written line replaces the scorer's shorthand when this ball has one.
                // Null is the normal state: older balls, and every ball when Gemini is not
                // configured. The shorthand rides along regardless, so a client that wants
                // the terse feed never has to re-derive it.
                'text'      => $written !== '' ? $written : $shorthand,
                'shorthand' => $shorthand,
                'actionId'  => (int) $act->id,
                // The parts, separately. The collapsed shorthand is ambiguous when a name
                // is missing ("kishore to no run" reads as a batter called "no run"), so
                // anything WRITING from these facts must get them labelled, not parsed.
                'bowler'    => $bowler,
                'striker'   => $striker,
                'outcome'   => $outcome,
                'label'    => $label,
                'runs'     => $runsOffBat + $extras,
                'wicket'   => $wicket,
                'boundary' => ($type === 'runs' && ($runsOffBat === 4 || $runsOffBat === 6)),
                'battingName' => $battingName,
                // The out batter's identity + real face travel with the wicket so the app
                // can put the player, not a monogram, on the dismissal card. Resolved only
                // on wickets — no other ball renders a card to hang a photo on.
                'playerId' => $wicket ? (string) ($outBatterId ?? '') : '',
                'photo'    => $wicket ? $this->avatarFor($outBatterId, $photoCache) : null,
            ];

            if ($isLegal) {
                $legalBalls++;
            }

            // Strike rotation (so the next line names the right batter).
            $runsToSwap = ($type === 'bye' || $type === 'legbye') ? $extras : $runsOffBat;
            if ($runsToSwap % 2 === 1) {
                [$striker, $nonStriker] = [$nonStriker, $striker];
                [$strikerId, $nonStrikerId] = [$nonStrikerId, $strikerId];
            }
            if ($wicket) {
                $newId = $p['new_batsman_id'] ?? null;
                $strikerId = $newId;
                $striker = $this->resolvePlayerName($match, $newId);
                // The incoming batter walks in — emit their "new batter" card at this over.
                if ($striker !== '') {
                    $feed[] = $this->batterInLine($inningsNo, $overMark, $striker, $newId, $battingName, $careerCache, $photoCache);
                }
            }
            if ($isLegal && $legalBalls % 6 === 0) {
                [$striker, $nonStriker] = [$nonStriker, $striker];
                [$strikerId, $nonStrikerId] = [$nonStrikerId, $strikerId];
            }
        }

        return array_reverse($feed);
    }

    /**
     * Build a "new batter" feed entry. Carries the batter's real career line (or null when
     * they're a guest / have no completed matches yet) so the app can show RUNS/BALLS/AVG.
     */
    private function batterInLine(int $inningsNo, string $over, string $name, $id, string $battingName, array &$cache, array &$photoCache): array
    {
        return [
            'innings'     => $inningsNo,
            'over'        => $over,
            'kind'        => 'batter_in',
            'text'        => $name,
            'label'       => '',
            'runs'        => 0,
            'wicket'      => false,
            'boundary'    => false,
            'battingName' => $battingName,
            'playerId'    => (string) ($id ?? ''),
            'photo'       => $this->avatarFor($id, $photoCache),
            'career'      => $this->careerFor($id, $cache),
        ];
    }

    /**
     * A player's real profile photo for a player id, memoised per match build. Null for
     * guests, unregistered names, and anyone who simply hasn't uploaded one — the app
     * falls back to initials rather than a stock silhouette.
     */
    private function avatarFor($id, array &$cache): ?string
    {
        $key = trim((string) ($id ?? ''));
        if ($key === '' || strtolower($key) === 'null') {
            return null;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $raw = \App\Models\User::where('player_id', $key)->value('avatar');
        return $cache[$key] = \App\Support\MediaUrl::resolve($raw !== null ? (string) $raw : null);
    }

    /** Real career batting for a player id, memoised per match build. Null if none/guest. */
    private function careerFor($id, array &$cache): ?array
    {
        $key = trim((string) ($id ?? ''));
        if ($key === '' || strtolower($key) === 'null') {
            return null;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $row = \App\Models\PlayerCareerBatting::where('player_id', $key)->first();
        $cache[$key] = $row ? [
            'innings'   => (int) $row->innings,
            'runs'      => (int) $row->runs,
            'balls'     => (int) $row->balls,
            'highScore' => (int) $row->high_score,
            'avg'       => $row->average(),     // float | null
            'sr'        => $row->strikeRate(),  // float | null
        ] : null;
        return $cache[$key];
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

    /**
     * Rank every player who actually did something by an "impact points" score, for the
     * MVP tab. Built purely from the replayed innings cards ([buildInningsCards]) so it
     * agrees with the scorecard to the ball — no separate source of truth, no estimates.
     *
     * Points (kept deliberately simple so the app can explain it in one line):
     *   batting  = runs + 1/four + 2/six, plus a strike-rate bonus once they've faced
     *              10 balls (below that a cameo SR is noise, not impact)
     *   bowling  = 20/wicket + 8/maiden, plus an economy bonus once they've bowled a
     *              full over
     *
     * Fielding is deliberately absent: the scorer never captures the fielder (see
     * [dismissalText]), so catches/run-outs cannot be credited to anyone without
     * inventing them.
     *
     * A player's team is unambiguous — batters belong to the innings' batting side,
     * bowlers to the other one — so batting and bowling spells across both innings
     * aggregate onto one row. Keyed by display name, exactly like the replay itself.
     */
    private function buildMvp(LiveMatch $match, array $cards, ?User $viewer = null): array
    {
        if (empty($cards)) {
            return [];
        }

        $teamName = fn (int $team): string => $team === 2
            ? (string) ($match->away_full ?: $match->away)
            : (string) ($match->home_full ?: $match->home);

        // Squad name -> player id. The scorecard's batter and bowler names come from the
        // squad the scorer picked from, so this is an exact join rather than a fuzzy match
        // on a person's name - which is the only kind of join worth making when the result
        // decides whose FACE appears on a card.
        $idByName = [];
        foreach ([$match->home_squad ?: [], $match->away_squad ?: []] as $squad) {
            foreach ((array) $squad as $member) {
                $memberName = trim((string) (is_array($member) ? ($member['name'] ?? '') : ''));
                $memberId = trim((string) (is_array($member) ? ($member['id'] ?? '') : ''));
                if ($memberName !== '' && $memberId !== '' && strtolower($memberId) !== 'null') {
                    $idByName[mb_strtolower($memberName)] = $memberId;
                }
            }
        }
        $photoCache = [];

        // Whether the VIEWER already follows each of these players, resolved for the whole
        // ranking in one query rather than one per card. Empty for a signed-out visitor,
        // whose Follow buttons have no state to settle against and so are not offered.
        $followed = [];
        $viewerPlayerId = $viewer instanceof User ? (string) $viewer->player_id : '';

        // Which squad ids are REAL accounts. A squad id alone proves nothing - seeded and
        // legacy matches carry ids like "DEMO_AR" that no user owns, and offering Follow on
        // one of those ships a button whose only possible outcome is a 404.
        $userIdByPlayerId = collect();
        if ($idByName !== []) {
            $squadIds = array_values(array_unique(array_values($idByName)));
            $userIdByPlayerId = User::whereIn('player_id', $squadIds)
                ->where('is_guest', false)
                ->pluck('id', 'player_id');
        }

        if ($viewer instanceof User) {
            if ($userIdByPlayerId->isNotEmpty()) {
                $following = array_flip(
                    DB::table('player_follows')
                        ->where('follower_id', $viewer->id)
                        ->whereIn('followee_id', $userIdByPlayerId->values()->all())
                        ->pluck('followee_id')
                        ->all()
                );
                foreach ($userIdByPlayerId as $pid => $uid) {
                    $followed[(string) $pid] = isset($following[$uid]);
                }
            }
        }

        $rows = [];
        $blank = static fn (string $name, int $team): array => [
            'name' => $name, 'team' => $team,
            'runs' => 0, 'ballsFaced' => 0, 'fours' => 0, 'sixes' => 0, 'out' => false,
            'wickets' => 0, 'ballsBowled' => 0, 'runsConceded' => 0, 'maidens' => 0,
        ];

        foreach ($cards as $card) {
            $battingTeam = (int) ($card['battingTeam'] ?? 1);
            $bowlingTeam = $battingTeam === 2 ? 1 : 2;

            foreach (($card['batters'] ?? []) as $b) {
                $name = trim((string) ($b['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $rows[$name] ??= $blank($name, $battingTeam);
                $rows[$name]['runs']       += (int) ($b['runs'] ?? 0);
                $rows[$name]['ballsFaced'] += (int) ($b['balls'] ?? 0);
                $rows[$name]['fours']      += (int) ($b['fours'] ?? 0);
                $rows[$name]['sixes']      += (int) ($b['sixes'] ?? 0);
                $rows[$name]['out']         = $rows[$name]['out'] || (bool) ($b['out'] ?? false);
            }

            foreach (($card['bowlers'] ?? []) as $bw) {
                $name = trim((string) ($bw['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $rows[$name] ??= $blank($name, $bowlingTeam);
                $rows[$name]['wickets']      += (int) ($bw['wickets'] ?? 0);
                $rows[$name]['ballsBowled']  += (int) ($bw['balls'] ?? 0);
                $rows[$name]['runsConceded'] += (int) ($bw['runs'] ?? 0);
                $rows[$name]['maidens']      += (int) ($bw['maidens'] ?? 0);
            }
        }

        $out = [];
        foreach ($rows as $r) {
            // Someone who never faced or bowled a ball has no impact to rank.
            if ($r['ballsFaced'] <= 0 && $r['ballsBowled'] <= 0) {
                continue;
            }

            $batPoints = 0;
            $sr = null;
            if ($r['ballsFaced'] > 0) {
                $batPoints = $r['runs'] + $r['fours'] + (2 * $r['sixes']);
                $sr = $r['runs'] * 100.0 / $r['ballsFaced'];
                if ($r['ballsFaced'] >= 10) {
                    $batPoints += match (true) {
                        $sr >= 150 => 8,
                        $sr >= 120 => 4,
                        $sr < 60   => -4,
                        default    => 0,
                    };
                }
            }

            $bowlPoints = 0;
            $econ = null;
            if ($r['ballsBowled'] > 0) {
                $bowlPoints = (20 * $r['wickets']) + (8 * $r['maidens']);
                $econ = $r['runsConceded'] * 6.0 / $r['ballsBowled'];
                if ($r['ballsBowled'] >= 6) {
                    $bowlPoints += match (true) {
                        $econ <= 4  => 8,
                        $econ <= 6  => 4,
                        $econ >= 12 => -4,
                        default     => 0,
                    };
                }
            }

            $overs = intdiv($r['ballsBowled'], 6) . '.' . ($r['ballsBowled'] % 6);

            // Blank for a guest, an unregistered name, or anyone who has not uploaded a
            // photo. The app draws a monogram in that case rather than a stock silhouette.
            $playerId = $idByName[mb_strtolower($r['name'])] ?? '';

            $out[] = [
                'name'         => $r['name'],
                'playerId'     => $playerId,
                'photo'        => $playerId === '' ? '' : (string) ($this->avatarFor($playerId, $photoCache) ?? ''),
                // A button is only offered when it can be both truthful and useful: a real
                // linked player, a signed-in viewer, and not the viewer themselves.
                'canFollow'    => $playerId !== ''
                    && $viewer instanceof User
                    && $playerId !== $viewerPlayerId
                    && $userIdByPlayerId->has($playerId),
                'isFollowing'  => $playerId !== '' && ($followed[$playerId] ?? false),
                'team'         => $r['team'],
                'teamName'     => $teamName($r['team']),
                'points'       => max(0, $batPoints + $bowlPoints),
                'batPoints'    => $batPoints,
                'bowlPoints'   => $bowlPoints,
                // Pre-formatted display lines; blank when they didn't bat / bowl.
                'batLine'      => $r['ballsFaced'] > 0
                    ? $r['runs'] . ($r['out'] ? '' : '*') . ' (' . $r['ballsFaced'] . ')'
                    : '',
                'bowlLine'     => $r['ballsBowled'] > 0
                    ? $r['wickets'] . '-' . $r['runsConceded'] . ' (' . $overs . ')'
                    : '',
                'strikeRate'   => $sr === null ? '' : sprintf('%.1f', $sr),
                'econ'         => $econ === null ? '' : sprintf('%.1f', $econ),
                'runs'         => $r['runs'],
                'ballsFaced'   => $r['ballsFaced'],
                'fours'        => $r['fours'],
                'sixes'        => $r['sixes'],
                'wickets'      => $r['wickets'],
                'ballsBowled'  => $r['ballsBowled'],
                'runsConceded' => $r['runsConceded'],
                'maidens'      => $r['maidens'],
            ];
        }

        // Highest impact first; ties broken by wickets, then runs, so the ordering is
        // stable across refreshes rather than shuffling on equal points.
        usort($out, function (array $a, array $b): int {
            return [$b['points'], $b['wickets'], $b['runs'], $a['name']]
                <=> [$a['points'], $a['wickets'], $a['runs'], $b['name']];
        });

        return $out;
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
