<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded open of an event detail page. Written by EventsController::show (and the web
 * event page). Feeds EventViewsWidget. `created_at` only — a view is immutable, so there's no
 * updated_at.
 */
class EventView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_id', 'user_id', 'visitor_key', 'source', 'device', 'district', 'state',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
