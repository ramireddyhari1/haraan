<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\MatchDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pairing a second phone to a match.
 *
 * The flow has two halves and they are authorised in completely different ways, which
 * is the whole security design:
 *
 *   · The SCORER's half (open, list, revoke) is JWT-authenticated and restricted to the
 *     match creator. Nobody else may invite a device into someone's match.
 *   · The DEVICE's half (claim, heartbeat) has no account at all — the person holding
 *     the second phone may not even have signed up. There, the TOKEN is the credential:
 *     short-lived and single-use for the claim, long-lived and revocable afterwards.
 *
 * The pairing link deliberately does not contain the match id. A link that leaked would
 * otherwise be a standing invitation into a match that cannot be withdrawn.
 */
final class MatchDeviceController extends Controller
{
    /** Open a pairing session for a role, and hand back the QR payload. */
    public function store(Request $request, string $id): JsonResponse
    {
        [$match, $user, $error] = $this->scorerContext($request, $id);
        if ($error !== null) {
            return $error;
        }

        $role = strtoupper(trim((string) $request->input('role')));
        if (! in_array($role, MatchDevice::ROLES, true)) {
            return response()->json(['error' => 'Unknown device role.'], 422);
        }

        // One live pairing per role: a scorer who taps twice wants the code they are
        // looking at to be the one that works, not a pile of valid codes.
        MatchDevice::where('match_id', $match->id)
            ->where('role', $role)
            ->where('status', MatchDevice::STATUS_PENDING)
            ->update(['status' => MatchDevice::STATUS_REVOKED]);

        $device = MatchDevice::create([
            'match_id' => $match->id,
            'role' => $role,
            'pair_token' => MatchDevice::freshToken(),
            'token_expires_at' => now()->addMinutes(MatchDevice::PAIR_TTL_MINUTES),
            'status' => MatchDevice::STATUS_PENDING,
            'created_by' => $user->id,
        ]);

        return response()->json(['data' => $this->pairingPayload($device)]);
    }

    /** Every device attached to this match, for the scorer's status list. */
    public function index(Request $request, string $id): JsonResponse
    {
        [$match, , $error] = $this->scorerContext($request, $id);
        if ($error !== null) {
            return $error;
        }

        $devices = MatchDevice::where('match_id', $match->id)
            ->where('status', '!=', MatchDevice::STATUS_REVOKED)
            ->orderByDesc('id')
            ->get()
            ->map(fn (MatchDevice $d) => $this->devicePayload($d))
            ->all();

        return response()->json(['data' => $devices]);
    }

    /** Cut a device loose. Its session token stops working on the next request. */
    public function destroy(Request $request, string $id, string $deviceId): JsonResponse
    {
        [$match, , $error] = $this->scorerContext($request, $id);
        if ($error !== null) {
            return $error;
        }

        $device = MatchDevice::where('match_id', $match->id)->find($deviceId);
        if ($device === null) {
            return response()->json(['error' => 'Device not found.'], 404);
        }

        $device->update([
            'status' => MatchDevice::STATUS_REVOKED,
            'session_token' => null,
        ]);

        return response()->json(['data' => ['revoked' => true]]);
    }

    // ─────────────────────────── The second device ───────────────────────────

    /**
     * What is on the other end of this QR code, before anyone joins anything.
     *
     * Deliberately readable without claiming: the person holding the second phone is
     * entitled to see which match and which role they are about to join, and scanning a
     * code should not be the thing that commits them.
     */
    public function preview(string $token): JsonResponse
    {
        $device = MatchDevice::where('pair_token', strtoupper(trim($token)))->first();
        if ($device === null) {
            return response()->json(['error' => 'That pairing code is not valid.'], 404);
        }
        if (! $device->isPairable()) {
            return response()->json([
                'error' => $device->status === MatchDevice::STATUS_CONNECTED
                    ? 'That code has already been used.'
                    : 'That pairing code has expired. Ask the scorer for a new one.',
            ], 410);
        }

        $match = LiveMatch::find($device->match_id);

        return response()->json(['data' => [
            'role' => $device->role,
            'roleLabel' => MatchDevice::friendlyRole($device->role),
            'matchTitle' => $this->matchTitle($match),
            'venue' => (string) ($match?->venue ?? ''),
            'expiresAt' => $device->token_expires_at?->toIso8601String(),
        ]]);
    }

    /**
     * Join the match as that device. Single-use: the code is spent here, and what comes
     * back is the session token the camera uses from then on.
     */
    public function claim(Request $request): JsonResponse
    {
        $token = strtoupper(trim((string) $request->input('token')));
        $device = MatchDevice::where('pair_token', $token)->first();

        if ($device === null) {
            return response()->json(['error' => 'That pairing code is not valid.'], 404);
        }
        if (! $device->isPairable()) {
            return response()->json([
                'error' => $device->status === MatchDevice::STATUS_CONNECTED
                    ? 'That code has already been used.'
                    : 'That pairing code has expired. Ask the scorer for a new one.',
            ], 410);
        }

        $session = MatchDevice::freshSessionToken();
        $device->update([
            'status' => MatchDevice::STATUS_CONNECTED,
            'session_token' => $session,
            'device_name' => mb_substr(trim((string) $request->input('deviceName')), 0, 60) ?: 'Camera phone',
            'device_platform' => mb_substr(trim((string) $request->input('platform')), 0, 40) ?: 'android',
            'connected_at' => now(),
            'last_seen_at' => now(),
            // Spent. The code in the QR cannot be replayed by anyone who photographed it.
            'token_expires_at' => now(),
        ]);

        $match = LiveMatch::find($device->match_id);

        return response()->json(['data' => [
            'sessionToken' => $session,
            'role' => $device->role,
            'roleLabel' => MatchDevice::friendlyRole($device->role),
            'matchId' => (string) $device->match_id,
            'matchTitle' => $this->matchTitle($match),
            'venue' => (string) ($match?->venue ?? ''),
        ]]);
    }

    /**
     * The camera saying it is still there. Also how a paired device learns it has been
     * revoked — it is told to stop rather than left filming for nobody.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = MatchDevice::where('session_token', (string) $request->input('sessionToken'))->first();
        if ($device === null || $device->status !== MatchDevice::STATUS_CONNECTED) {
            return response()->json(['error' => 'This device is no longer paired.'], 401);
        }

        $device->update(['last_seen_at' => now()]);
        $match = LiveMatch::find($device->match_id);

        return response()->json(['data' => [
            'status' => 'connected',
            'role' => $device->role,
            // The camera shows the live score so whoever is holding it knows the match
            // is still going and that they are pointed at the right thing.
            'score' => (string) ($match?->score_text ?? ''),
            'overs' => (string) ($match?->overs ?? ''),
            'matchStatus' => strtolower((string) ($match?->status ?? '')),
        ]]);
    }

    /**
     * A clip from a paired camera.
     *
     * Authorised by the SESSION token, not an account — the phone filming may belong to
     * a friend at the boundary who has never signed up. Revoking the device therefore
     * also stops it uploading, which is the point of having a revocable session at all.
     */
    public function uploadClip(Request $request): JsonResponse
    {
        $device = MatchDevice::where('session_token', (string) $request->input('sessionToken'))->first();
        if ($device === null || $device->status !== MatchDevice::STATUS_CONNECTED) {
            return response()->json(['error' => 'This device is no longer paired.'], 401);
        }

        $file = $request->file('clip');
        if ($file === null || ! $file->isValid()) {
            return response()->json(['error' => 'No clip received.'], 422);
        }
        // A delivery is seconds long. Anything much bigger is a phone uploading its
        // gallery, and a ground connection cannot carry it anyway.
        if ($file->getSize() > 40 * 1024 * 1024) {
            return response()->json(['error' => 'That clip is too large.'], 422);
        }
        $extension = strtolower((string) $file->getClientOriginalExtension()) ?: 'mp4';
        if (! in_array($extension, ['mp4', 'webm', '3gp'], true)) {
            return response()->json(['error' => 'Unsupported clip format.'], 422);
        }

        $path = $file->storeAs(
            'match-clips/' . $device->match_id,
            $device->id . '-' . now()->format('His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension,
            'public',
        );

        $id = DB::table('match_device_clips')->insertGetId([
            'match_id' => $device->match_id,
            'device_id' => $device->id,
            'role' => $device->role,
            'path' => $path,
            'bytes' => (int) $file->getSize(),
            'duration_ms' => (int) $request->input('durationMs', 0),
            'over_ball' => mb_substr(trim((string) $request->input('overBall')), 0, 12) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Uploading counts as being alive; a camera that is sending footage should never
        // be shown to the scorer as lost.
        $device->update(['last_seen_at' => now()]);

        return response()->json(['data' => [
            'id' => (string) $id,
            'url' => Storage::disk('public')->url($path),
        ]]);
    }

    /** Everything the cameras have sent for this match, newest first. Scorer only. */
    public function clips(Request $request, string $id): JsonResponse
    {
        [$match, , $error] = $this->scorerContext($request, $id);
        if ($error !== null) {
            return $error;
        }

        $clips = DB::table('match_device_clips')
            ->where('match_id', $match->id)
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn ($c) => [
                'id' => (string) $c->id,
                'role' => (string) $c->role,
                'roleLabel' => MatchDevice::friendlyRole((string) $c->role),
                'url' => Storage::disk('public')->url((string) $c->path),
                'overBall' => (string) ($c->over_ball ?? ''),
                'durationMs' => (int) $c->duration_ms,
                'recordedAt' => (string) $c->created_at,
            ])
            ->all();

        return response()->json(['data' => $clips]);
    }

    // ─────────────────────────── Shared ───────────────────────────

    /** Never just "Match": the camera phone is being told what it is joining. */
    private function matchTitle(?LiveMatch $match): string
    {
        if ($match === null) {
            return 'Match';
        }
        $title = trim((string) $match->title);
        if ($title !== '') {
            return $title;
        }
        $home = trim((string) ($match->home_full ?: $match->home));
        $away = trim((string) ($match->away_full ?: $match->away));

        return $home !== '' && $away !== '' ? $home . ' vs ' . $away : 'Match';
    }

    /** @return array{0: ?LiveMatch, 1: ?User, 2: ?JsonResponse} */
    private function scorerContext(Request $request, string $id): array
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return [null, null, response()->json(['error' => 'Unauthorized'], 401)];
        }

        $match = LiveMatch::find($id);
        if ($match === null) {
            return [null, null, response()->json(['error' => 'Match not found'], 404)];
        }
        // Only the person scoring the match may attach devices to it.
        if ((int) $match->user_id !== (int) $user->id) {
            return [null, null, response()->json([
                'error' => 'Only the scorer can add devices to this match.',
            ], 403)];
        }

        return [$match, $user, null];
    }

    /** @return array<string,mixed> */
    private function pairingPayload(MatchDevice $device): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'id' => (string) $device->id,
            'role' => $device->role,
            'roleLabel' => MatchDevice::friendlyRole($device->role),
            'token' => $device->pair_token,
            // Both forms of the same thing: the https link is what goes in the QR and
            // survives being pasted into WhatsApp; the scheme link is what the app
            // itself resolves when it is already installed.
            'link' => $base . '/join/camera/' . $device->pair_token,
            'deepLink' => 'haraan://camera/' . $device->pair_token,
            'expiresAt' => $device->token_expires_at?->toIso8601String(),
            'status' => $device->presentStatus(),
        ];
    }

    /** @return array<string,mixed> */
    private function devicePayload(MatchDevice $device): array
    {
        return [
            'id' => (string) $device->id,
            'role' => $device->role,
            'roleLabel' => MatchDevice::friendlyRole($device->role),
            'status' => $device->presentStatus(),
            'deviceName' => (string) ($device->device_name ?? ''),
            'platform' => (string) ($device->device_platform ?? ''),
            'connectedAt' => $device->connected_at?->toIso8601String(),
            'lastSeenAt' => $device->last_seen_at?->toIso8601String(),
        ];
    }
}
