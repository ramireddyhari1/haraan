<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A bookable physical unit inside a venue — "Court 1", "Pitch A", "Lane 3".
 *
 * A court can host several sports ({@see $sports}); a booking locks it across all of them
 * for its time window, so the same ground shared by football and cricket never double-books.
 * {@see $price} is the court's own hourly rate, falling back to the venue price when null.
 */
final class VenueCourt extends Model
{
    use BroadcastsContentChanges;

    /** Clients refetch venue lists when a court changes. */
    protected string $contentDomain = 'venues';

    /** What a bookable unit can be. Drives the desk's icon and wording. */
    public const KINDS = [
        'court' => 'Court',
        'table' => 'Table',
        'station' => 'Station',
        'room' => 'Room',
        'lane' => 'Lane',
    ];

    protected $fillable = [
        'venue_id', 'name', 'seats', 'kind', 'sports', 'price', 'sort_order', 'is_active',
        'peak_price', 'peak_days', 'peak_start', 'peak_end',
    ];

    protected $casts = [
        'sports'    => 'array',
        'seats'     => 'integer',
        'price'     => 'integer',
        'peak_price' => 'integer',
        'peak_days' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * What this unit is called — its own kind when set, else the lane's default.
     *
     * A row saved before kinds existed has none, and rather than guessing from
     * the sport it falls back to the console's vocabulary: a turf reads "Court",
     * a café reads "Table". Same row, different word, no data migration needed
     * for a café that renames nothing.
     */
    public function kindLabel(?string $lane = null): string
    {
        if ($this->kind !== null && isset(self::KINDS[$this->kind])) {
            return self::KINDS[$this->kind];
        }

        return $lane !== null && \App\Support\PartnerLane::isBranchLane($lane)
            ? ucfirst(\App\Support\PartnerLane::resourceNoun($lane))
            : 'Court';
    }

    /** "4 seats", or null when capacity isn't a meaningful property here. */
    public function seatsLabel(): ?string
    {
        if ($this->seats === null || $this->seats < 1) {
            return null;
        }

        return $this->seats.' '.($this->seats === 1 ? 'seat' : 'seats');
    }

    /** Whether this unit can hold a party of the given size. */
    public function fitsParty(?int $partySize): bool
    {
        // No stated capacity means the desk isn't tracking it — never block on it.
        if ($partySize === null || $this->seats === null || $this->seats < 1) {
            return true;
        }

        return $this->seats >= $partySize;
    }

    /** Sports this court supports, trimmed and de-duplicated (may be empty → all venue sports). */
    public function sportsList(): array
    {
        $list = is_array($this->sports) ? $this->sports : [];

        return array_values(array_unique(array_filter(array_map('trim', $list))));
    }

    /** True when this court can host the given sport (empty sport list = hosts anything). */
    public function supportsSport(string $sport): bool
    {
        $list = $this->sportsList();

        return $list === [] || in_array($sport, $list, true);
    }

    /** Peak weekdays as 3-letter names (["Sat","Sun"]); empty = every day. */
    public function peakDaysList(): array
    {
        $list = is_array($this->peak_days) ? $this->peak_days : [];

        return array_values(array_filter(array_map(
            static fn ($d) => ucfirst(strtolower(substr(trim((string) $d), 0, 3))),
            $list,
        )));
    }

    /**
     * Whether peak pricing applies for a booking on the given date at the given time label.
     * Requires a peak price AND at least one "when" (days or window) to be set — a bare peak
     * price with no schedule is ignored rather than silently doubling every booking.
     */
    public function isPeak(Carbon $date, ?string $time): bool
    {
        if ($this->peak_price === null) {
            return false;
        }

        $days = $this->peakDaysList();
        $hasWindow = $this->peak_start !== null && $this->peak_end !== null;

        if ($days === [] && ! $hasWindow) {
            return false;
        }

        if ($days !== [] && ! in_array($date->format('D'), $days, true)) {
            return false;
        }

        if ($hasWindow) {
            $t = self::minutes($time);
            $s = self::minutes($this->peak_start);
            $e = self::minutes($this->peak_end);
            if ($t === null || $s === null || $e === null || ! ($t >= $s && $t < $e)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The effective hourly rate for a booking: peak price when it applies, else this court's
     * base price, falling back to the supplied venue price when the court sets none.
     */
    public function rateFor(Carbon $date, ?string $time, int $venuePrice): int
    {
        if ($this->isPeak($date, $time)) {
            return (int) $this->peak_price;
        }

        return (int) ($this->price ?? $venuePrice);
    }

    /** Parse a time label ("7:00 PM", "19:00") to minutes-from-midnight, or null. */
    private static function minutes(?string $label): ?int
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $ts = strtotime(trim($label));

        return $ts === false ? null : (int) date('G', $ts) * 60 + (int) date('i', $ts);
    }
}
