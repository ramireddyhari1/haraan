<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One session spent, tied to the booking that spent it and the branch that
 * honoured it.
 *
 * `redeemed_at_venue_id` is not derivable from the booking: a walk-in redemption
 * has no booking at all, and a session given away at the desk leaves no other
 * trace. It is written once, at the moment the session is spent, because there
 * is nothing to reconstruct it from afterwards.
 */
final class PackageRedemption extends Model
{
    protected $fillable = ['customer_package_id', 'booking_id', 'redeemed_at_venue_id'];

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** The branch that honoured this session. Null for pre-branch rows. */
    public function redeemedAt(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'redeemed_at_venue_id');
    }
}
