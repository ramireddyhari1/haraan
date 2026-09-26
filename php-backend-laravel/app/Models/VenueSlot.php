<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsVenueAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bookable time row on a venue's weekly template.
 *
 * A slot may be limited to some of the venue's sports ({@see $sports}) — a turf that only
 * opens for football in the evenings. Empty means every sport, which is what every row
 * meant before the column existed. Whether a given court may be sold at this time is the
 * intersection of this list and {@see VenueCourt::$sports}.
 */
final class VenueSlot extends Model
{
    use BroadcastsVenueAvailability;

    /**
     * `price` and `capacity` belong here. They were missing while saveSlot() wrote
     * both, so mass assignment dropped them in silence: the partner set a slot rate
     * of 500 for 4 people, the API answered {"status":"ok"}, and the row kept
     * price NULL and capacity 1 — which is why every desk cell read "0/1".
     */
    protected $fillable = [
        'venue_id', 'day', 'time', 'price', 'capacity', 'sports', 'is_available', 'filling_fast', 'sort_order',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'filling_fast' => 'boolean',
        'capacity'     => 'integer',
        'sports'       => 'array',
    ];

    /** The label for a slot that runs every day — what the app and web fall back to. */
    public const EVERY_DAY = 'Every day';

    public const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * The column defaults to "Today", a label no date ever matches, so a slot added
     * without a day showed on no day at all. New rows start as every day instead.
     */
    protected $attributes = [
        'day' => self::EVERY_DAY,
    ];

    protected static function booted(): void
    {
        // A slot's day is a weekday name or "Every day". Anything else ("Today", "Daily",
        // blank, "mon") is normalised here so the website and the app, which match the
        // label exactly, both find it.
        static::saving(function (VenueSlot $slot): void {
            $slot->day = self::normaliseDay($slot->day);
        });
    }

    public static function normaliseDay(?string $day): string
    {
        $d = strtolower(trim((string) $day));
        foreach (self::WEEKDAYS as $name) {
            if ($d === strtolower($name) || $d === strtolower(substr($name, 0, 3))) {
                return $name;
            }
        }

        return self::EVERY_DAY;
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * The sports this slot runs for, cleaned up. Same normalisation as
     * {@see VenueCourt::sportsList()} so the two lists compare like for like.
     *
     * @return list<string>
     */
    public function sportsList(): array
    {
        $list = is_array($this->sports) ? $this->sports : [];

        return array_values(array_unique(array_filter(array_map('trim', $list))));
    }

    /** True when this slot runs for the given sport (empty sport list = runs for anything). */
    public function supportsSport(string $sport): bool
    {
        $list = $this->sportsList();

        return $list === [] || in_array($sport, $list, true);
    }

    /**
     * Can this court be sold at this time?
     *
     * Either side leaving its list empty means "no restriction", so a venue that never
     * touches sports behaves exactly as it did before the column existed. Only when both
     * sides name sports and share none is the pairing refused.
     */
    public function allowsCourt(VenueCourt $court): bool
    {
        $mine = $this->sportsList();
        $theirs = $court->sportsList();

        return $mine === [] || $theirs === [] || array_intersect($mine, $theirs) !== [];
    }
}
