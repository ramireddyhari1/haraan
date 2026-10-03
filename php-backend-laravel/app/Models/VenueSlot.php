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
        'venue_id', 'day', 'time', 'price', 'capacity', 'sports', 'court_prices', 'is_available', 'filling_fast', 'sort_order',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'filling_fast' => 'boolean',
        'capacity'     => 'integer',
        'sports'       => 'array',
        'court_prices' => 'array',
    ];

    /**
     * The price this slot sets for one court, or null to leave it to the court.
     *
     * That court's own price here first, then the slot's price for all courts. Null means
     * the slot says nothing and the court's rate (peak included) is charged — see
     * {@see VenueCourt::rateFor()}, which every desk, app, web and checkout path goes through.
     */
    public function priceForCourt(VenueCourt|int|null $court): ?float
    {
        $id = $court instanceof VenueCourt ? $court->id : $court;
        $own = $id !== null ? ($this->courtPriceList()[(int) $id] ?? null) : null;
        if ($own !== null && $own > 0) {
            return $own;
        }

        return (float) $this->price > 0 ? (float) $this->price : null;
    }

    /**
     * Per-court prices, cleaned: court id => price, positive prices only.
     *
     * @return array<int, float>
     */
    public function courtPriceList(): array
    {
        $out = [];
        foreach ((is_array($this->court_prices) ? $this->court_prices : []) as $courtId => $price) {
            if (is_numeric($price) && (float) $price > 0 && (int) $courtId > 0) {
                $out[(int) $courtId] = (float) $price;
            }
        }

        return $out;
    }

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
            $slot->time = self::normaliseTime($slot->time) ?? $slot->time;
        });
    }

    /**
     * A slot's start time in the one spelling everything else uses: "6:00 AM".
     *
     * The partner app's own hint asked for "06:00 AM - 07:00 AM", which strtotime() can't
     * read — so that row sold nowhere online, sorted last on the desk, and sat beside the
     * generator's "6:00 AM" row as a second 6 AM. Only the first time in the text counts
     * (a slot is a start time; its length is the venue's slot length). Null when there is
     * no readable time at all.
     */
    public static function normaliseTime(?string $time): ?string
    {
        $m = self::startMinutes($time);

        return $m === null ? null : sprintf('%d:%02d %s', intdiv($m, 60) % 12 ?: 12, $m % 60, $m < 720 ? 'AM' : 'PM');
    }

    /** Minutes after midnight of the first time in the text, or null. "18:00", "6 pm", "06:00 AM - 07:00 AM". */
    public static function startMinutes(?string $time): ?int
    {
        if (! preg_match('/(\d{1,2})(?::(\d{2}))?\s*([AaPp]\.?[Mm]\.?)?/', (string) $time, $g)) {
            return null;
        }
        $h = (int) $g[1];
        $min = isset($g[2]) && $g[2] !== '' ? (int) $g[2] : 0;
        $ampm = strtolower(str_replace('.', '', $g[3] ?? ''));
        if ($ampm !== '') {
            if ($h < 1 || $h > 12) {
                return null;
            }
            $h = $h % 12 + ($ampm === 'pm' ? 12 : 0);
        } elseif (($g[2] ?? '') === '') {
            // A bare number ("6") isn't a time anyone can be held to.
            return null;
        }

        return ($h <= 23 && $min <= 59) ? $h * 60 + $min : null;
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

    /**
     * The slot rows that run on one date — THE day rule, used by the website, checkout,
     * the WhatsApp bot, the partner desk and Home alike.
     *
     * Every-day rows plus that weekday's rows. A weekday row at the same start time as an
     * every-day row replaces it (a Saturday 6 AM at a peak rate wins over the every-day
     * 6 AM), so the two never both show. This used to be "weekday rows, else every-day
     * rows": a venue with Mon–Sun 6 AM rows and "Every day" 8–11 AM rows never sold
     * 8–11 AM online, while the partner desk counted those hours as capacity.
     *
     * @param  \Illuminate\Support\Collection<int, VenueSlot>  $rows  a venue's slot rows
     * @return \Illuminate\Support\Collection<int, VenueSlot>
     */
    public static function forDate(\Illuminate\Support\Collection $rows, \Carbon\CarbonInterface $date): \Illuminate\Support\Collection
    {
        $weekday = $date->format('l');
        $startOf = fn (VenueSlot $s): int => \App\Services\BookingService::timeToMinutes($s->time) ?? PHP_INT_MAX;

        $mine = $rows->filter(fn (VenueSlot $s): bool => self::normaliseDay($s->day) === $weekday);
        $taken = $mine->map($startOf)->all();
        $everyDay = $rows->filter(fn (VenueSlot $s): bool => self::normaliseDay($s->day) === self::EVERY_DAY
            && ! in_array($startOf($s), $taken, true));

        return $mine->concat($everyDay)->sortBy($startOf)->values();
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
