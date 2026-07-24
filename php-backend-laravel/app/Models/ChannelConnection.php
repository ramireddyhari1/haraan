<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A partner's linked Instagram account. The map from "a DM arrived on account
 * 178..." to "that's this partner's" — exact, unlike the WhatsApp shared sender
 * where attribution can only ever be a heuristic.
 */
final class ChannelConnection extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'partner_id', 'channel', 'external_id', 'username', 'page_id',
        'access_token', 'token_expires_at', 'status', 'last_error',
    ];

    protected $casts = [
        // A page token can read and send DMs; it must not sit in the clear.
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = ['access_token'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /** The live connection for an account id, or null if we don't know it. */
    public static function forAccount(string $channel, string $externalId): ?self
    {
        return static::query()
            ->where('channel', $channel)
            ->where('external_id', $externalId)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && filled($this->access_token)
            && ($this->token_expires_at === null || $this->token_expires_at->isFuture());
    }
}
