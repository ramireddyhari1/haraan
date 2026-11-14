<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One comment on a photo post.
 */
class PostComment extends Model
{
    public const STATUS_VISIBLE = 'visible';

    public const STATUS_HIDDEN = 'hidden';

    protected $guarded = [];

    protected $casts = [
        'moderated_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(PlayerPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_VISIBLE);
    }
}
