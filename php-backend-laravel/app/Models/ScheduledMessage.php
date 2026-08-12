<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One queued journey step. Created by the enqueuer, drained by the dispatcher.
 *
 * The row is the source of truth for "have we already scheduled this?" — the
 * unique `dedupe_key` means re-running the enqueuer is free, which is what lets
 * it run on a plain cron tick without bookkeeping.
 */
final class ScheduledMessage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Deliberately not sent — opted out, cancelled, no phone, too late. */
    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'partner_id', 'channel', 'recipient', 'template_key', 'category',
        'payload', 'context_type', 'context_id', 'dedupe_key', 'send_after',
        'status', 'skip_reason', 'attempts', 'sent_at', 'message_log_id', 'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'send_after' => 'datetime',
        'sent_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
