<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user per calendar day they were active. Append-only; the presence of a
 * row is the signal, so there is no updated_at. Populated by {@see User::touchLastSeen()}.
 */
class UserActivityDay extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'activity_date'];

    protected $casts = [
        'activity_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
