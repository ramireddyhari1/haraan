<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\StoreMatchRequest;
use App\Models\LiveMatch;
use App\Models\User;
use App\Services\MatchVerificationService;
use App\Services\PlayerStatsService;
use App\Services\ReputationService;
use App\Services\VenueVerificationService;
use App\Support\ActionboardXp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ActionBoard matches — create + verification lifecycle.
 */
final class MatchesController extends Controller
{
    /**
     * Create a match from the Create Match wizard. Born as Scheduled at Low
     * trust; XP unlocks only once the result is verified.
     */
    public function store(StoreMatchRequest $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $v = $request->validated();
        $type = $v['matchType'];
        $isPrivate = $request->boolean('isPrivate');

        // Serial abusers (low trust) cannot create ranked-tier tournaments. Private
        // matches earn no XP/rank, so the trust gate doesn't apply to them.
        if (!$isPrivate && $type === 'tournament' && !ReputationService::canCreateRankedTournament($authUser)) {
            return response()->json([
                'error' => 'Your trust score is too low to create tournament matches.',
            ], 403);
        }

        // Attach a Haraan turf booking (auto-verifies on completion). Must be a
        // CONFIRMED booking owned by the creator.
        $venueBookingId = null;
        if (!empty($v['venueBookingId'])) {
            $booking = VenueVerificationService::findValidBooking((int) $v['venueBookingId'], (int) $authUser->id);
            if ($booking === null) {
                return response()->json([
                    'error' => 'That venue booking is not valid or not yours.',
                ], 422);
            }
            $venueBookingId = (int) $booking->id;
        }

        $match = LiveMatch::query()->create([
            'title'        => $v['teamA'] . ' vs ' . $v['teamB'],
            // Short code for compact displays (hero monogram, live list); full name kept for headers.
            'home'         => self::shortName($v['teamA']),
            'away'         => self::shortName($v['teamB']),
            'home_full'    => $v['teamA'],
            'away_full'    => $v['teamB'],
            'home_emblem'  => $v['teamAEmblem'] ?? null,
            'away_emblem'  => $v['teamBEmblem'] ?? null,
            'competition'  => $v['overs'] . ' Over Match',
            'venue'        => $v['venue'] ?? 'Custom Match',
            'status'       => 'Scheduled',
            'time'         => 'Scheduled',
            'home_score'   => 0,
            'away_score'   => 0,
            'overs'        => '0.0',
            'crr'          => '0.00',
            'batters'      => [],
            'bowler'       => [],
            'timeline'     => [],
            'over_summary' => [],
            'home_squad'   => self::normalizeSquad($v['squadA'] ?? []),
            'away_squad'   => self::normalizeSquad($v['squadB'] ?? []),
            'user_id'      => $authUser->id,

            // ActionBoard ranking
            'match_type'          => $type,
            'base_xp'             => ActionboardXp::baseXpForType($type),
            'trust_level'         => 'low',
            'verification_status' => 'none',
            'is_ranked'           => false,
            'venue_booking_id'    => $venueBookingId,

            // Private mode: a closed scoreboard reachable only by share code. No XP,
            // never ranked, hidden from feeds. Immutable once created.
            'is_private'          => $isPrivate,
            'join_code'           => $isPrivate ? self::generateJoinCode() : null,

            // Geo-scoped visibility: born LOCAL, stamped with the creator's district
            // (and state, for the future STATE tier). Reach beyond the district is
            // granted by an admin — never chosen at creation.
            'visibility'          => LiveMatch::VIS_LOCAL,
            'district'            => $authUser->district,
            'state'               => $authUser->state,
            'locality'            => $v['locality'] ?? null,
        ]);

        return response()->json([
            'message' => 'Match created',
            'data'    => $match,
        ], 201);
    }

    /**
     * Generate a short, human-shareable, collision-checked join code for a private
     * match, e.g. "HRN-7K2Q". Avoids ambiguous characters (0/O, 1/I).
     */
    private static function generateJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'HRN-' . $suffix;
        } while (LiveMatch::where('join_code', $code)->exists());

        return $code;
    }

    /**
     * Upload a custom team logo for one side of a match. Stored on the public
     * disk and served from /storage/team-logos/...; a stored logo takes
     * precedence over the emblem when the match is rendered.
     * POST /api/matches/{id}/team-logo  (multipart: side, logo)
     */
    public function uploadTeamLogo(Request $request, string $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }
        if ((int) $match->user_id !== (int) $authUser->id) {
            return response()->json(['error' => 'Only the match creator can set team logos.'], 403);
        }

        $validated = $request->validate([
            'side' => ['required', 'string', 'in:home,away'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // 2 MB
        ]);

        $side = $validated['side'];
        $column = $side === 'home' ? 'home_logo' : 'away_logo';

        // Replace any previous upload for this side.
        $previous = $match->{$column};
        if (is_string($previous) && str_starts_with($previous, '/storage/')) {
            \Illuminate\Support\Facades\Storage::disk('public')
                ->delete(substr($previous, strlen('/storage/')));
        }

        $path = $request->file('logo')->store('team-logos', 'public');
        $url = '/storage/' . $path;

        $match->update([$column => $url]);

        return response()->json([
            'message' => 'Team logo updated',
            'side'    => $side,
            'url'     => $url,
        ]);
    }

    /**
     * Mark a match Completed: freeze stats and open the 72h verification window.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }

        $match->update(['status' => 'Completed']);
        PlayerStatsService::freezeMatchStats($match);
        // Auto-verify Haraan turf matches; otherwise open the captain window.
        VenueVerificationService::onMatchCompleted($match);

        return response()->json(['message' => 'Match completed', 'data' => $match->fresh()]);
    }

    /**
     * A captain confirms the result. Both captains confirming → Medium trust.
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }
        if ($match->verification_status !== 'pending') {
            return response()->json(['error' => 'Match is not awaiting verification'], 409);
        }

        $side = (string) $request->input('side');
        if (!in_array($side, ['home', 'away'], true)) {
            return response()->json(['error' => 'side must be home or away'], 422);
        }

        $match = MatchVerificationService::confirmByCaptain($match, $side);

        return response()->json(['message' => 'Confirmation recorded', 'data' => $match->fresh()]);
    }

    /**
     * Higher-trust verification: organizer (High) or Haraan venue (Verified).
     */
    public function verify(Request $request, string $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }

        $method = (string) $request->input('method', 'organizer');

        if ($method === 'organizer') {
            if (!ReputationService::canVerifyResults($authUser)) {
                return response()->json([
                    'error' => 'You are not an authorized organizer, or your trust score is too low to verify results.',
                ], 403);
            }
            $match = MatchVerificationService::verifyByOrganizer($match, $authUser);
        } elseif ($method === 'venue') {
            // Verify against a real booking: prefer the explicit one, else the
            // booking attached to the match. Caller must own it (or be organizer).
            $bookingId = $request->integer('bookingId') ?: (int) $match->venue_booking_id;
            $booking = VenueVerificationService::findValidBooking($bookingId);
            if ($booking === null) {
                return response()->json(['error' => 'No valid Haraan booking for this match'], 422);
            }
            if ((int) $booking->user_id !== (int) $authUser->id && !ReputationService::canOrganize($authUser)) {
                return response()->json(['error' => 'You cannot verify this venue booking'], 403);
            }
            $match = MatchVerificationService::verifyByVenue($match, (int) $booking->id);
        } else {
            return response()->json(['error' => 'method must be organizer or venue'], 422);
        }

        return response()->json(['message' => 'Match verified', 'data' => $match->fresh()]);
    }

    /**
     * File a dispute against a match, penalizing the target player's trust.
     * Body: targetPlayerId, type (default match_dispute), reason.
     */
    public function dispute(Request $request, string $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }

        $targetPlayerId = (string) $request->input('targetPlayerId');
        if ($targetPlayerId === '') {
            return response()->json(['error' => 'targetPlayerId is required'], 422);
        }

        $type = (string) $request->input('type', 'match_dispute');
        if (!ActionboardXp::isValidPenalty($type)) {
            return response()->json(['error' => 'Invalid penalty type'], 422);
        }

        $event = ReputationService::penalize(
            playerId: $targetPlayerId,
            type: $type,
            reason: $request->input('reason'),
            matchId: (int) $match->id,
            reportedBy: (int) $authUser->id,
        );

        if ($event === null) {
            return response()->json(['error' => 'Could not record dispute'], 422);
        }

        $target = User::where('player_id', $targetPlayerId)->first();

        return response()->json([
            'message'         => 'Dispute recorded',
            'player_id'       => $targetPlayerId,
            'penalty'         => $event->amount,
            'new_trust_score' => $target?->trust_score,
        ]);
    }

    /**
     * Normalize wizard squad input (plain names or {id,name}) into the stored
     * {id, name} shape. Names that match a registered player_id are resolved.
     */
    /**
     * Derive a short, neat team code from a full name. Multi-word names become initials
     * ("Royal Challengers" → "RC"); single words take their first 3 letters
     * ("keerthipalle" → "KEE"). Used for compact displays so long names don't crowd the UI.
     */
    private static function shortName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $words = preg_split('/\s+/', $name) ?: [];
        if (count($words) >= 2) {
            $initials = '';
            foreach ($words as $w) {
                $c = preg_replace('/[^a-zA-Z0-9]/', '', $w);
                if ($c !== '') {
                    $initials .= strtoupper($c[0]);
                }
                if (strlen($initials) >= 3) {
                    break;
                }
            }
            if ($initials !== '') {
                return $initials;
            }
        }
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        return strtoupper(substr($clean, 0, 3));
    }

    private static function normalizeSquad(array $squad): array
    {
        // Collect candidate names/ids
        $rawNames = [];
        foreach ($squad as $p) {
            if (is_array($p)) {
                $rawNames[] = (string) ($p['name'] ?? $p['id'] ?? '');
            } else {
                $rawNames[] = (string) $p;
            }
        }
        $rawNames = array_values(array_filter(array_map('trim', $rawNames)));

        if ($rawNames === []) {
            return [];
        }

        // Resolve any that match a registered player_id (so Ranked rules apply).
        $registered = User::query()
            ->whereIn('player_id', $rawNames)
            ->get()
            ->keyBy('player_id');

        $resolved = [];
        foreach ($squad as $p) {
            $name = is_array($p) ? (string) ($p['name'] ?? '') : (string) $p;
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $explicitId = is_array($p) ? ($p['id'] ?? null) : null;

            if ($explicitId) {
                $resolved[] = ['id' => $explicitId, 'name' => $name];
            } elseif ($registered->has($name)) {
                $resolved[] = ['id' => $name, 'name' => $registered[$name]->name];
            } else {
                $resolved[] = ['id' => null, 'name' => $name];
            }
        }

        return $resolved;
    }

    public function scoreAction(Request $request, string $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }

        if ((int) $match->user_id !== (int) $authUser->id) {
            return response()->json(['error' => 'Unauthorized: Only the creator can score this match.'], 403);
        }

        $status = strtolower((string) $match->status);
        if ($status === 'completed') {
            return response()->json(['error' => 'Match is completed and locked.'], 422);
        }

        $type = (string) $request->input('type');
        if ($type === '') {
            return response()->json(['error' => 'Action type is required'], 422);
        }

        if ($type !== 'start' && $type !== 'undo' && $status !== 'live') {
            return response()->json(['error' => 'Match is not live.'], 422);
        }

        $overs = $match->overs ?? '0.0';
        $parts = explode('.', $overs);
        $overNum = (int) ($parts[0] ?? 0);
        $ballNum = (int) ($parts[1] ?? 0);

        $inningsCount = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->where('action_type', 'start')
            ->count();
        $currentInnings = $inningsCount > 0 ? $inningsCount : 1;

        if ($type === 'start') {
            $currentInnings = (int) $request->input('innings', $inningsCount + 1);
        }

        // Enforce the match over-limit: once the innings has bowled its full quota of
        // legal balls, no further deliveries are accepted (this is what stopped a
        // "20 over" innings running on to 22.1). `overs` resets to 0.0 each innings.
        $deliveryTypes = ['runs', 'wide', 'noball', 'bye', 'legbye', 'wicket'];
        if (in_array($type, $deliveryTypes, true)) {
            $maxOvers = self::maxOversFor($match);
            if ($maxOvers > 0 && self::oversToBalls((string) ($match->overs ?? '0.0')) >= $maxOvers * 6) {
                return response()->json([
                    'error' => "Innings complete — {$maxOvers} overs bowled.",
                ], 422);
            }
        }

        if ($type === 'undo') {
            $lastAction = DB::table('match_actions')
                ->where('match_id', $match->id)
                ->orderByDesc('id')
                ->first();

            if ($lastAction) {
                DB::table('match_actions')->where('id', $lastAction->id)->delete();
            }

            $this->rebuildMatchState($match);
        } else {
            DB::table('match_actions')->insert([
                'match_id' => $match->id,
                'innings' => $currentInnings,
                'over_number' => $overNum,
                'ball_number' => $ballNum,
                'action_type' => $type,
                'payload' => json_encode($request->all()),
                'version' => 1,
                'created_by' => $authUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            self::applyAction($match, $type, $request->all());
            // End the match the moment the chase is won / the 2nd innings is over.
            self::maybeCompleteMatch($match, $currentInnings);
            $match->save();
        }

        return response()->json([
            'message' => 'Score updated successfully',
            'data'    => $match->fresh(),
        ]);
    }

    /**
     * Close out a chase: in the 2nd innings, finish the match as soon as the chasing
     * side passes the target, or when its overs/wickets run out. Sets a human result
     * string on `status` so the app stops "LIVE" and shows the outcome.
     */
    private static function maybeCompleteMatch(LiveMatch $match, int $inningsCount): void
    {
        if ($inningsCount < 2) {
            return; // 1st innings ends via the over-limit guard, not a chase result.
        }
        if (strtolower((string) $match->status) === 'completed') {
            return;
        }

        $overSummary = is_array($match->over_summary) ? $match->over_summary : [];
        $battingTeam = 1;
        if (!empty($overSummary)) {
            $last = end($overSummary);
            $battingTeam = (($last['batting'] ?? 'home') === 'away') ? 2 : 1;
        }
        $chasing = $battingTeam === 2 ? (int) $match->away_score : (int) $match->home_score;
        $defending = $battingTeam === 2 ? (int) $match->home_score : (int) $match->away_score;
        $chasingName = (string) ($battingTeam === 2 ? ($match->away_full ?: $match->away) : ($match->home_full ?: $match->home));
        $defendingName = (string) ($battingTeam === 2 ? ($match->home_full ?: $match->home) : ($match->away_full ?: $match->away));

        $maxOvers = self::maxOversFor($match);
        $ballsBowled = self::oversToBalls((string) ($match->overs ?? '0.0'));
        $wickets = self::countInningsWickets($match, $inningsCount);

        if ($chasing > $defending) {
            $wktsLeft = max(0, 10 - $wickets);
            $match->status = "$chasingName won by $wktsLeft " . ($wktsLeft === 1 ? 'wicket' : 'wickets');
            return;
        }

        $inningsDone = ($maxOvers > 0 && $ballsBowled >= $maxOvers * 6) || $wickets >= 10;
        if ($inningsDone) {
            $match->status = match (true) {
                $chasing === $defending => 'Match tied',
                default => "$defendingName won by " . ($defending - $chasing) . ' runs',
            };
        }
    }

    /** Count wickets that fell in a given innings from the action log. */
    private static function countInningsWickets(LiveMatch $match, int $innings): int
    {
        return (int) DB::table('match_actions')
            ->where('match_id', $match->id)
            ->where('innings', $innings)
            ->where('action_type', 'wicket')
            ->count();
    }

    private function rebuildMatchState(LiveMatch $match): void
    {
        $match->status = 'Scheduled';
        $match->home_score = 0;
        $match->away_score = 0;
        $match->overs = '0.0';
        $match->crr = '0.00';
        $match->batters = [];
        $match->bowler = [];
        $match->over_summary = [];
        $match->timeline = [];
        $match->save();

        $actions = DB::table('match_actions')
            ->where('match_id', $match->id)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($actions as $act) {
            $payload = json_decode($act->payload, true) ?: [];
            self::applyAction($match, $act->action_type, $payload);
        }

        $match->save();
    }

    public static function applyAction(LiveMatch $match, string $type, array $payload): void
    {
        if ($type === 'start') {
            $battingTeam = (int) ($payload['batting_team'] ?? 1);
            $strikerId = $payload['striker_id'] ?? null;
            $nonStrikerId = $payload['non_striker_id'] ?? null;
            $bowlerId = $payload['bowler_id'] ?? null;

            $strikerName = self::findPlayerName($match, $strikerId);
            $nonStrikerName = self::findPlayerName($match, $nonStrikerId);
            $bowlerName = self::findPlayerName($match, $bowlerId);

            $match->status = 'Live';
            // Persist the toss outcome (e.g. "Team A • Bat") so the hero/info show it.
            if (!empty($payload['decision'])) {
                $match->decision = (string) $payload['decision'];
            }
            $match->batters = [
                ['id' => $strikerId, 'name' => $strikerName, 'runs' => 0, 'balls' => 0],
                ['id' => $nonStrikerId, 'name' => $nonStrikerName, 'runs' => 0, 'balls' => 0]
            ];
            $match->bowler = [
                'id' => $bowlerId,
                'name' => $bowlerName,
                'runs' => 0,
                'wickets' => 0,
                'overs' => '0.0',
                'maidens' => 0,
                'figures' => '0-0'
            ];
            $match->over_summary = [
                [
                    'over' => 1,
                    'runs' => 0,
                    'balls' => [],
                    'batting' => $battingTeam === 2 ? 'away' : 'home'
                ]
            ];
            $match->overs = '0.0';
            $match->crr = '0.00';
            return;
        }

        if ($type === 'change_bowler') {
            $bowlerId = $payload['bowler_id'] ?? null;
            $bowlerName = self::findPlayerName($match, $bowlerId);

            // A fresh bowler comes on with their own (zeroed) spell figures, not the
            // outgoing bowler's. The model tracks one current bowler, so reset here.
            $match->bowler = [
                'id' => $bowlerId,
                'name' => $bowlerName,
                'runs' => 0,
                'wickets' => 0,
                'overs' => '0.0',
                'maidens' => 0,
                'figures' => '0-0',
            ];

            $overSummary = $match->over_summary ?? [];
            if (!empty($overSummary)) {
                $lastOver = end($overSummary);
                $legalBalls = self::countLegalBalls($lastOver['balls'] ?? []);
                if ($legalBalls >= 6) {
                    $newOverNum = count($overSummary) + 1;
                    $batting = $lastOver['batting'] ?? 'home';
                    $overSummary[] = [
                        'over' => $newOverNum,
                        'runs' => 0,
                        'balls' => [],
                        'batting' => $batting
                    ];
                    $match->over_summary = $overSummary;
                }
            }
            return;
        }

        if ($type === 'change_batsman') {
            $role = $payload['role'] ?? 'striker';
            $batsmanId = $payload['id'] ?? null;
            $batsmanName = self::findPlayerName($match, $batsmanId);

            $batters = $match->batters ?? [];
            if ($role === 'striker') {
                $batters[0] = ['id' => $batsmanId, 'name' => $batsmanName, 'runs' => 0, 'balls' => 0];
            } else {
                $batters[1] = ['id' => $batsmanId, 'name' => $batsmanName, 'runs' => 0, 'balls' => 0];
            }
            $match->batters = $batters;
            return;
        }

        $isLegal = true;
        $runsOffBat = 0;
        $extras = 0;
        $wicket = false;
        $ballLabel = '';

        if ($type === 'runs') {
            $runsOffBat = (int) ($payload['value'] ?? 0);
            $ballLabel = (string) $runsOffBat;
        } elseif ($type === 'wide') {
            $isLegal = false;
            $extras = (int) ($payload['value'] ?? 1);
            $ballLabel = 'WD';
        } elseif ($type === 'noball') {
            $isLegal = false;
            $runsOffBat = (int) ($payload['runs_off_bat'] ?? 0);
            $extras = 1;
            $ballLabel = 'NB';
        } elseif ($type === 'bye') {
            $extras = (int) ($payload['value'] ?? 1);
            $ballLabel = 'BYE';
        } elseif ($type === 'legbye') {
            $extras = (int) ($payload['value'] ?? 1);
            $ballLabel = 'LB';
        } elseif ($type === 'wicket') {
            $wicket = true;
            $ballLabel = 'W';
        }

        $overSummary = $match->over_summary ?? [];
        $battingTeam = 1;
        if (!empty($overSummary)) {
            $lastOver = end($overSummary);
            $battingTeam = ($lastOver['batting'] ?? 'home') === 'away' ? 2 : 1;
        }

        $totalRuns = $runsOffBat + $extras;

        if ($battingTeam === 2) {
            $match->away_score += $totalRuns;
        } else {
            $match->home_score += $totalRuns;
        }

        $batters = $match->batters ?? [];
        if (!empty($batters)) {
            $striker = $batters[0] ?? null;
            if ($striker) {
                if ($type !== 'wide') {
                    $striker['runs'] += $runsOffBat;
                    $striker['balls'] += 1;
                }
                $batters[0] = $striker;
            }
        }

        $bowler = $match->bowler ?? [];
        if (!empty($bowler)) {
            if ($type !== 'bye' && $type !== 'legbye') {
                $bowler['runs'] += $totalRuns;
            }
            if ($isLegal) {
                $bowler['overs'] = self::addBallToOvers((string) ($bowler['overs'] ?? '0.0'));
            }
            if ($wicket) {
                $bowler['wickets'] += 1;
            }
            $bowler['figures'] = $bowler['wickets'] . '-' . $bowler['runs'];
            $match->bowler = $bowler;
        }

        if (!empty($overSummary)) {
            $lastIdx = count($overSummary) - 1;
            $balls = $overSummary[$lastIdx]['balls'] ?? [];
            $balls[] = $ballLabel;
            $overSummary[$lastIdx]['balls'] = $balls;
            $overSummary[$lastIdx]['runs'] += $totalRuns;
            $match->over_summary = $overSummary;
        }

        if ($isLegal) {
            $match->overs = self::addBallToOvers($match->overs);
        }

        $runsToSwap = $runsOffBat;
        if ($type === 'bye' || $type === 'legbye') {
            $runsToSwap = $extras;
        }
        if ($runsToSwap % 2 !== 0 && !empty($batters) && count($batters) >= 2) {
            $temp = $batters[0];
            $batters[0] = $batters[1];
            $batters[1] = $temp;
        }

        if ($wicket) {
            $newBatsmanId = $payload['new_batsman_id'] ?? null;
            $newBatsmanName = self::findPlayerName($match, $newBatsmanId);
            $batters[0] = ['id' => $newBatsmanId, 'name' => $newBatsmanName, 'runs' => 0, 'balls' => 0];
        }

        if ($isLegal && !empty($overSummary)) {
            $lastOver = end($overSummary);
            $legalBalls = self::countLegalBalls($lastOver['balls'] ?? []);
            if ($legalBalls >= 6 && count($batters) >= 2) {
                $temp = $batters[0];
                $batters[0] = $batters[1];
                $batters[1] = $temp;
            }
        }

        $match->batters = $batters;

        $oversFloat = self::oversToBalls($match->overs) / 6.0;
        if ($oversFloat > 0) {
            $currentScore = $battingTeam === 2 ? $match->away_score : $match->home_score;
            $match->crr = sprintf("%.2f", $currentScore / $oversFloat);
        } else {
            $match->crr = '0.00';
        }
    }

    private static function findPlayerName(LiveMatch $match, $id): string
    {
        if (empty($id) || strtolower((string) $id) === 'null') {
            return 'Guest Player';
        }
        $homeSquad = $match->home_squad ?? [];
        foreach ($homeSquad as $p) {
            if (isset($p['id']) && $p['id'] == $id) {
                return $p['name'] ?? '';
            }
        }
        $awaySquad = $match->away_squad ?? [];
        foreach ($awaySquad as $p) {
            if (isset($p['id']) && $p['id'] == $id) {
                return $p['name'] ?? '';
            }
        }
        return (string) $id;
    }

    private static function countLegalBalls(array $balls): int
    {
        $count = 0;
        foreach ($balls as $b) {
            $bUpper = strtoupper((string) $b);
            if ($bUpper !== 'WD' && $bUpper !== 'NB') {
                $count++;
            }
        }
        return $count;
    }

    private static function addBallToOvers(string $overs): string
    {
        $parts = explode('.', $overs);
        $ov = (int) ($parts[0] ?? 0);
        $ball = (int) ($parts[1] ?? 0);
        if ($ball >= 5) {
            return ($ov + 1) . '.0';
        } else {
            return $ov . '.' . ($ball + 1);
        }
    }

    private static function oversToBalls(string $overs): int
    {
        $parts = explode('.', $overs);
        $ov = (int) ($parts[0] ?? 0);
        $ball = (int) ($parts[1] ?? 0);
        return ($ov * 6) + $ball;
    }

    /**
     * Per-innings over quota for a match, parsed from the "{n} Over Match" competition
     * label set at creation. Returns 0 when no limit can be determined (unlimited).
     */
    private static function maxOversFor(LiveMatch $match): int
    {
        if (preg_match('/(\d+)/', (string) ($match->competition ?? ''), $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
