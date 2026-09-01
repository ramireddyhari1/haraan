<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveMatch;
use App\Models\MatchDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Jobs\ReviewMatchClip;
use App\Services\DeliveryReview;
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
        // THE REVIEW CONTRACT: Full HD, up to ten seconds, up to fifty megabytes.
        if ($file->getSize() > DeliveryReview::MAX_REVIEW_BYTES) {
            return response()->json([
                'error' => 'That clip is too large (limit '
                    . DeliveryReview::MAX_REVIEW_MB . 'MB). Record a shorter delivery.',
            ], 422);
        }
        $extension = strtolower((string) $file->getClientOriginalExtension()) ?: 'mp4';
        if (! in_array($extension, ['mp4', 'webm', '3gp'], true)) {
            return response()->json(['error' => 'Unsupported clip format.'], 422);
        }

        // DURATION, read from the file rather than believed.
        //
        // durationMs arrives in the same multipart body as the video, so trusting it lets
        // a client hand us a thirty-second clip labelled as eight. The container states
        // its own length in its mvhd box, which is authoritative and needs no ffprobe.
        //
        // On the one real clip on this machine the two disagree by 1.8 seconds - the
        // phone measures wall-clock from tap to finalise, which is not the same thing as
        // how long the video runs. The container wins.
        //
        // An unreadable duration is refused rather than waved through: a clip whose
        // length cannot be established is exactly the clip a ten-second pipeline must not
        // accept on trust.
        $durationMs = \App\Support\Mp4Probe::durationMs($file->getRealPath());
        if ($durationMs === null) {
            return response()->json([
                'error' => 'That video could not be read. Record the delivery again.',
            ], 422);
        }
        if ($durationMs > DeliveryReview::MAX_REVIEW_SECONDS * 1000) {
            return response()->json([
                'error' => 'That clip is longer than '
                    . DeliveryReview::MAX_REVIEW_SECONDS . ' seconds. Record just the delivery.',
            ], 422);
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
            // The measured length, not the reported one.
            'duration_ms' => $durationMs,
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
                'review' => $c->analysis === null ? null : json_decode((string) $c->analysis, true),
                // Null means nobody has ever asked. The app treats that as "offer the
                // button", which is different from a review that ran and failed.
                'reviewStatus' => $c->review_status,
                'reviewError' => $c->review_error,
            ])
            ->all();

        return response()->json(['data' => $clips]);
    }

    /**
     * Ask for a review of one clip.
     *
     * Returns immediately. The Vertex call happens on a queue, because handing a video
     * model several seconds of footage takes 8 seconds on a good day and the request
     * used to sit through all of it — holding a PHP worker, and behind nginx on a
     * ground's connection simply timing out with nothing to show for the wait.
     *
     * Scorer-only and on demand, as before: reviewing every clip a camera sends would
     * put a video model in the path of a match nobody asked it to judge, and cost a call
     * per ball for footage no one will open.
     */
    public function reviewClip(Request $request, string $id, string $clipId): JsonResponse
    {
        [$match, , $error] = $this->scorerContext($request, $id);
        if ($error !== null) {
            return $error;
        }

        $clip = DB::table('match_device_clips')
            ->where('id', $clipId)
            ->where('match_id', $match->id)
            ->first();
        if ($clip === null) {
            return response()->json(['error' => 'Clip not found.'], 404);
        }

        // Already done. Footage does not change its mind, so neither does the reading.
        if ($clip->analysis !== null) {
            return $this->reviewState($clip->id, DeliveryReview::STATUS_COMPLETED, [
                'review' => json_decode((string) $clip->analysis, true),
                'cached' => true,
            ]);
        }

        // Already running. A second tap must not buy a second Vertex call.
        if (in_array((string) $clip->review_status, [
            DeliveryReview::STATUS_PENDING,
            DeliveryReview::STATUS_PROCESSING,
        ], true)) {
            return $this->reviewState($clip->id, (string) $clip->review_status, [], 202);
        }

        $service = app(DeliveryReview::class);
        if (! $service->isConfigured()) {
            return response()->json(['error' => 'Review is not available on this server.'], 503);
        }

        // Refuse here rather than letting the job discover it: the scorer gets the reason
        // now instead of after a spinner that was always going to fail.
        // The clip is stored and playable; this is only about what can reach the model.
        // Refused here rather than in the job so the scorer gets the reason immediately
        // instead of after a spinner that was always going to fail.
        if ((int) $clip->bytes > DeliveryReview::MAX_VERTEX_INLINE_BYTES) {
            DB::table('match_device_clips')->where('id', $clip->id)->update([
                'review_status' => DeliveryReview::STATUS_FAILED,
                'review_error' => 'This clip plays fine but is too large to analyse (limit '
                    . DeliveryReview::MAX_VERTEX_INLINE_MB . 'MB). Record a shorter delivery to review it.',
                'updated_at' => now(),
            ]);

            return $this->reviewState($clip->id, DeliveryReview::STATUS_FAILED, [], 422);
        }

        DB::table('match_device_clips')->where('id', $clip->id)->update([
            'review_status' => DeliveryReview::STATUS_PENDING,
            'review_error' => null,
            'review_requested_at' => now(),
            'updated_at' => now(),
        ]);

        ReviewMatchClip::dispatch(
            (int) $clip->id,
            (string) $clip->path,
            ((string) $clip->role) === MatchDevice::ROLE_BOWLER ? 'bowler' : 'lbw',
        );

        // With QUEUE_CONNECTION=sync the dispatch above ran inline and the row is already
        // finished, so re-read rather than asserting "pending" at a client that would
        // then poll for a state that has been and gone.
        return $this->reviewState($clip->id, DeliveryReview::STATUS_PENDING, [], 202);
    }

    /**
     * Where a clip's review has got to. Polled by the app while a job runs.
     *
     * Cheap on purpose — one indexed row read, no model, no storage touch — because a
     * phone will call this every couple of seconds for as long as Vertex is thinking.
     */
    public function reviewStatus(Request $request, string $id, string $clipId): JsonResponse
    {
        [$match, , $error] = $this->scorerContext($request, $id);
        if ($error !== null) {
            return $error;
        }

        $clip = DB::table('match_device_clips')
            ->where('id', $clipId)
            ->where('match_id', $match->id)
            ->first();
        if ($clip === null) {
            return response()->json(['error' => 'Clip not found.'], 404);
        }

        return $this->reviewState($clip->id, (string) ($clip->review_status ?? ''));
    }

    /**
     * One shape for every review answer, so the app has a single thing to parse.
     *
     * Re-reads the row rather than trusting the status passed in: on a sync queue the
     * job has already run by the time we get here, and reporting "pending" for a review
     * that finished would send the client into a poll for nothing.
     */
    private function reviewState(int $clipId, string $assumed, array $extra = [], int $code = 200): JsonResponse
    {
        $row = DB::table('match_device_clips')->find($clipId);
        $status = (string) ($row?->review_status ?? $assumed);
        $review = $row?->analysis === null ? null : json_decode((string) $row->analysis, true);

        if ($review !== null) {
            $status = DeliveryReview::STATUS_COMPLETED;
            $code = 200;
        }

        return response()->json(['data' => array_merge([
            'status' => $status === '' ? null : $status,
            'review' => $review,
            // Safe to print: set from DeliveryReview::lastFailure(), never an exception.
            'error' => $row?->review_error,
        ], $extra)], $code);
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
