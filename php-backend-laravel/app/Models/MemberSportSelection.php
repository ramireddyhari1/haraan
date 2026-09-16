<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sport a member chose for advanced insights. `created_at` is when it was chosen: it
 * orders which choices count if the plan's number of sports drops, and dates the cooldown
 * before the sport can be swapped out. Written only by SportInsightsAccess.
 *
 * @property int $user_id
 * @property string $sport
 * @property \Illuminate\Support\Carbon $created_at
 */
final class MemberSportSelection extends Model
{
    protected $fillable = ['user_id', 'sport'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
