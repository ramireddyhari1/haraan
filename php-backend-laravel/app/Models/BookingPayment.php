<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of money against a booking.
 *
 * `amount` is signed: positive collected, negative refunded. Rows are only ever
 * written through {@see \App\Services\BookingLedger}, which recomputes the parent
 * booking's amount_paid/payment_status in the same transaction.
 *
 * @property int         $id
 * @property int         $booking_id
 * @property float       $amount
 * @property string      $method
 * @property int|null    $collected_by
 * @property string|null $reference
 * @property string|null $note
 */
class BookingPayment extends Model
{
    /** @use HasFactory<\Database\Factories\BookingPaymentFactory> */
    use HasFactory;

    /** Money that physically passes through a staff member's hands. */
    public const CASH_METHODS = ['cash', 'upi', 'card'];

    protected $fillable = [
        'booking_id',
        'amount',
        'method',
        'collected_by',
        'shift_session_id',
        'reference',
        'note',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** The staff member who took it; null for gateway money. */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * The desk shift this belongs to. Null for gateway money, and null for cash
     * taken while no shift was open — money nobody has claimed.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftSession::class, 'shift_session_id');
    }

    public function isRefund(): bool
    {
        return (float) $this->amount < 0;
    }

    /** Cash-drawer money — what a shift close-out has to account for. */
    public function isOverTheCounter(): bool
    {
        return in_array($this->method, self::CASH_METHODS, true);
    }
}
