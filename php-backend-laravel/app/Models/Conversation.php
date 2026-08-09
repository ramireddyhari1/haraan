<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A direct-message thread. 1:1 today; the participants table already allows more.
 */
class Conversation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_group' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['unread_count', 'last_read_at'])
            ->withTimestamps();
    }

    /**
     * The other person in a 1:1, from [$viewer]'s point of view. Meaningless for a
     * group (there is no single "other"), so callers must branch on [is_group] first.
     */
    public function counterpart(User $viewer): ?User
    {
        return $this->participants->firstWhere('id', '!=', $viewer->id);
    }

    /**
     * Everyone in the conversation except [$viewer]. For a group this is the roster the
     * list card stacks avatars from; for a 1:1 it is the single counterpart.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function others(User $viewer): \Illuminate\Support\Collection
    {
        return $this->participants->where('id', '!=', $viewer->id)->values();
    }
}
