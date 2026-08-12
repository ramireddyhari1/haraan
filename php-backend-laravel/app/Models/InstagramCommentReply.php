<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One handled comment. Exists so a comment can only ever be answered once —
 * Meta permits a single private reply per comment and redelivers webhooks, so
 * the unique index is doing compliance work, not just deduplication.
 */
final class InstagramCommentReply extends Model
{
    protected $fillable = [
        'comment_id', 'partner_id', 'connection_id', 'rule_id', 'media_id',
        'commenter_id', 'commenter_username', 'status', 'skip_reason',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
