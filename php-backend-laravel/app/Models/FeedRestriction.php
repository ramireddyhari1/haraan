<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One limit on one account's use of the Home feed — narrower than suspending the account.
 * Active while not lifted and `until` is empty or still ahead.
 */
class FeedRestriction extends Model
{
    /** Can't create posts. */
    public const NO_POSTING = 'no_posting';

    /** Can't comment. */
    public const NO_COMMENTS = 'no_comments';

    /** Their posts and story bubble leave the Home feed (their own profile grid is unchanged). */
    public const HIDDEN_FROM_FEED = 'hidden_from_feed';

    public const TYPES = [
        self::NO_POSTING => 'Can’t post',
        self::NO_COMMENTS => 'Can’t comment',
        self::HIDDEN_FROM_FEED => 'Hidden from the Home feed',
    ];

    protected $guarded = [];

    protected $casts = [
        'until' => 'datetime',
        'lifted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('lifted_at')
            ->where(fn (Builder $w) => $w->whereNull('until')->orWhere('until', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null && ($this->until === null || $this->until->isFuture());
    }

    public static function has(int $userId, string $type): bool
    {
        return self::query()->active()->where('user_id', $userId)->where('type', $type)->exists();
    }
}
