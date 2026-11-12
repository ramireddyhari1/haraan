<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\MatchUpdated;
use App\Models\LiveMatch;
use App\Models\MatchCommentaryReport;
use App\Models\MatchCommentarySession;
use App\Models\User;
use App\Services\Membership\MemberEntitlements;
use App\Support\LiveKit;
use App\Support\Membership\MemberFeature;
use App\Support\PlatformRules;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Live commentary: the scorer goes on air, anyone on the match detail screen can listen.
 *
 * Who may go on air: the match's scorer (its creator — the same rule as score-action), on a
 * plan with `matches.live_commentary`, while the match isn't finished, with commentary on in
 * /control. Listening needs nothing but being able to see the match.
 *
 * Every way a broadcast ends goes through {@see self::end()}, which closes the LiveKit room (so
 * listeners drop at once, not when their token expires) and nudges the match channel so open
 * detail screens drop the speaker icon without waiting for a poll.
 */
final class MatchCommentary
{
    /** How often the commentator's phone reports in. */
    public const HEARTBEAT_SECONDS = 15;

    /** No heartbeat for this long = the phone is gone; the broadcast is over. */
    public const STALE_AFTER_SECONDS = 45;

    public function __construct(private readonly MemberEntitlements $entitlements) {}

    /** Commentary can run at all right now (switch on and an audio server configured). */
    public static function available(): bool
    {
        return PlatformRules::bool('commentary.enabled') && LiveKit::configured();
    }

    /** The live broadcast on this match, or null. Closes one whose phone went quiet. */
    public function current(LiveMatch $match): ?MatchCommentarySession
    {
        $session = MatchCommentarySession::query()
            ->open()
            ->where('match_id', $match->id)
            ->latest('id')
            ->first();

        if ($session === null) {
            return null;
        }

        if (! PlatformRules::bool('commentary.enabled')) {
            $this->end($session, 'switched_off');

            return null;
        }

        $lastSeen = $session->last_heartbeat_at ?? $session->started_at;
        if ($lastSeen === null || $lastSeen->lt(now()->subSeconds(self::STALE_AFTER_SECONDS))) {
            $this->end($session, 'stale');

            return null;
        }

        return $session;
    }

    /**
     * What the match detail payload says about commentary: just enough for the speaker icon.
     * Never a token — that is fetched only when a viewer taps to listen.
     *
     * @return array{onAir: bool, by: string|null, muted: bool}
     */
    public function publicState(LiveMatch $match): array
    {
        $session = ($match->isFinished() || ! self::available()) ? null : $this->current($match);

        return [
            'onAir' => $session !== null,
            'by' => $session?->user?->name,
            'muted' => (bool) ($session?->muted ?? false),
        ];
    }

    /**
     * Put the scorer on air. Any broadcast already open on the match is closed first, so a
     * second phone (or a restart after a crash) never leaves two rooms running.
     *
     * @return array<string, mixed> what the scorer's phone needs to connect
     *
     * @throws CommentaryRefused
     * @throws \App\Services\Membership\EntitlementDenied
     */
    public function start(User $user, LiveMatch $match): array
    {
        if ((int) $match->user_id !== (int) $user->id) {
            throw new CommentaryRefused('Only the match’s scorer can commentate on it.', 'not_scorer', 403);
        }
        if ($match->isFinished()) {
            throw new CommentaryRefused('This match has finished.', 'match_finished', 422);
        }
        if (! PlatformRules::bool('commentary.enabled')) {
            throw new CommentaryRefused('Live commentary is switched off for now.', 'commentary_off', 503);
        }
        if ($match->is_private && ! PlatformRules::bool('commentary.private_matches')) {
            throw new CommentaryRefused('Live commentary isn’t available on private matches.', 'private_match', 422);
        }

        // The plan gate — before any audio server is touched.
        $this->entitlements->authorize($user, MemberFeature::MATCHES_LIVE_COMMENTARY);

        if (! LiveKit::configured()) {
            throw new CommentaryRefused('Live commentary isn’t available right now. Try again later.', 'unavailable', 503);
        }

        MatchCommentarySession::query()->open()->where('match_id', $match->id)->get()
            ->each(fn (MatchCommentarySession $s) => $this->end($s, 'restarted', notify: false));

        $room = 'match-'.$match->id.'-'.Str::lower(Str::random(10));
        $maxListeners = PlatformRules::int('commentary.max_listeners');
        if (! LiveKit::createRoom($room, $maxListeners + 1)) {
            throw new CommentaryRefused('Couldn’t reach the commentary server. Try again in a moment.', 'unavailable', 503);
        }

        $session = MatchCommentarySession::create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'room' => $room,
            'started_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        MatchUpdated::dispatch($match->id);

        $maxMinutes = PlatformRules::int('commentary.max_minutes');

        return [
            'session' => $session->id,
            'onAir' => true,
            'url' => LiveKit::url(),
            'room' => $room,
            'token' => LiveKit::publisherToken($room, 'u'.$user->id, (string) $user->name, $maxMinutes * 60 + 300),
            'heartbeatSeconds' => self::HEARTBEAT_SECONDS,
            'silenceMinutes' => PlatformRules::int('commentary.silence_minutes'),
            'maxMinutes' => $maxMinutes,
            'endsAt' => $session->started_at->copy()->addMinutes($maxMinutes)->toIso8601String(),
        ];
    }

    /**
     * The commentator's phone reporting in. Answers whether it should stay on air — this is
     * how a phone learns it was taken off air by /control, by reports or by the time limit.
     *
     * @return array<string, mixed>
     */
    public function heartbeat(MatchCommentarySession $session, bool $muted): array
    {
        if (! $session->isEnded()) {
            $match = $session->match;
            if (! PlatformRules::bool('commentary.enabled')) {
                $this->end($session, 'switched_off');
            } elseif ($match === null || $match->isFinished()) {
                $this->end($session, 'match_finished');
            } elseif ($session->started_at->copy()->addMinutes(PlatformRules::int('commentary.max_minutes'))->isPast()) {
                $this->end($session, 'time_limit');
            }
        }

        if ($session->isEnded()) {
            return $this->offAir($session);
        }

        $count = LiveKit::participantCount($session->room);
        $listeners = $count === null ? $session->listeners_now : max(0, $count - 1);
        $mutedChanged = $session->muted !== $muted;

        $session->forceFill([
            'last_heartbeat_at' => now(),
            'muted' => $muted,
            'listeners_now' => $listeners,
            'peak_listeners' => max($session->peak_listeners, $listeners),
        ])->save();

        if ($mutedChanged) {
            MatchUpdated::dispatch($session->match_id);
        }

        return [
            'session' => $session->id,
            'onAir' => true,
            'listeners' => $listeners,
            'muted' => $muted,
        ];
    }

    /**
     * A listen-only pass for the speaker icon.
     *
     * @return array<string, mixed>
     *
     * @throws CommentaryRefused
     */
    public function listen(LiveMatch $match, string $listenerKey): array
    {
        $session = self::available() && ! $match->isFinished() ? $this->current($match) : null;
        if ($session === null) {
            throw new CommentaryRefused('Nobody is commentating right now.', 'off_air', 404);
        }
        if ($session->listeners_now >= PlatformRules::int('commentary.max_listeners')) {
            throw new CommentaryRefused('Commentary is full right now. Try again in a minute.', 'full', 409);
        }

        $remaining = max(60, (int) now()->diffInSeconds(
            $session->started_at->copy()->addMinutes(PlatformRules::int('commentary.max_minutes')), true,
        ));

        return [
            'session' => $session->id,
            'onAir' => true,
            'by' => $session->user?->name,
            'url' => LiveKit::url(),
            'room' => $session->room,
            'token' => LiveKit::listenerToken($session->room, 'l-'.substr(hash('sha256', $listenerKey.'|'.$session->id), 0, 16).'-'.Str::lower(Str::random(4)), $remaining + 300),
        ];
    }

    /**
     * A listener's report. Enough different listeners on one broadcast take it off air
     * (rule `commentary.reports_to_end`; 0 leaves it to a moderator).
     *
     * @return array{reported: bool, offAir: bool}
     */
    public function report(MatchCommentarySession $session, ?User $reporter, string $reporterKey, string $reason, ?string $details): array
    {
        try {
            MatchCommentaryReport::create([
                'session_id' => $session->id,
                'reporter_id' => $reporter?->id,
                'reporter_key' => $reporterKey,
                'reason' => array_key_exists($reason, MatchCommentaryReport::REASONS) ? $reason : 'other',
                'details' => $details !== null ? Str::limit(trim($details), 290, '') : null,
            ]);
        } catch (QueryException) {
            // Already reported by this listener — one vote each.
        }

        $threshold = PlatformRules::int('commentary.reports_to_end');
        if (! $session->isEnded() && $threshold > 0 && $session->reports()->count() >= $threshold) {
            $this->end($session, 'reports');
        }

        return ['reported' => true, 'offAir' => $session->isEnded()];
    }

    /** Close every open broadcast on a match (it finished). */
    public function endForMatch(LiveMatch|int $match): void
    {
        $id = $match instanceof LiveMatch ? $match->id : $match;
        MatchCommentarySession::query()->open()->where('match_id', $id)->get()
            ->each(fn (MatchCommentarySession $s) => $this->end($s, 'match_finished'));
    }

    /** The one way a broadcast ends. Safe to call twice. */
    public function end(MatchCommentarySession $session, string $reason, ?User $by = null, bool $notify = true): void
    {
        if ($session->isEnded()) {
            return;
        }

        $session->forceFill([
            'ended_at' => now(),
            'ended_reason' => $reason,
            'ended_by' => $by?->id,
            'listeners_now' => 0,
        ])->save();

        LiveKit::deleteRoom($session->room);

        if ($notify) {
            MatchUpdated::dispatch($session->match_id);
        }
    }

    /** @return array<string, mixed> */
    private function offAir(MatchCommentarySession $session): array
    {
        return [
            'session' => $session->id,
            'onAir' => false,
            'reason' => $session->ended_reason,
            'message' => match ($session->ended_reason) {
                'admin' => 'Haraan took your commentary off air.',
                'reports' => 'Your commentary was taken off air after listeners reported it.',
                'time_limit' => 'You reached the time limit for one broadcast. Go on air again to keep going.',
                'match_finished' => 'The match finished, so commentary ended.',
                'switched_off' => 'Live commentary has been switched off for now.',
                'restarted' => 'You went on air from another phone.',
                'silence' => 'Your mic was silent, so you went off air.',
                default => 'You’re off air.',
            },
        ];
    }
}
