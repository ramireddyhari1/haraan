<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One other device taking part in a match. See the migration for why there are two
 * separate secrets on this row.
 */
class MatchDevice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /** The roles a second device can hold. Adding one is adding a line here. */
    public const ROLE_LBW = 'LBW_AI_CAMERA';
    public const ROLE_BOWLER = 'BOWLER_ANALYSIS_CAMERA';

    public const ROLES = [self::ROLE_LBW, self::ROLE_BOWLER];

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_REVOKED = 'revoked';

    /** How long a pairing QR is worth scanning. Long enough to walk to the other phone. */
    public const PAIR_TTL_MINUTES = 10;

    /**
     * A device that has not checked in for this long is shown as lost. Cricket has long
     * gaps between deliveries, so this is minutes, not seconds.
     */
    public const STALE_AFTER_SECONDS = 90;

    public static function friendlyRole(string $role): string
    {
        return match ($role) {
            self::ROLE_LBW => 'LBW / AI review camera',
            self::ROLE_BOWLER => 'Bowler analysis camera',
            default => 'Match device',
        };
    }

    /**
     * Unambiguous when read off a screen and typed by hand: no O/0, no I/1/l. A pairing
     * code is sometimes read aloud across a ground.
     */
    public static function freshToken(int $length = 10): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        do {
            $token = '';
            for ($i = 0; $i < $length; $i++) {
                $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('pair_token', $token)->exists());

        return $token;
    }

    public static function freshSessionToken(): string
    {
        return Str::random(48);
    }

    public function isPairable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }

    /** Connected AND heard from recently — the two are not the same thing. */
    public function isLive(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && $this->last_seen_at !== null
            && $this->last_seen_at->diffInSeconds(now()) <= self::STALE_AFTER_SECONDS;
    }

    /** What the scorer's device list shows for this row. */
    public function presentStatus(): string
    {
        return match (true) {
            $this->status === self::STATUS_REVOKED => 'revoked',
            $this->status === self::STATUS_CONNECTED => $this->isLive() ? 'connected' : 'lost',
            $this->isPairable() => 'pending',
            default => 'expired',
        };
    }
}
