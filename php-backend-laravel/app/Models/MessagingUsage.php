<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-partner, per-period, per-channel rollup — what a plan's quota check will
 * read. Fully derivable from {@see MessageLog}; kept as its own row so the send
 * path is one increment instead of an aggregate over a table that only grows.
 */
final class MessagingUsage extends Model
{
    protected $table = 'messaging_usage';

    protected $fillable = [
        'partner_id', 'channel', 'period_start', 'period_end',
        'conversations_opened', 'messages_sent', 'messages_failed', 'cost_micros',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'conversations_opened' => 'integer',
        'messages_sent' => 'integer',
        'messages_failed' => 'integer',
        'cost_micros' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
