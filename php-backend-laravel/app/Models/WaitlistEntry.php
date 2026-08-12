<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody waiting for a court-hour that was already sold.
 *
 * @property string      $status  waiting|offered|converted|expired|cancelled
 * @property string|null $start_time  null = any time that day
 */
class WaitlistEntry extends Model
{
    /** @use HasFactory<\Database\Factories\WaitlistEntryFactory> */
    use HasFactory;

    protected $table = 'slot_waitlist';

    public const STATUS_WAITING = 'waiting';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_WAITING => 'Waiting',
        self::STATUS_OFFERED => 'Offered',
        self::STATUS_CONVERTED => 'Booked',
        self::STATUS_EXPIRED => 'Offer lapsed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'venue_id', 'venue_court_id', 'wanted_on', 'start_time', 'end_time',
        'user_id', 'guest_name', 'guest_phone', 'status',
        'offered_at', 'offer_expires_at', 'notified_at',
        'freed_by_booking_id', 'converted_booking_id', 'note', 'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_WAITING,
    ];

    protected function casts(): array
    {
        return [
            'wanted_on' => 'date',
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class, 'venue_court_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    /** Still in play — either waiting, or holding a live offer. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_WAITING, self::STATUS_OFFERED]);
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    /** Offers whose window has passed — the slot should go back on the market. */
    public function scopeLapsed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OFFERED)
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<', now());
    }

    public function isOfferLive(): bool
    {
        return $this->status === self::STATUS_OFFERED
            && $this->offer_expires_at !== null
            && $this->offer_expires_at->isFuture();
    }

    /** Whoever we would call — an app user's name, or the name taken at the desk. */
    public function contactName(): string
    {
        return $this->guest_name ?: ($this->user?->name ?? 'Unnamed');
    }

    public function contactPhone(): ?string
    {
        return $this->guest_phone ?: $this->user?->phone;
    }

    /** "Sat, 02 Aug · 19:00–20:00" or "Sat, 02 Aug · any time". */
    public function windowLabel(): string
    {
        $day = $this->wanted_on?->format('D, d M') ?? '—';

        if ($this->start_time === null) {
            return $day . ' · any time';
        }

        return $day . ' · ' . $this->start_time . ($this->end_time ? '–' . $this->end_time : '');
    }
}
