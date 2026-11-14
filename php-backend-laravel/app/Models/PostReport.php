<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's report on a Home feed post, or on one comment under it (comment_id set).
 * Worked in /control → ActionBoard Home → Reported posts.
 */
class PostReport extends Model
{
    public const REASONS = [
        'spam' => 'Spam',
        'nudity' => 'Nudity or sexual content',
        'violence' => 'Violence or dangerous acts',
        'harassment' => 'Harassment or bullying',
        'hate' => 'Hate speech',
        'false_info' => 'False information',
        'not_sport' => 'Not about sport',
        'other' => 'Something else',
    ];

    protected $guarded = [];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(PlayerPost::class, 'post_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'comment_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
