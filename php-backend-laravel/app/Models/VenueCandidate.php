<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A sports venue found on Google that Haraan does not list yet — a row in the
 * /control → Venues → Discovery review queue. Nothing here reaches customers until an
 * admin approves it, which creates a {@see Venue} in the "listed" state.
 */
class VenueCandidate extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DUPLICATE = 'duplicate';

    public const CHECK_UNCHECKED = 'unchecked';
    public const CHECK_MATCHED = 'matched';
    public const CHECK_UNMATCHED = 'unmatched';

    protected $fillable = [
        'place_id', 'name', 'address', 'area', 'city', 'state', 'latitude', 'longitude',
        'phone', 'website', 'maps_url', 'primary_type', 'google_types', 'sports', 'found_by',
        'crosscheck_status', 'crosscheck_source', 'crosscheck_name', 'crosscheck_url', 'crosschecked_at',
        'status', 'venue_id', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'google_types' => 'array',
        'sports' => 'array',
        'found_by' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'crosschecked_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function isOpenForReview(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    /**
     * Turn this candidate into a venue page with booking off. Returns the venue, or the
     * one already made for it — approving twice never makes two.
     */
    public function approve(?int $adminId = null): Venue
    {
        $venue = $this->approveRow($adminId);

        // Outside the transaction: a slow Google call must not hold the database.
        try {
            app(\App\Services\VenueDiscovery\VenueDiscovery::class)->fillHours($venue);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Listed venue hours not filled: ' . $e->getMessage());
        }

        return $venue;
    }

    private function approveRow(?int $adminId): Venue
    {
        return DB::transaction(function () use ($adminId): Venue {
            $existing = $this->venue_id ? Venue::find($this->venue_id) : null;
            $existing ??= Venue::query()->where('place_id', $this->place_id)->first();

            if ($existing === null) {
                $sports = array_values(array_filter((array) $this->sports));
                $existing = new Venue();
                $existing->forceFill([
                    'name' => $this->name,
                    // Unknown stays unknown: an empty category shows no sport rather than a guessed one.
                    'category' => $sports[0] ?? '',
                    'sports' => $sports ?: null,
                    'location' => $this->area ?: $this->city,
                    'city' => $this->city,
                    'address' => $this->address,
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                    'map_link' => $this->maps_url,
                    'place_id' => $this->place_id,
                    'contact_phone' => $this->phone,
                    'listing_source' => 'google_places',
                    'price' => 0,
                    // '0' is this table's "not rated yet" — never Google's rating, never an invented one.
                    'rating' => '0',
                    'status' => Venue::STATUS_LISTED,
                    // Off on purpose: every booking query filters on these, so a listed
                    // venue can never be sold or shown as bookable by code that predates it.
                    'is_active' => false,
                    'is_bookable' => false,
                ])->save();
            }

            $this->forceFill([
                'status' => self::STATUS_APPROVED,
                'venue_id' => $existing->id,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ])->save();

            return $existing;
        });
    }

    public function reject(?int $adminId = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ])->save();
    }
}
