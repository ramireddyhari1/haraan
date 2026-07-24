<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per send ATTEMPT — including the ones that never reached the provider
 * (channel disabled, credentials missing, unroutable number). Those non-sends are
 * the whole point: today they vanish into a log line, so nobody notices when
 * ticket delivery has been quietly failing for a week.
 */
final class MessageLog extends Model
{
    protected $table = 'message_log';

    /** A send that actually left the building. Only these cost money. */
    public const STATUS_SENT = 'sent';

    /** The provider rejected it (bad template, unverified sender, throttled…). */
    public const STATUS_FAILED = 'failed';

    /** Channel switched off in config — never attempted. */
    public const STATUS_DISABLED = 'disabled';

    /** No credentials / no sender configured — never attempted. */
    public const STATUS_UNCONFIGURED = 'unconfigured';

    /** Recipient couldn't be normalised to a routable address. */
    public const STATUS_UNROUTABLE = 'unroutable';

    protected $fillable = [
        'partner_id', 'conversation_id', 'channel', 'direction', 'recipient',
        'category', 'template_key', 'context_type', 'context_id',
        'provider', 'provider_message_id', 'status', 'error',
        'cost_micros', 'currency',
    ];

    protected $casts = [
        'context_id' => 'integer',
        'cost_micros' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessageConversation::class, 'conversation_id');
    }

    public function wasSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
