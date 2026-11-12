<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\MatchCommentarySession;
use App\Models\User;
use App\Services\MatchCommentary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live commentary over HTTP. The voice goes phone → LiveKit → phones; these endpoints only hand
 * out passes and keep the record.
 *
 * Scorer side (auth.jwt + actionboard.profile, under /api/matches): go on air, heartbeat, stop.
 * Listener side (optional auth, under /api/live-matches): get a listen-only pass, report.
 */
final class MatchCommentaryController extends Controller
{
    public function __construct(private readonly MatchCommentary $commentary) {}

    /** POST /api/matches/{id}/commentary */
    public function start(Request $request, string $id): JsonResponse
    {
        $user = $this->user($request);
        $match = LiveMatch::query()->find($id);
        if ($match === null) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        return response()->json($this->commentary->start($user, $match), 201);
    }

    /** POST /api/matches/{id}/commentary/{session}/heartbeat  {muted: bool} */
    public function heartbeat(Request $request, string $id, string $session): JsonResponse
    {
        $row = $this->ownSession($request, $id, $session);
        if ($row === null) {
            return response()->json(['error' => 'Broadcast not found.'], 404);
        }

        return response()->json($this->commentary->heartbeat($row, $request->boolean('muted')));
    }

    /** DELETE /api/matches/{id}/commentary/{session}  {reason: commentator|silence} */
    public function stop(Request $request, string $id, string $session): JsonResponse
    {
        $row = $this->ownSession($request, $id, $session);
        if ($row === null) {
            return response()->json(['error' => 'Broadcast not found.'], 404);
        }

        $reason = $request->input('reason') === 'silence' ? 'silence' : 'commentator';
        $this->commentary->end($row, $reason);

        return response()->json(['session' => $row->id, 'onAir' => false, 'reason' => $row->ended_reason]);
    }

    /** POST /api/live-matches/{id}/commentary/listen */
    public function listen(Request $request, string $id): JsonResponse
    {
        $match = LiveMatch::query()->find($id);
        $viewer = $this->viewer($request);
        if ($match === null || ! $match->isVisibleTo($viewer)) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        return response()->json($this->commentary->listen($match, $this->listenerKey($request)));
    }

    /** POST /api/live-matches/code/{code}/commentary/listen */
    public function listenByCode(Request $request, string $code): JsonResponse
    {
        $match = LiveMatch::byJoinCode($code)->where('is_private', true)->first();
        if ($match === null) {
            return response()->json(['error' => 'No match found for that code.'], 404);
        }

        return response()->json($this->commentary->listen($match, $this->listenerKey($request)));
    }

    /** POST /api/live-matches/commentary/{session}/report  {reason, details?} */
    public function report(Request $request, string $session): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:24'],
            'details' => ['nullable', 'string', 'max:300'],
        ]);

        $row = MatchCommentarySession::query()->find($session);
        $viewer = $this->viewer($request);
        // Report what you could have heard: a match this person can see, or a private match
        // (whose listeners only ever hold the code).
        if ($row === null || $row->match === null || (! $row->match->is_private && ! $row->match->isVisibleTo($viewer))) {
            return response()->json(['error' => 'Broadcast not found.'], 404);
        }

        return response()->json($this->commentary->report(
            $row, $viewer, $this->listenerKey($request), (string) $data['reason'], $data['details'] ?? null,
        ));
    }

    private function user(Request $request): User
    {
        $user = $this->viewer($request);
        abort_if($user === null, 401, 'Unauthorized');

        return $user;
    }

    private function viewer(Request $request): ?User
    {
        $user = $request->attributes->get('auth_user');

        return $user instanceof User ? $user : null;
    }

    private function ownSession(Request $request, string $matchId, string $sessionId): ?MatchCommentarySession
    {
        return MatchCommentarySession::query()
            ->whereKey($sessionId)
            ->where('match_id', $matchId)
            ->where('user_id', $this->user($request)->id)
            ->first();
    }

    /** Same keying as the "watching" presence: user id, else install id hash, else ip+ua hash. */
    private function listenerKey(Request $request): string
    {
        $viewer = $this->viewer($request);
        if ($viewer !== null) {
            return 'u:'.$viewer->id;
        }

        $client = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($request->input('viewer') ?? '')) ?? '';
        if ($client !== '') {
            return 'd:'.substr(hash('sha256', $client), 0, 40);
        }

        return 'a:'.substr(hash('sha256', $request->ip().'|'.(string) $request->userAgent()), 0, 40);
    }
}
