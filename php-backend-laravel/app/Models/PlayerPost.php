<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One photo post on a player's profile grid — and, for a public account, on the ActionBoard
 * Home feed. Moderated from /control → ActionBoard Home → Posts.
 */
class PlayerPost extends Model
{
    /** Shown everywhere. */
    public const STATUS_VISIBLE = 'visible';

    /** Taken down by a moderator (or auto-hidden by reports). Only the author still sees it. */
    public const STATUS_HIDDEN = 'hidden';

    /** Removed for breaking the rules. Nobody sees it, the author included; the row stays as evidence. */
    public const STATUS_REMOVED = 'removed';

    public const STATUSES = [
        self::STATUS_VISIBLE => 'Visible',
        self::STATUS_HIDDEN => 'Hidden',
        self::STATUS_REMOVED => 'Removed',
    ];

    protected $guarded = [];

    protected $casts = [
        'pinned_at' => 'datetime',
        'pinned_until' => 'datetime',
        'featured' => 'boolean',
        'open_reports' => 'integer',
        'moderated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class, 'post_id');
    }

    /** Carousel images, in display order. Empty for pre-carousel posts (use image_path). */
    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class, 'post_id')->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class, 'post_id');
    }

    /** Comments a reader may see — what every public count and thread uses. */
    public function visibleComments(): HasMany
    {
        return $this->comments()->where('status', PostComment::STATUS_VISIBLE);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(PostSave::class, 'post_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class, 'post_id');
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_VISIBLE);
    }

    public function isVisible(): bool
    {
        return ($this->status ?? self::STATUS_VISIBLE) === self::STATUS_VISIBLE;
    }

    /** Pinned right now: pinned, and the pin hasn't run out. */
    public function isPinned(): bool
    {
        return $this->pinned_at !== null && ($this->pinned_until === null || $this->pinned_until->isFuture());
    }

    /** Every image path, cover first — the carousel rows, or the cover alone for older posts. */
    public function imagePaths(): array
    {
        $paths = $this->images->pluck('image_path')->values()->all();

        return $paths !== [] ? $paths : array_values(array_filter([$this->image_path]));
    }
}
