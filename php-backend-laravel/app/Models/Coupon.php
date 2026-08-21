<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'venue_id', 'scope', 'code', 'type', 'discount', 'max_discount', 'min_order', 'min_tickets',
        'max_uses', 'per_customer_limit', 'uses', 'active', 'expires_at', 'multi_event',
        'eligibility', 'phone_numbers', 'restrict_dates', 'valid_dates', 'restrict_times', 'valid_times',
    ];

    protected $casts = [
        'event_id'           => 'integer',
        'venue_id'           => 'integer',
        'discount'           => 'float',
        'max_discount'       => 'float',
        'min_order'          => 'float',
        'min_tickets'        => 'integer',
        'max_uses'           => 'integer',
        'per_customer_limit' => 'integer',
        'uses'               => 'integer',
        'active'             => 'boolean',
        'expires_at'         => 'datetime',
        'multi_event'        => 'boolean',
        'phone_numbers'      => 'array',
        'restrict_dates'     => 'boolean',
        'valid_dates'        => 'array',
        'restrict_times'     => 'boolean',
        'valid_times'        => 'array',
    ];

    /** True once the coupon has an expiry that is now in the past. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** The order subtotal must reach this coupon's minimum (0 / null = no minimum). */
    public function meetsMinOrder(float $subtotal): bool
    {
        return $subtotal >= (float) ($this->min_order ?? 0);
    }

    /**
     * The order must carry at least this many tickets (0 / null = no minimum).
     *
     * Separate from {@see meetsMinOrder()} on purpose: "spend ₹500" and "buy 2 tickets"
     * are different offers, and a host who wants the second one gets no help from the
     * first. A null ticket count means the caller can't say — treat it as satisfied
     * rather than block a coupon on a number nobody supplied.
     */
    public function meetsMinTickets(?int $tickets): bool
    {
        $required = (int) ($this->min_tickets ?? 0);

        return $required <= 1 || $tickets === null || $tickets >= $required;
    }

    /**
     * The ₹ amount this coupon takes off a given ticket subtotal, honouring its type:
     * a fixed coupon is its flat `discount`; a percentage coupon is `discount`% of the
     * subtotal, capped at `max_discount` when one is set. Never negative. The caller
     * still clamps this to the payable total so a coupon can't make an order go below ₹0.
     */
    public function discountFor(float $subtotal): float
    {
        if ($this->type === 'percent') {
            $amount = $subtotal * ((float) $this->discount / 100);
            if ($this->max_discount !== null && (float) $this->max_discount > 0) {
                $amount = min($amount, (float) $this->max_discount);
            }
        } else {
            $amount = (float) $this->discount;
        }

        return round(max(0, $amount), 2);
    }

    /** The event this coupon is scoped to; null = global (works on any event). */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * True when this coupon may be used for the given event. A global coupon
     * (`event_id` null) applies everywhere; a scoped coupon only to its event.
     *
     * A venue-only code never applies to an event, even unscoped — otherwise widening
     * the model would have silently made every turf discount valid on ticket sales.
     */
    public function appliesToEvent(?int $eventId): bool
    {
        if ($this->scope === 'venue') {
            return false;
        }

        return $this->event_id === null || (int) $this->event_id === (int) $eventId;
    }

    /**
     * True when this coupon may be used for the given venue booking.
     *
     * Opt-in by design: `scope` defaults to `event`, so every coupon that existed before
     * venues could be discounted stays events-only. A code reaches turf only when someone
     * deliberately set it to `venue` (optionally pinned to one venue) or `all`.
     */
    public function appliesToVenue(?int $venueId): bool
    {
        if ($this->scope !== 'venue' && $this->scope !== 'all') {
            return false;
        }

        return $this->venue_id === null || (int) $this->venue_id === (int) $venueId;
    }

    /** Find a coupon by code (case-insensitive), or null. */
    public static function findByCode(?string $code): ?self
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        return self::query()->whereRaw('lower(code) = ?', [strtolower($code)])->first();
    }

    /** True when this coupon is active, not expired, and hasn't exhausted its usage cap. */
    public function isRedeemable(): bool
    {
        if (! $this->active || $this->isExpired()) {
            return false;
        }

        return $this->max_uses === null || $this->uses < $this->max_uses;
    }
}
