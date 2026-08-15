<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a customer BOUGHT. Keyed on phone, matching the partner CRM — a walk-in
 * has no account, and the desk knows people by their number.
 *
 * Sessions remaining is DERIVED from the redemption ledger, never stored: a
 * counter that can only be decremented drifts and can't be explained to a
 * customer who disputes their balance.
 */
final class CustomerPackage extends Model
{
    protected $fillable = [
        'venue_package_id', 'partner_id', 'sold_at_venue_id', 'customer_phone', 'customer_name',
        'sessions_total', 'amount_paid', 'payment_method', 'expires_at',
    ];

    protected $casts = [
        'sessions_total' => 'integer',
        'amount_paid' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(VenuePackage::class, 'venue_package_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PackageRedemption::class);
    }

    public function used(): int
    {
        return $this->redemptions()->count();
    }

    public function remaining(): int
    {
        return max($this->sessions_total - $this->used(), 0);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Usable = has sessions left AND hasn't run out of time. */
    public function isUsable(): bool
    {
        return $this->remaining() > 0 && ! $this->isExpired();
    }

    /**
     * Whether this pass may be spent at a given branch.
     *
     * A pass inherits its branch lock from the OFFER it was bought from:
     * `venue_packages.venue_id` null means "any of this partner's branches", and a
     * value means that one only. Where the pass was SOLD is irrelevant to where it
     * may be used — a customer can buy a chain-wide pass at Koramangala and spend
     * it at HSR, which is the whole point of selling one.
     *
     * Null `$venueId` means the caller isn't acting at a branch (a business-level
     * report), so no branch restriction applies.
     */
    public function isUsableAt(?int $venueId): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        $lockedTo = $this->package?->venue_id;

        return $lockedTo === null || $venueId === null || (int) $lockedTo === $venueId;
    }

    /**
     * Constrain a query to passes spendable at a branch — the SQL half of
     * {@see isUsableAt()}, for the lists that must not load every row to filter.
     */
    public function scopeUsableAtVenue(\Illuminate\Database\Eloquent\Builder $query, ?int $venueId): void
    {
        if ($venueId === null) {
            return;
        }

        $query->whereHas('package', function (\Illuminate\Database\Eloquent\Builder $p) use ($venueId): void {
            $p->whereNull('venue_id')->orWhere('venue_id', $venueId);
        });
    }
}
