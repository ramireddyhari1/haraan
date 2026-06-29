<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $quantity
 * @property float       $total_amount
 * @property string      $status
 * @property array|null  $seat_numbers
 * @property string|null $coupon_code
 * @property float       $discount
 * @property int         $user_id
 * @property int         $event_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read User  $user
 * @property-read Event $event
 */
final class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'quantity',
        'total_amount',
        'status',
        'seat_numbers',
        'coupon_code',
        'discount',
        'user_id',
        'event_id',
        'organization_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'total_amount' => 'float',
            'discount'     => 'float',
            'seat_numbers' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    //  Relationships
    // -------------------------------------------------------------------------

    /** The user who placed this booking. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The event this booking is for. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Owning organization unit (district/venue). Nullable; scoping not yet enabled. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_id');
    }
}
