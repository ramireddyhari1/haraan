<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\BookingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int         $id
 * @property int         $quantity
 * @property float       $total_amount
 * @property float       $amount_paid    derived from booking_payments — never set directly
 * @property string      $payment_status unpaid|partial|paid|refunded|part_refunded
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
#[ObservedBy(BookingObserver::class)]
final class Booking extends Model
{
    use HasFactory;

    /**
     * Every booking gets a unique, scannable ticket code the moment it's created — the app
     * renders it as the entry-pass QR (`haraan:ticket:<code>`) and the partner check-in scanner
     * resolves it. Matches the format the backfill migration used (24 upper-alnum chars).
     */
    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            if (empty($booking->ticket_code)) {
                do {
                    $code = Str::upper(Str::random(24));
                } while (self::query()->where('ticket_code', $code)->exists());

                $booking->ticket_code = $code;
            }
        });
    }

    /**
     * Money state defaults live on the model as well as the column, so a freshly
     * created Booking reads 'unpaid' / 0.00 without needing a refresh() first —
     * otherwise every caller sees null and has to know to reload.
     */
    protected $attributes = [
        'amount_paid' => 0,
        'payment_status' => 'unpaid',
    ];

    protected $fillable = [
        'quantity',
        'total_amount',
        'convenience_fee',
        'status',
        // Razorpay reserve→confirm: order id links the rows of one payment, payment id
        // is stamped once the signature verifies, reserved_until bounds the PENDING hold.
        'razorpay_order_id',
        'razorpay_payment_id',
        'reserved_until',
        'seat_numbers',
        'coupon_code',
        'discount',
        'user_id',
        'event_id',
        'ticket_type_id',
        'event_slot_id',
        'organization_id',
        // Venue-slot bookings (Phase B)
        'booking_type',
        'venue_id',
        'venue_slot_id',
        'venue_court_id',
        'slot_date',
        'start_time',
        'end_time',
        'slot_label',
        // Walk-in / offline bookings created at the partner desk.
        'channel',
        'guest_name',
        'guest_phone',
        // Who the ticket is for, captured at checkout (see the add_attendee_contact
        // migration). The account is who paid; these are the order's contact details.
        'attendee_name',
        'attendee_email',
        'attendee_phone',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity'        => 'integer',
            'total_amount'    => 'float',
            'amount_paid'     => 'float',
            'convenience_fee' => 'float',
            'discount'        => 'float',
            'seat_numbers' => 'array',
            'slot_date'    => 'date',
            'reserved_until' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    //  Relationships
    // -------------------------------------------------------------------------

    /**
     * Every movement of money against this booking — advances, the balance
     * collected at the desk, refunds (negative rows).
     *
     * NB `amount_paid` and `payment_status` are DERIVED from these rows and are
     * written only by {@see \App\Services\BookingLedger}. Never set them directly.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    /** What the customer still owes. Zero once settled, never negative. */
    public function balanceDue(): float
    {
        return max(0.0, round((float) $this->total_amount - (float) $this->amount_paid, 2));
    }

    /** Took an advance but hasn't settled — the chase list on the dashboard. */
    public function hasBalanceDue(): bool
    {
        return $this->balanceDue() > 0 && ! in_array(
            strtolower((string) $this->payment_status),
            ['refunded', 'part_refunded'],
            true,
        );
    }

    /** The user who placed this booking. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The event this booking is for (null for venue bookings). */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** The ticket tier this booking was sold at (null for flat-price / venue bookings). */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /** The event session this booking is for (null for single-slot / venue bookings). */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    /** The venue this booking reserves a slot at (null for event bookings). */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** The recurring slot template this booking reserves (nullable). */
    public function venueSlot(): BelongsTo
    {
        return $this->belongsTo(VenueSlot::class);
    }

    /** The physical court this booking locks for its time window (nullable). */
    public function venueCourt(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class);
    }

    /** The settlement record for this booking (nullable until a payout is raised). */
    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    /** Owning organization unit (district/venue). Nullable; scoping not yet enabled. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_id');
    }
}
