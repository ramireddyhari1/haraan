<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\MatchUpdated;
use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\MatchJoinRequest;
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

        return response()->json(['message' => 'Accepted', 'data' => $req->fresh()]);
    }

    // ── helpers ──

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
