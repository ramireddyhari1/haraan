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

    /** The other person in a 1:1, from [$viewer]'s point of view. */
    public function counterpart(User $viewer): ?User
    {
        return $this->participants->firstWhere('id', '!=', $viewer->id);
    }
}
