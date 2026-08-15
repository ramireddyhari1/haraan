<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a venue SELLS: "10 sessions for ₹4,000", optionally valid for N days and
 * optionally tied to one venue (null = any of this partner's venues).
 */
final class VenuePackage extends Model
{
    protected $fillable = [
        'partner_id', 'venue_id', 'name', 'price', 'sessions', 'validity_days', 'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'sessions' => 'integer',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** Per-session value, for showing the saving against the walk-in rate. */
    public function perSession(): int
    {
        return $this->sessions > 0 ? (int) round($this->price / $this->sessions) : 0;
    }
}
