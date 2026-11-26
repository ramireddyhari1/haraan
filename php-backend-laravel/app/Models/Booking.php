<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsVenueAvailability;
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
    use BroadcastsVenueAvailability;
    use HasFactory;

    /**
     * Channels where the partner's desk created the booking for a customer with no app
     * account: a counter walk-in ('offline') and a WhatsApp Desk hold ('whatsapp').
     * On these rows `user_id` is the PARTNER — the FK needs somewhere to point — so the
     * customer lives in guest_name / guest_phone and nothing may be sent to `user`.
     */
    public const DESK_CHANNELS = ['offline', 'whatsapp'];

    public function isDeskBooking(): bool
    {
        return in_array(strtolower((string) $this->channel), self::DESK_CHANNELS, true);
    }

    /** Only venue bookings hold a court-hour; event tickets never touch slot availability. */
    public function affectsVenueAvailability(): bool
    {
        return $this->getAttribute('booking_type') === 'venue'
            || $this->getOriginal('booking_type') === 'venue';
    }

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
        // Platform charges (see the add_platform_charges migration): customer-paid platform and
        // gateway fees, tax, and what comes off the host's payout. Stored on the order's first row.
        'platform_fee',
        'gateway_fee',
        'tax_amount',
        'host_deduction',
        'pay_at_venue',
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
        'standing_contract_id',
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
            'platform_fee' => 'float',
            'gateway_fee' => 'float',
            'tax_amount' => 'float',
            'host_deduction' => 'float',
            'pay_at_venue' => 'float',
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

    /**
     * What the gateway actually charged for this row.
     *
     * The two booking types keep their totals differently, and a single ledger has
     * to read both: a venue row's `total_amount` is already the settled figure
     * (subtotal + fee − discount, written that way by BookingService::reserveVenue),
     * while an event row holds the ticket subtotal with the fee and discount beside
     * it. Adding the fee to a venue row would bill it twice; ignoring it on an event
     * row would under-record the sale.
     *
     * Pulse tax is the one charge kept OUTSIDE a venue row's `total_amount` (as on events):
     * partner payouts sum `total_amount`, and the tax is Haraan's to remit, not the venue's.
     */
    public function amountCharged(): float
    {
        if ($this->booking_type === 'venue') {
            return max(0.0, round((float) $this->total_amount + (float) $this->tax_amount, 2));
        }

        return max(0.0, round(
            (float) $this->total_amount + $this->customerCharges() - (float) $this->discount,
            2,
        ));
    }

    /**
     * What the customer pays online for this row: the whole charge, less the balance they
     * chose to settle at the venue (advance payment). The Razorpay order, the ledger's
     * online settlement and any automatic gateway refund all use this, never amountCharged().
     */
    public function onlineDue(): float
    {
        return max(0.0, round($this->amountCharged() - (float) ($this->pay_at_venue ?? 0), 2));
    }

    /** Fees and tax the customer paid on top of the ticket subtotal (event rows). */
    public function customerCharges(): float
    {
        return round(
            (float) $this->convenience_fee + (float) $this->platform_fee + (float) $this->gateway_fee + (float) $this->tax_amount,
            2,
        );
    }

    /**
     * Grand total of one EVENT order (its rows share a Razorpay order): ticket subtotal + fees +
     * customer-paid platform/gateway fees + tax − discount. Summed before clamping, because the
     * discount sits on the first row and may exceed that row alone.
     *
     * @param  iterable<Booking>  $rows
     */
    public static function orderGrandTotal(iterable $rows): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) $row->total_amount + $row->customerCharges() - (float) $row->discount;
        }

        return max(0.0, round($sum, 2));
    }

    /**
     * The slot value in SQL — what a partner sees and is paid on. A venue row's
     * `total_amount` includes the online fees (convenience, gateway, named fees), which
     * Haraan keeps, so they come off; tax already sits outside total_amount. Event rows
     * are untouched (their `total_amount` is the tickets alone).
     */
    public const SLOT_VALUE_SQL = "(coalesce(total_amount, 0) - CASE WHEN booking_type = 'venue' THEN coalesce(convenience_fee, 0) ELSE 0 END)";

    /** The court's price for this booking after any discount — no fees, no tax. */
    public function slotValue(): float
    {
        if ($this->booking_type !== 'venue') {
            return round((float) $this->total_amount, 2);
        }

        return round(max(0.0, (float) $this->total_amount - (float) $this->convenience_fee), 2);
    }

    /** What the customer paid on top of the slot (fees + tax) — Haraan's, paid online up front. */
    public function chargesOnTop(): float
    {
        if ($this->booking_type !== 'venue') {
            return 0.0;
        }

        return round(max(0.0, (float) $this->convenience_fee) + max(0.0, (float) $this->tax_amount), 2);
    }

    /**
     * Slot money received so far, as the partner sees it: what was paid less the fees and
     * tax that rode along on the online payment. ₹136 online for a ₹400 slot with a ₹16 fee
     * is ₹120 toward the slot.
     */
    public function partnerPaid(): float
    {
        return round(min($this->slotValue(), max(0.0, (float) $this->amount_paid - $this->chargesOnTop())), 2);
    }

    /** Ways the venue takes money itself, at its own counter — never through Haraan. */
    public const DESK_PAYMENT_METHODS = ['cash', 'upi', 'card'];

    /**
     * What Haraan owes the partner for this booking: only money that passed through Haraan
     * (Razorpay), less Haraan's commission / host fees.
     *
     *  - Online booking: the booking's value, less the advance balance the venue takes at
     *    its desk (`pay_at_venue`), less `host_deduction`. Tax sits outside total_amount.
     *  - Desk booking (walk-in / WhatsApp): only what was paid through a Haraan QR or link.
     *    Cash, the venue's own UPI or card never reached Haraan — counting the booking's
     *    full value made a ₹7,400 day of cash walk-ins read as money Haraan owed.
     */
    public function partnerPayable(): float
    {
        if ($this->isDeskBooking()) {
            $online = (float) $this->payments()
                ->whereNotIn(\Illuminate\Support\Facades\DB::raw('lower(method)'), self::DESK_PAYMENT_METHODS)
                ->sum('amount');

            return round(max(0.0, $online - (float) $this->host_deduction), 2);
        }

        // The slot value — the online fees are Haraan's — less what the venue took itself.
        return round(max(0.0, $this->slotValue() - $this->venueCollected() - (float) $this->host_deduction), 2);
    }

    /**
     * Slot money of an ONLINE booking that did NOT come through Haraan — the part the
     * partner is not paid out on.
     *
     * Once Razorpay money is on the ledger, that money decides: Haraan owes the slot part
     * of what it actually received (online payments less its fees and tax), and the rest
     * of the slot was the venue's to take. What the desk took on top doesn't change what
     * Haraan holds — Classic Sports Arena, 25 Nov: ₹350 came in online (₹150 slot advance
     * + ₹25 fee, × 2) and the desk also took ₹525 each; Haraan still owes the ₹300.
     *
     * With no Razorpay money at all, the venue took everything it collected: the advance
     * balance it was due (`pay_at_venue`) or more when the desk took more, so an online
     * hold settled in full at the counter owes nothing.
     */
    public function venueCollected(): float
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $isDesk = fn ($p): bool => in_array(strtolower((string) $p->method), self::DESK_PAYMENT_METHODS, true);
        $slot = $this->slotValue();

        $online = $payments->reject($isDesk);
        if ($this->booking_type === 'venue' && $online->isNotEmpty()) {
            $onlineSlot = max(0.0, (float) $online->sum('amount') - $this->chargesOnTop());

            return round(max(0.0, $slot - min($slot, $onlineSlot)), 2);
        }

        // Money on the ledger, none of it through Razorpay: Haraan received nothing, so it
        // owes nothing — whatever the desk later records (a "cash refund" typed in by hand
        // must never turn into a payout). Only a row with NO ledger at all (bookings from
        // before online money reached the ledger) falls back to the advance rule below.
        //
        // The same holds for a booking with NO ledger at all made after online money started
        // reaching the ledger (25 Aug 2026): a confirmed online booking always has its
        // Razorpay row, so one without any was never paid through Haraan.
        if ($this->booking_type === 'venue'
            && ($payments->isNotEmpty() || ($this->created_at !== null && $this->created_at->gte(self::LEDGER_SINCE)))) {
            return round($slot, 2);
        }

        $desk = (float) $payments->filter($isDesk)->sum('amount');

        return round(min($slot, max((float) ($this->pay_at_venue ?? 0), $desk)), 2);
    }

    private const ONLINE_SUM_SQL = "(SELECT coalesce(sum(bp.amount), 0) FROM booking_payments bp WHERE bp.booking_id = bookings.id AND lower(bp.method) NOT IN ('cash','upi','card'))";

    private const HAS_ONLINE_SQL = "EXISTS (SELECT 1 FROM booking_payments bp WHERE bp.booking_id = bookings.id AND lower(bp.method) NOT IN ('cash','upi','card'))";

    /** When online money started reaching booking_payments (BookingLedger::settleOnline, deployed 25 Aug 2026). */
    public const LEDGER_SINCE = '2026-08-26 00:00:00';

    private const HAS_ANY_PAYMENT_SQL = 'EXISTS (SELECT 1 FROM booking_payments bp WHERE bp.booking_id = bookings.id)';

    private const DESK_SUM_SQL = "(SELECT coalesce(sum(bp.amount), 0) FROM booking_payments bp WHERE bp.booking_id = bookings.id AND lower(bp.method) IN ('cash','upi','card'))";

    /** Online money less Haraan's fees and tax, floored at 0. */
    private const ONLINE_SLOT_SQL = '(CASE WHEN '.self::ONLINE_SUM_SQL." - coalesce(convenience_fee, 0) - coalesce(tax_amount, 0) > 0 THEN "
        .self::ONLINE_SUM_SQL." - coalesce(convenience_fee, 0) - coalesce(tax_amount, 0) ELSE 0 END)";

    /** max(pay_at_venue, desk money). */
    private const DESK_TAKEN_SQL = '(CASE WHEN coalesce(pay_at_venue, 0) > '.self::DESK_SUM_SQL.' THEN coalesce(pay_at_venue, 0) ELSE '.self::DESK_SUM_SQL.' END)';

    /**
     * {@see venueCollected()} in SQL, per row, for the payout sums. Event rows keep the old
     * rule (just `pay_at_venue`, which they never set).
     */
    public const VENUE_COLLECTED_SQL = "(CASE WHEN booking_type = 'venue' AND ".self::HAS_ONLINE_SQL
        .' THEN (CASE WHEN '.self::ONLINE_SLOT_SQL.' >= '.self::SLOT_VALUE_SQL.' THEN 0 ELSE '.self::SLOT_VALUE_SQL.' - '.self::ONLINE_SLOT_SQL.' END)'
        ." WHEN booking_type = 'venue' AND (".self::HAS_ANY_PAYMENT_SQL." OR created_at >= '".self::LEDGER_SINCE."') THEN ".self::SLOT_VALUE_SQL
        ." WHEN booking_type = 'venue'"
        .' THEN (CASE WHEN '.self::DESK_TAKEN_SQL.' > '.self::SLOT_VALUE_SQL.' THEN '.self::SLOT_VALUE_SQL.' ELSE '.self::DESK_TAKEN_SQL.' END)'
        .' ELSE coalesce(pay_at_venue, 0) END)';

    /**
     * An online venue booking about to be settled at the desk with nothing paid through
     * Haraan: the online fees and tax were never charged (they ride on the Razorpay
     * payment), so they come off before the desk works out what's due. Otherwise the desk
     * collects Haraan's ₹25 fee into its own till. A no-op once any online money landed.
     */
    public function dropUnchargedOnlineCharges(): void
    {
        if ($this->booking_type !== 'venue' || $this->isDeskBooking() || $this->chargesOnTop() <= 0) {
            return;
        }

        $online = $this->payments()
            ->whereNotIn(\Illuminate\Support\Facades\DB::raw('lower(method)'), self::DESK_PAYMENT_METHODS)
            ->where('amount', '>', 0)
            ->exists();
        if ($online) {
            return;
        }

        $this->forceFill([
            'total_amount'    => round(max(0.0, (float) $this->total_amount - (float) $this->convenience_fee), 2),
            'convenience_fee' => 0,
            'tax_amount'      => 0,
        ])->save();
    }

    /** What the customer still owes. Zero once settled, never negative. */
    public function balanceDue(): float
    {
        return max(0.0, round($this->amountCharged() - (float) $this->amount_paid, 2));
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

    /** The parent standing contract if this booking was generated from a recurring contract. */
    public function standingContract(): BelongsTo
    {
        return $this->belongsTo(StandingContract::class, 'standing_contract_id');
    }
}
