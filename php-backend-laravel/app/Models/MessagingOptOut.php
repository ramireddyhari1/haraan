<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who has said stop. A row with no partner_id silences every partner; a row with
 * one silences only that partner, so opting out of a gym's reminders doesn't cost
 * someone the concert reminder they wanted.
 *
 * Journeys honour these. Ticket delivery does not — that's a transaction the
 * customer paid for, and withholding it would be worse than annoying them.
 */
final class MessagingOptOut extends Model
{
    protected $fillable = ['channel', 'recipient', 'partner_id', 'reason', 'opted_out_at'];

    protected $casts = ['opted_out_at' => 'datetime'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /** Whether this recipient has silenced this partner (or everyone). */
    public static function blocks(string $channel, string $recipient, ?int $partnerId): bool
    {
        return static::query()
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->where(function ($q) use ($partnerId): void {
                $q->whereNull('partner_id');
                if ($partnerId !== null) {
                    $q->orWhere('partner_id', $partnerId);
                }
            })
            ->exists();
    }

    /** Idempotent: opting out twice is not an error, and must not move the date. */
    public static function record(string $channel, string $recipient, ?int $partnerId = null, string $reason = 'stop_keyword'): self
    {
        return static::query()->firstOrCreate(
            ['channel' => $channel, 'recipient' => $recipient, 'partner_id' => $partnerId],
            ['reason' => $reason, 'opted_out_at' => Carbon::now()],
        );
    }
}
