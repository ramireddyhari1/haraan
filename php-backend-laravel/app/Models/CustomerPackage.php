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
        'venue_package_id', 'partner_id', 'customer_phone', 'customer_name',
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
}
