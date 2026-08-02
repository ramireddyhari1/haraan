<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A court-hour occupied by something that isn't a booking — maintenance, a
 * holiday, an academy batch, a tournament hold, a private hire.
 *
 * Read by {@see \App\Services\BookingService::assertCourtHourFree()} alongside
 * real bookings, so all five compete for the same physical slot.
 *
 * @property int         $venue_id
 * @property int|null    $venue_court_id  null = every court at the venue
 * @property string      $kind
 * @property int|null    $weekday         null = every day in the range
 * @property string|null $start_time      null = the whole day
 */
class VenueBlock extends Model
{
    /** @use HasFactory<\Database\Factories\VenueBlockFactory> */
    use HasFactory;

    public const KINDS = [
        'maintenance' => 'Maintenance',
        'holiday' => 'Holiday / closed',
        'academy' => 'Academy batch',
        'tournament' => 'Tournament',
        'private' => 'Private hire',
    ];

    protected $fillable = [
        'venue_id',
        'venue_court_id',
        'kind',
        'title',
        'starts_on',
        'ends_on',
        'weekday',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'weekday' => 'integer',
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

    /**
     * Blocks that could apply to this venue on this date.
     *
     * Narrows on the date range and weekday in SQL; the court and time-window
     * decisions stay in PHP because "null court means every court" and "null time
     * means all day" are easier to get right — and to read — as explicit rules.
     */
    public function scopeApplyingOn(Builder $query, int $venueId, CarbonInterface $date): Builder
    {
        return $query
            ->where('venue_id', $venueId)
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString())
            ->where(fn (Builder $q) => $q
                ->whereNull('weekday')
                ->orWhere('weekday', $date->dayOfWeek));
    }

    /** True when this block covers the given court (null court = the whole venue). */
    public function coversCourt(?int $courtId): bool
    {
        return $this->venue_court_id === null || $this->venue_court_id === $courtId;
    }

    /** True when this block has no time window and therefore takes the whole day. */
    public function isAllDay(): bool
    {
        return $this->start_time === null || $this->end_time === null;
    }

    public function label(): string
    {
        return $this->title ?: (self::KINDS[$this->kind] ?? ucfirst($this->kind));
    }
}
