<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

final class Venue extends Model
{
    use BroadcastsContentChanges;

    /** Clients refetch venue lists when a venue changes. */
    protected string $contentDomain = 'venues';

    protected $fillable = [
        'name', 'category', 'sports', 'location', 'city', 'address', 'distance', 'latitude', 'longitude', 'map_link',
        'price', 'price_chart', 'price_note', 'rating', 'ratings_count', 'reviews_count', 'tagline', 'hours',
        'about', 'rules', 'images', 'amenities', 'is_bookable', 'is_active', 'is_featured',
        'sort_order', 'partner_id', 'organization_id',
        'hours_json', 'slot_minutes', 'cancel_free_hours', 'cancel_refund_percent',
    ];

    protected $casts = [
        'images' => 'array',
        'amenities' => 'array',
        'sports' => 'array',
        'rules' => 'array',
        'price_chart' => 'array',
        'hours_json' => 'array',
        'slot_minutes' => 'integer',
        'cancel_free_hours' => 'integer',
        'cancel_refund_percent' => 'integer',
        'is_bookable' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Sports this venue offers, always non-empty: the explicit `sports` list when set, else the
     * primary category. The category is guaranteed first so the card's leading icon matches the
     * badge. De-duplicated and trimmed.
     */
    public function sportsList(): array
    {
        $list = is_array($this->sports) ? $this->sports : [];
        $list = array_merge([$this->category], $list);

        return array_values(array_unique(array_filter(array_map('trim', $list))));
    }

    public function slots(): HasMany
    {
        return $this->hasMany(VenueSlot::class)->orderBy('sort_order');
    }

    /** Bookable physical units (courts / pitches / lanes) inside this venue. */
    public function courts(): HasMany
    {
        return $this->hasMany(VenueCourt::class)->orderBy('sort_order');
    }

    /**
     * Courts grouped by the sport they host: `['Football' => [VenueCourt, …], …]`.
     * A court that lists no sports (or the venue's own sports) appears under every sport
     * the venue offers. Drives the app/web "pick sport → pick court" booking flow.
     */
    public function courtsBySport(): array
    {
        $sports = $this->sportsList();
        $grouped = array_fill_keys($sports, []);

        foreach ($this->courts as $court) {
            $hosts = $court->sportsList() ?: $sports;
            foreach ($hosts as $sport) {
                if (! array_key_exists($sport, $grouped)) {
                    $grouped[$sport] = [];
                }
                $grouped[$sport][] = $court;
            }
        }

        return $grouped;
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VenueReview::class);
    }

    /** Dates this venue is closed for bookings (holidays / maintenance). */
    public function blockedDates(): HasMany
    {
        return $this->hasMany(VenueBlockedDate::class);
    }

    /** Owning organization unit (district/venue). Nullable; scoping not yet enabled. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_id');
    }

    /** The partner (venue owner) who manages this venue in the partner console. Nullable. */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    // -------------------------------------------------------------------------
    //  Operating hours (structured) — drive generated slots + closed-day guard
    // -------------------------------------------------------------------------

    /** Weekday keys in order, mapped to their full display name. */
    private const WEEKDAYS = [
        'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday',
        'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday',
    ];

    /** Open/close ("HH:MM") for a weekday key, or null when closed / unset. */
    public function hoursForWeekday(string $key): ?array
    {
        $hours = is_array($this->hours_json) ? $this->hours_json : [];
        $day = $hours[$key] ?? null;

        if (! is_array($day) || ! empty($day['closed'])) {
            return null;
        }

        $open = $day['open'] ?? null;
        $close = $day['close'] ?? null;

        return ($open && $close) ? ['open' => $open, 'close' => $close] : null;
    }

    /** Whether the venue takes bookings on the given date (open that weekday). */
    public function isOpenOn(Carbon $date): bool
    {
        // No structured hours configured → fall back to "open" (legacy behaviour).
        if (! is_array($this->hours_json) || $this->hours_json === []) {
            return true;
        }

        return $this->hoursForWeekday($date->format('D')) !== null;
    }

    /**
     * Regenerate this venue's bookable slots from its structured hours: one start-time per
     * open weekday from open→close, stepped by slot_minutes. Replaces existing slots, so hours
     * become the single source of truth. No-op when hours aren't set (keeps any manual slots).
     */
    public function regenerateSlotsFromHours(): void
    {
        if (! is_array($this->hours_json) || $this->hours_json === []) {
            return;
        }

        $step = max(30, (int) ($this->slot_minutes ?: 60));
        $this->slots()->delete();

        $order = 0;
        foreach (self::WEEKDAYS as $key => $full) {
            $day = $this->hoursForWeekday($key);
            if ($day === null) {
                continue;
            }

            $open = self::toMinutes($day['open']);
            $close = self::toMinutes($day['close']);
            if ($open === null || $close === null || $close <= $open) {
                continue;
            }

            // Last start must leave room for one full interval.
            for ($m = $open; $m + $step <= $close; $m += $step) {
                VenueSlot::query()->create([
                    'venue_id' => $this->id,
                    'day' => $full,
                    'time' => self::toLabel($m),
                    'is_available' => true,
                    'price' => 0,
                    'sort_order' => $order++,
                ]);
            }
        }
    }

    /** Human summary of the week's hours, e.g. "Mon–Fri 6:00 AM–11:00 PM · Sat–Sun 7:00 AM–10:00 PM". */
    public function displayHours(): string
    {
        if (! is_array($this->hours_json) || $this->hours_json === []) {
            return (string) ($this->hours ?? '');
        }

        // Group consecutive weekdays that share the same open/close (or are all closed).
        $keys = array_keys(self::WEEKDAYS);
        $segments = [];
        $run = [];
        $runSig = null;

        $flush = function () use (&$segments, &$run, &$runSig): void {
            if ($run === []) {
                return;
            }
            $days = count($run) === 1 ? $run[0] : $run[0].'–'.end($run);
            $segments[] = $runSig === 'closed' ? "$days Closed" : "$days $runSig";
            $run = [];
        };

        foreach ($keys as $key) {
            $day = $this->hoursForWeekday($key);
            $sig = $day === null ? 'closed' : self::toLabel(self::toMinutes($day['open'])).'–'.self::toLabel(self::toMinutes($day['close']));
            if ($sig !== $runSig) {
                $flush();
                $runSig = $sig;
            }
            $run[] = $key;
        }
        $flush();

        return implode(' · ', $segments);
    }

    /** One-line cancellation policy, or null when none set. */
    public function cancellationText(): ?string
    {
        $hours = $this->cancel_free_hours;
        if ($hours === null) {
            return null;
        }

        $refund = $this->cancel_refund_percent;
        $tail = ($refund === null || $refund === 0)
            ? 'no refund after that'
            : "{$refund}% refund after that";

        return "Free cancellation up to {$hours} hours before · {$tail}";
    }

    /** "06:00"/"6:00 PM" → minutes-from-midnight, or null. */
    private static function toMinutes(?string $label): ?int
    {
        if ($label === null || trim($label) === '') {
            return null;
        }
        $ts = strtotime(trim($label));

        return $ts === false ? null : (int) date('G', $ts) * 60 + (int) date('i', $ts);
    }

    /** Minutes-from-midnight → "6:00 AM". */
    private static function toLabel(int $minutes): string
    {
        return date('g:i A', mktime(0, $minutes % (24 * 60)));
    }
}
