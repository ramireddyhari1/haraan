<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VenueSlot extends Model
{
    protected $fillable = [
        'venue_id', 'day', 'time', 'is_available', 'filling_fast', 'sort_order',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'filling_fast' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
