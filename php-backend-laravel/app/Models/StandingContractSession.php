<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual generated session under a Standing Contract.
 *
 * @property int $id
 * @property int $standing_contract_id
 * @property int|null $booking_id
 * @property \Illuminate\Support\Carbon $session_date
 * @property string $start_time
 * @property string $end_time
 * @property int $venue_court_id
 * @property float $price
 * @property string $attendance_status
 * @property \Illuminate\Support\Carbon|null $check_in_time
 * @property string $payment_status
 * @property string|null $notes
 */
class StandingContractSession extends Model
{
    use HasFactory;

    public const ATTENDANCE_SCHEDULED = 'scheduled';
    public const ATTENDANCE_PRESENT = 'present';
    public const ATTENDANCE_ABSENT = 'absent';
    public const ATTENDANCE_CANCELLED_CUSTOMER = 'cancelled_customer';
    public const ATTENDANCE_CANCELLED_VENUE = 'cancelled_venue';
    public const ATTENDANCE_CANCELLED_RAIN = 'cancelled_rain';
    public const ATTENDANCE_SKIPPED_HOLIDAY = 'skipped_holiday';
    public const ATTENDANCE_SKIPPED_MANUAL = 'skipped_manual';

    protected $fillable = [
        'standing_contract_id',
        'booking_id',
        'session_date',
        'start_time',
        'end_time',
        'venue_court_id',
        'price',
        'attendance_status',
        'check_in_time',
        'payment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'check_in_time' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(StandingContract::class, 'standing_contract_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class, 'venue_court_id');
    }

    public function isAttended(): bool
    {
        return $this->attendance_status === self::ATTENDANCE_PRESENT;
    }

    public function isSkipped(): bool
    {
        return in_array($this->attendance_status, [
            self::ATTENDANCE_SKIPPED_HOLIDAY,
            self::ATTENDANCE_SKIPPED_MANUAL,
            self::ATTENDANCE_CANCELLED_VENUE,
            self::ATTENDANCE_CANCELLED_RAIN,
        ], true);
    }
}
