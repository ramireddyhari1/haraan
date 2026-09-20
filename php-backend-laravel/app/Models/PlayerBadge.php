<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A badge a player has unlocked, with when (and in which match) it happened. Never re-locked. */
final class PlayerBadge extends Model
{
    protected $fillable = ['user_id', 'badge_key', 'match_id', 'unlocked_at', 'celebrated_at'];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'celebrated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(BadgeDefinition::class, 'badge_key', 'key');
    }
}
