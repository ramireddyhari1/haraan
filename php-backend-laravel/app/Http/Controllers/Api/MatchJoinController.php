<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\MatchUpdated;
use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\MatchJoinRequest;
use App\Models\Notification;
use App\Models\User;
use App\Support\MatchProximity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Join a match near me": nearby players discover open matches, request to join, and
 * the owner accepts (slotting them into a squad) or declines. Request-and-approve so
 * the owner keeps control over who's in their game.
 */
final class MatchJoinController extends Controller
{
    /**
     * Open matches near the viewer that are looking for players — public, scheduled
     * (not yet started), open_to_join with slots left, and NOT the viewer's own.
     * Ranked by distance, same as the live feed. GET /api/matches/open
     */
    public function open(Request $request): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        $viewer = $viewer instanceof User ? $viewer : null;
        $near = $this->position($request, $viewer);

        $matches = LiveMatch::query()
            ->where('open_to_join', true)
            ->where('slots_needed', '>', 0)
            ->where('is_private', false)
            ->whereRaw('lower(status) = ?', ['scheduled'])
            ->when($viewer !== null, fn ($q) => $q->where('user_id', '!=', $viewer->id))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $matches = $near->sort($matches)->take(40);

        // The viewer's live request status per match, so the button reads correctly.
        $myRequests = $viewer === null ? collect() : MatchJoinRequest::query()
            ->where('requester_id', $viewer->id)
            ->whereIn('match_id', $matches->pluck('id'))
            ->get()
            ->keyBy('match_id');

        $data = $matches->map(function (LiveMatch $m) use ($near, $myRequests): array {
            $req = $myRequests->get($m->id);
            return [
                'id'          => (string) $m->id,
                'sport'       => strtolower((string) ($m->sport ?: 'cricket')),
                'team1'       => (string) $m->home,
                'team2'       => (string) $m->away,
                'team1Emblem' => (string) ($m->home_emblem ?? ''),
                'team2Emblem' => (string) ($m->away_emblem ?? ''),
                'venue'       => (string) ($m->venue ?? ''),
                'locality'    => (string) ($m->locality ?? ''),
                'competition' => (string) ($m->competition ?? ''),
                'slotsNeeded' => (int) $m->slots_needed,
                'scheduledAt' => $m->scheduled_at?->toIso8601String(),
                'distanceKm'  => $this->roundKm($near->distanceKm($m)),
                // none | pending | accepted | declined
                'myStatus'    => $req?->status ?? 'none',
            ];
        })->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Send a request to join an open match. POST /api/matches/{id}/join
     */
    public function requestJoin(Request $request, string $id): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        if (!$viewer instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $data = $request->validate(['message' => ['nullable', 'string', 'max:200']]);

        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }
        if ((int) $match->user_id === (int) $viewer->id) {
            return response()->json(['error' => "It's your own match."], 422);
        }
        if ($match->is_private || !$match->open_to_join || (int) $match->slots_needed <= 0) {
            return response()->json(['error' => 'This match is not open to join.'], 422);
        }
        if (strtolower((string) $match->status) !== 'scheduled') {
            return response()->json(['error' => 'This match has already started.'], 422);
        }

        $existing = MatchJoinRequest::query()
            ->where('match_id', $match->id)
            ->where('requester_id', $viewer->id)
            ->where('status', MatchJoinRequest::PENDING)
            ->first();
        if ($existing !== null) {
            return response()->json(['message' => 'Request already sent', 'data' => $existing], 200);
        }

        $req = MatchJoinRequest::query()->create([
            'match_id'     => $match->id,
            'requester_id' => $viewer->id,
            'message'      => $data['message'] ?? null,
            'status'       => MatchJoinRequest::PENDING,
        ]);

        // Nudge the owner's open apps to refresh their requests inbox.
        MatchUpdated::dispatch($match->id);
        // Push + bell: tell the owner a player wants in.
        $this->notifyUser(
            (int) $match->user_id,
            'New join request',
            trim(($viewer->name ?: 'A player') . " wants to join {$match->home} vs {$match->away}"),
        );

        return response()->json(['message' => 'Request sent', 'data' => $req], 201);
    }

    /**
     * Withdraw the viewer's own pending request. DELETE /api/matches/{id}/join
     */
    public function cancelJoin(Request $request, string $id): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        if (!$viewer instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        MatchJoinRequest::query()
            ->where('match_id', $id)
            ->where('requester_id', $viewer->id)
            ->where('status', MatchJoinRequest::PENDING)
            ->update(['status' => MatchJoinRequest::CANCELLED, 'responded_at' => now()]);

        return response()->json(['message' => 'Request withdrawn']);
    }

    /**
     * The owner's incoming pending requests across all their matches, newest first.
     * GET /api/matches/join-requests
     */
    public function incoming(Request $request): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        if (!$viewer instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $myMatchIds = LiveMatch::query()->where('user_id', $viewer->id)->pluck('id');
        $requests = MatchJoinRequest::query()
            ->with('requester:id,name,player_id,avatar,trust_score')
            ->whereIn('match_id', $myMatchIds)
            ->where('status', MatchJoinRequest::PENDING)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $matches = LiveMatch::query()->whereIn('id', $requests->pluck('match_id'))->get()->keyBy('id');

        $data = $requests->map(function (MatchJoinRequest $r) use ($matches): array {
            $m = $matches->get($r->match_id);
            $u = $r->requester;
            return [
                'id'          => (string) $r->id,
                'matchId'     => (string) $r->match_id,
                'matchTitle'  => $m ? ($m->home . ' vs ' . $m->away) : '',
                'message'     => (string) ($r->message ?? ''),
                'createdAt'   => $r->created_at?->toIso8601String(),
                'playerId'    => (string) ($u->player_id ?? ''),
                'playerName'  => (string) ($u->name ?? 'Player'),
                'playerAvatar'=> (string) ($u->avatar ?? ''),
                'trustScore'  => (int) ($u->trust_score ?? 0),
            ];
        })->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Owner accepts (slots the player into a squad) or declines a request.
     * POST /api/matches/join-requests/{id}/respond   { action: accept|decline, side? }
     */
    public function respond(Request $request, string $id): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        if (!$viewer instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $data = $request->validate([
            'action' => ['required', 'in:accept,decline'],
            'side'   => ['nullable', 'in:home,away'],
        ]);

        $req = MatchJoinRequest::query()->with('requester:id,name,player_id')->find($id);
        if ($req === null) {
            return response()->json(['error' => 'Request not found'], 404);
        }
        $match = LiveMatch::query()->find($req->match_id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found'], 404);
        }
        if ((int) $match->user_id !== (int) $viewer->id) {
            return response()->json(['error' => 'Only the match creator can respond.'], 403);
        }
        if ($req->status !== MatchJoinRequest::PENDING) {
            return response()->json(['error' => 'This request is already resolved.'], 422);
        }

        if ($data['action'] === 'decline') {
            $req->update(['status' => MatchJoinRequest::DECLINED, 'responded_at' => now()]);
            $this->notifyUser(
                (int) $req->requester_id,
                'Join request update',
                "Your request to join {$match->home} vs {$match->away} wasn't accepted this time.",
            );
            return response()->json(['message' => 'Declined', 'data' => $req]);
        }

        // Accept: slot the player into a squad and consume a slot.
        DB::transaction(function () use ($req, $match, $data): void {
            $home = is_array($match->home_squad) ? $match->home_squad : [];
            $away = is_array($match->away_squad) ? $match->away_squad : [];
            // Default to the side with fewer players so teams stay balanced.
            $side = $data['side'] ?? (count($home) <= count($away) ? 'home' : 'away');

            $u = $req->requester;
            $entry = ['id' => $u->player_id ?: (string) $u->id, 'name' => $u->name ?: 'Player'];
            if ($side === 'home') {
                $home[] = $entry;
                $match->home_squad = $home;
            } else {
                $away[] = $entry;
                $match->away_squad = $away;
            }
            $match->slots_needed = max(0, (int) $match->slots_needed - 1);
            if ($match->slots_needed === 0) {
                $match->open_to_join = false; // full — stop showing it in discovery
            }
            $match->save();

            $req->update([
                'status'       => MatchJoinRequest::ACCEPTED,
                'side'         => $side,
                'responded_at' => now(),
            ]);
        });

        MatchUpdated::dispatch($match->id);
        // Push + bell: tell the player they're in.
        $this->notifyUser(
            (int) $req->requester_id,
            "You're in! 🎉",
            "Your request to join {$match->home} vs {$match->away} was accepted.",
        );

        return response()->json(['message' => 'Accepted', 'data' => $req->fresh()]);
    }

    /**
     * What a private match's share code opens, before the player picks a side.
     * GET /api/matches/join-by-code/{code}
     *
     * The code is the invitation — a private match never appears in discovery, so the
     * only way to hold its code is to have been given it by the creator.
     */
    public function codePreview(Request $request, string $code): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        if (!$viewer instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $match = LiveMatch::byJoinCode($code)->where('is_private', true)->first();
        if ($match === null) {
            return response()->json(['error' => 'No match found for that code.'], 404);
        }

        return response()->json(['data' => $this->codeMatchPayload($match, $viewer)]);
    }

    /**
     * Join a private match to PLAY, by its share code. No owner approval: holding the
     * code is the invitation. Takes a same-named guest slot on that side if the scorer
     * already typed the player in, otherwise adds them to the end of the squad.
     * POST /api/matches/join-by-code   { code, side: home|away }
     */
    public function joinByCode(Request $request): JsonResponse
    {
        $viewer = $request->attributes->get('auth_user');
        if (!$viewer instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'side' => ['required', 'in:home,away'],
        ]);

        $match = LiveMatch::byJoinCode($data['code'])->where('is_private', true)->first();
        if ($match === null) {
            return response()->json(['error' => 'No match found for that code.'], 404);
        }
        if ((int) $match->user_id === (int) $viewer->id) {
            return response()->json(['error' => "It's your own match — you're already in it."], 422);
        }
        if ($match->isFinished()) {
            return response()->json(['error' => 'This match has already finished.'], 422);
        }
        $pid = trim((string) ($viewer->player_id ?? ''));
        if ($pid === '') {
            return response()->json([
                'error' => 'Complete your ActionBoard player profile first.',
                'code'  => 'profile_incomplete',
            ], 403);
        }

        $side = $data['side'];
        $name = trim((string) ($viewer->name ?: 'Player'));

        $joined = DB::transaction(function () use ($match, $side, $pid, $name): bool {
            $match = LiveMatch::query()->lockForUpdate()->find($match->id);
            $home = is_array($match->home_squad) ? $match->home_squad : [];
            $away = is_array($match->away_squad) ? $match->away_squad : [];

            foreach (array_merge($home, $away) as $p) {
                if (is_array($p) && (string) ($p['id'] ?? '') === $pid) {
                    return false; // already in — idempotent, never a second copy
                }
            }

            $squad = $side === 'home' ? $home : $away;
            $claimed = false;
            foreach ($squad as $i => $p) {
                $entryName = is_array($p) ? trim((string) ($p['name'] ?? '')) : trim((string) $p);
                $entryId = is_array($p) ? ($p['id'] ?? null) : null;
                if (empty($entryId) && $entryName !== '' && mb_strtolower($entryName) === mb_strtolower($name)) {
                    $squad[$i] = array_merge(is_array($p) ? $p : [], ['id' => $pid, 'name' => $entryName]);
                    $claimed = true;
                    break;
                }
            }
            if (!$claimed) {
                $squad[] = ['id' => $pid, 'name' => $name];
            }

            if ($side === 'home') {
                $match->home_squad = $squad;
            } else {
                $match->away_squad = $squad;
            }
            $match->save();

            return true;
        });

        $match->refresh();
        if ($joined) {
            MatchUpdated::dispatch($match->id);
            $team = $side === 'home' ? $match->home : $match->away;
            $this->notifyUser(
                (int) $match->user_id,
                'Player joined',
                "{$name} joined {$team} in {$match->home} vs {$match->away} with your match code.",
            );
        }

        return response()->json([
            'message' => $joined ? 'Joined' : 'Already in this match',
            'data'    => $this->codeMatchPayload($match, $viewer),
        ], $joined ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private function codeMatchPayload(LiveMatch $match, User $viewer): array
    {
        $home = is_array($match->home_squad) ? $match->home_squad : [];
        $away = is_array($match->away_squad) ? $match->away_squad : [];
        $pid = (string) ($viewer->player_id ?? '');
        $sideOf = static function (array $squad) use ($pid): bool {
            if ($pid === '') {
                return false;
            }
            foreach ($squad as $p) {
                if (is_array($p) && (string) ($p['id'] ?? '') === $pid) {
                    return true;
                }
            }
            return false;
        };

        return [
            'matchId'    => (string) $match->id,
            'sport'      => strtolower((string) ($match->sport ?: 'cricket')),
            'home'       => (string) $match->home,
            'away'       => (string) $match->away,
            'homeEmblem' => (string) ($match->home_emblem ?? ''),
            'awayEmblem' => (string) ($match->away_emblem ?? ''),
            'homeCount'  => count($home),
            'awayCount'  => count($away),
            'venue'      => (string) ($match->venue ?? ''),
            'status'     => (string) ($match->status ?? ''),
            'finished'   => $match->isFinished(),
            'isOwner'    => (int) $match->user_id === (int) $viewer->id,
            'mySide'     => $sideOf($home) ? 'home' : ($sideOf($away) ? 'away' : null),
        ];
    }

    // ── helpers ──

    /**
     * Notify one user — writes a bell-inbox row targeted at them (audience_type=user),
     * which the Notification model also fans out to their devices via FCM when the
     * server has push credentials. Never lets a notification failure break the action.
     */
    private function notifyUser(int $userId, string $title, string $body): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            Notification::create([
                'title'          => $title,
                'body'           => $body,
                'audience_type'  => 'user',
                'audience_value' => (string) $userId,
                'status'         => 'sent',
                'deep_link'      => 'haraan://matches/scheduled',
            ]);
        } catch (\Throwable $e) {
            // best-effort — the join action already succeeded.
        }
    }

    private function position(Request $request, ?User $viewer): MatchProximity
    {
        $num = static fn ($v): ?float => is_numeric($v) ? (float) $v : null;
        $lat = $num($request->query('lat'));
        $lng = $num($request->query('lng'));
        return new MatchProximity(
            latitude: ($lat !== null && $lat >= -90 && $lat <= 90) ? $lat : null,
            longitude: ($lng !== null && $lng >= -180 && $lng <= 180) ? $lng : null,
            locality: (string) ($request->query('locality') ?? ''),
            district: (string) ($request->query('district') ?: ($viewer->district ?? '')),
            state: (string) ($request->query('state') ?: ($viewer->state ?? '')),
        );
    }

    private function roundKm(?float $km): ?float
    {
        return $km === null ? null : round($km, 1);
    }
}
