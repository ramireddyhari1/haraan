<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use App\Models\Concerns\BroadcastsContentChanges;
use App\Support\Membership\MembershipSettings;
use App\Support\PlatformRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

final class Venue extends Model
{
    use AuditsAdminChanges;

    /** Only changes to these fields are audit-logged (see AuditsAdminChanges). */
    protected array $auditedAttributes = ['status', 'published_at', 'price', 'convenience_fee_type', 'convenience_fee_value', 'fees', 'is_bookable', 'is_active', 'booking_window_days', 'partner_id', 'cancel_free_hours', 'cancel_refund_percent'];

    use BroadcastsContentChanges;

    /** Clients refetch venue lists when a venue changes. */
    protected string $contentDomain = 'venues';

    protected $fillable = [
        'name', 'category', 'kind', 'branch_label', 'branch_code', 'capabilities',
        'sports', 'location', 'city', 'address', 'distance', 'latitude', 'longitude', 'map_link', 'place_id',
        'price', 'convenience_fee_type', 'convenience_fee_value', 'fees',
        'price_chart', 'price_note', 'rating', 'ratings_count', 'reviews_count', 'tagline', 'hours',
        'about', 'rules', 'images', 'amenities', 'status', 'published_at', 'is_bookable', 'booking_window_days', 'is_active', 'is_featured',
        'sort_order', 'partner_id', 'organization_id',
        'hours_json', 'slot_minutes', 'cancel_free_hours', 'cancel_refund_percent',
    ];

    protected $casts = [
        'images' => 'array',
        'amenities' => 'array',
        'capabilities' => 'array',
        'sports' => 'array',
        'rules' => 'array',
        'fees' => 'array',
        'price_chart' => 'array',
        'hours_json' => 'array',
        'slot_minutes' => 'integer',
        'cancel_free_hours' => 'integer',
        'cancel_refund_percent' => 'integer',
        'is_bookable' => 'boolean',
        'booking_window_days' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'published_at' => 'datetime',
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

    /**
     * What to call this branch in the partner console — the explicit label, else
     * the area, else the venue name. Never blank.
     *
     * A chain's venues usually share one brand name ("Big Bean Coffee"), so the
     * switcher and every internal table must show the branch, not the brand, or
     * the column reads as the same word three times.
     */
    public function branchName(): string
    {
        foreach ([$this->branch_label, $this->location, $this->name] as $candidate) {
            if (trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return 'Branch';
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

    /**
     * Court-hour bookings taken here. Event bookings share this table, so the
     * `booking_type` filter is what makes this "the venue's bookings" rather than
     * "every order that happens to name a venue".
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->where('booking_type', 'venue');
    }

    /** Time taken off the market — maintenance, holidays, academy batches, private hires. */
    public function blocks(): HasMany
    {
        return $this->hasMany(VenueBlock::class);
    }

    /**
     * Dynamic rate rules that {@see VenueCourt::rateFor()} consults before peak and
     * base pricing. Venue-wide rules carry a null `venue_court_id`.
     */
    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class)->orderByDesc('priority');
    }

    /** Staff explicitly assigned to this branch. The owning partner is not a row here — see {@see partner()}. */
    public function assignedStaff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_venues');
    }

    /**
     * Every audited change made to this venue from a console. AdminAction stores its
     * subject as a class basename, so the type filter is half the key.
     *
     * @see \App\Models\Concerns\AuditsAdminChanges  writes a row when an audited column moves.
     */
    public function auditTrail(): HasMany
    {
        return $this->hasMany(AdminAction::class, 'subject_id')
            ->where('subject_type', 'Venue');
    }

    /**
     * ActionBoard matches played here, joined the only exact way the schema allows:
     * a match points at the booking it was played on, and that booking points here.
     * `live_matches.venue` is free text and is deliberately not used — two venues
     * both called "Turf Park" would merge into one.
     */
    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(
            LiveMatch::class,
            Booking::class,
            'venue_id',          // bookings.venue_id
            'venue_booking_id',  // live_matches.venue_booking_id
            'id',
            'id',
        );
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

    /**
     * The bookable windows on a weekday, as [startMinute, endMinute] pairs within that day.
     *
     * A day's own hours give one window; a closing time of 12:00 AM means midnight at the
     * end of the day. Hours that close AFTER midnight (Mon 6 PM → 1 AM) are split at 12 AM:
     * Monday runs to midnight and the rest is a window at the start of Tuesday. Every booking
     * therefore stays inside one calendar date, which is what the booking engine stores
     * (slot_date + HH:MM) — the after-midnight hour of Monday night is sold as Tuesday 12 AM.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function windowsForWeekday(string $key): array
    {
        $keys = array_keys(self::WEEKDAYS);
        $i = array_search($key, $keys, true);
        if ($i === false) {
            return [];
        }

        $windows = [];

        // Carried over from the night before.
        $prev = $this->hoursForWeekday($keys[($i + 6) % 7]);
        if ($prev !== null) {
            [$po, $pc] = [self::toMinutes($prev['open']), self::toMinutes($prev['close'])];
            // Strictly earlier: open == close is a 24-hour day, which never carries over.
            if ($po !== null && $pc !== null && $pc > 0 && $pc < $po) {
                $windows[] = [0, $pc];
            }
        }

        $own = $this->hoursForWeekday($key);
        if ($own !== null) {
            [$o, $c] = [self::toMinutes($own['open']), self::toMinutes($own['close'])];
            if ($o !== null && $c !== null && $c === $o) {
                // Open and close the same ("00:00"–"00:00") is open round the clock.
                return [[0, 24 * 60]];
            }
            if ($o !== null && $c !== null) {
                $windows[] = [$o, ($c === 0 || $c < $o) ? 24 * 60 : $c];
            }
        }

        return $windows;
    }

    /** Whether the venue takes bookings on the given date (open that weekday, or open past midnight into it). */
    public function isOpenOn(Carbon $date): bool
    {
        // No structured hours configured → fall back to "open" (legacy behaviour).
        if (! is_array($this->hours_json) || $this->hours_json === []) {
            return true;
        }

        return $this->windowsForWeekday($date->format('D')) !== [];
    }

    /** Minutes in one of this venue's slots: 30 or 60 (the default when unset). */
    public function slotLength(): int
    {
        return max(30, (int) ($this->slot_minutes ?: 60));
    }

    /**
     * The template slot rows that apply to a date: the venue's every-day rows plus the
     * rows labelled with that weekday, a weekday row replacing an every-day row at the same
     * time ({@see VenueSlot::forDate()}). Same rule as the partner desk and the app's venue
     * page (VenueDetailScreen slotsForDay), so everyone offers the same times.
     *
     * @return \Illuminate\Support\Collection<int, VenueSlot>
     */
    public function slotsOn(\Carbon\CarbonInterface $date): \Illuminate\Support\Collection
    {
        $all = $this->relationLoaded('slots') ? $this->slots : $this->slots()->get();

        // normaliseDay (inside forDate): any label that isn't a weekday (legacy "Today",
        // "Daily", blank) is an every-day row, as admin has always presented it.
        return VenueSlot::forDate($all, $date);
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

        // The start-times the hours call for, in order, keyed "Monday|6:00 AM".
        $wanted = [];
        // Own hours plus anything carried past midnight from the night before (see
        // windowsForWeekday). Hours closing after midnight used to be dropped outright —
        // a 6 PM–1 AM turf got no slots at all, with no warning.
        foreach (self::WEEKDAYS as $key => $full) {
            foreach ($this->windowsForWeekday($key) as [$open, $close]) {
                // Last start must leave room for one full interval.
                for ($m = $open; $m + $step <= $close; $m += $step) {
                    $wanted[$full . '|' . self::toLabel($m)] = [$full, self::toLabel($m), $m];
                }
            }
        }

        // Carried-over windows come first in a day; list each day's times in clock order.
        uksort($wanted, static function (string $a, string $b) use ($wanted): int {
            $days = array_values(self::WEEKDAYS);

            return [array_search($wanted[$a][0], $days, true), $wanted[$a][2]]
                <=> [array_search($wanted[$b][0], $days, true), $wanted[$b][2]];
        });

        // Sync, never wipe: this used to delete every slot and recreate them at ₹0 and
        // "available" on EVERY save, so a slot price or a switched-off slot set in the
        // Slots tab silently reverted the next time anyone saved the venue. A time that
        // is still within the hours keeps its row (price, sports, capacity, availability);
        // only times the hours no longer cover are removed, and only new ones are created.
        $existing = $this->slots()->get()->keyBy(fn (VenueSlot $s): string => $s->day . '|' . $s->time);

        // What a new row starts from: the same time's every-day row if there was one, else
        // that time on any other day. Turning "every day" rows into per-day rows (or opening
        // a new day) then keeps the prices set for that hour instead of falling back to ₹0.
        $template = [];
        foreach ($existing as $slot) {
            $t = (string) $slot->time;
            if (! isset($template[$t]) || $slot->day === VenueSlot::EVERY_DAY) {
                $template[$t] = $slot;
            }
        }

        foreach ($existing as $k => $slot) {
            if (! isset($wanted[$k])) {
                $slot->delete();
            }
        }

        $order = 0;
        foreach ($wanted as $k => [$full, $label]) {
            $slot = $existing->get($k);
            if ($slot !== null) {
                if ((int) $slot->sort_order !== $order) {
                    $slot->update(['sort_order' => $order]);
                }
            } else {
                $from = $template[$label] ?? null;
                VenueSlot::query()->create([
                    'venue_id' => $this->id,
                    'day' => $full,
                    'time' => $label,
                    'is_available' => true,
                    'price' => $from?->price ?? 0,
                    'court_prices' => $from?->court_prices,
                    'sports' => $from?->sports,
                    'capacity' => $from?->capacity ?? 1,
                    'sort_order' => $order,
                ]);
            }
            $order++;
        }
    }

    /**
     * Rating, rating count and review count from the venue's real, visible reviews.
     *
     * These three columns used to be typed in by hand in /control ("starter values"),
     * so a venue with no reviews at all could show 4.2 ★ from 120 ratings. They are now
     * only ever derived: null / 0 until a customer actually reviews.
     */
    public function refreshRating(): void
    {
        $stats = $this->reviews()
            ->where('is_active', true)
            ->selectRaw('COUNT(*) as n, AVG(rating) as avg')
            ->first();

        $count = (int) ($stats->n ?? 0);

        // '0', not null, for "no reviews": on prod the column is NOT NULL, and changing
        // that on SQLite rebuilds `venues` — whose FK cascade deletes every court, slot
        // and review. Every reader treats a '0' rating (falsy) as "not rated".
        $this->forceFill([
            'rating'        => $count > 0 ? number_format((float) $stats->avg, 1, '.', '') : '0',
            'ratings_count' => $count,
            'reviews_count' => $count,
        ])->saveQuietly();
    }

    /** Whether any booking (court or event) points at this venue. */
    public function hasBookings(): bool
    {
        return Booking::query()->where('venue_id', $this->id)->exists();
    }

    protected static function booted(): void
    {
        // A new venue has no reviews, so it has no rating — not the column's '4.5' default.
        static::creating(function (Venue $venue): void {
            if ((int) ($venue->ratings_count ?? 0) === 0) {
                $venue->rating = '0';
                $venue->ratings_count = 0;
                $venue->reviews_count = 0;
            }
        });

        // Bookings reference venues by a plain id (no foreign key), so deleting a venue
        // would leave its bookings pointing at nothing — gone from the partner's sheet,
        // earnings and payouts. The Filament delete buttons refuse first with a message;
        // this is the backstop for every other path.
        static::deleting(function (Venue $venue): void {
            if ($venue->hasBookings()) {
                throw new \DomainException("Venue #{$venue->id} has bookings and cannot be deleted. Deactivate it instead.");
            }
        });
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
            $sig = match (true) {
                $day === null => 'closed',
                self::toMinutes($day['open']) === self::toMinutes($day['close']) => 'Open 24 hours',
                default => self::toLabel(self::toMinutes($day['open'])).'–'.self::toLabel(self::toMinutes($day['close'])),
            };
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

    /**
     * The partner-set booking fee charged on top of a slot subtotal. Same contract as
     * {@see \App\Models\Event::convenienceFeeFor()} — none | flat | percent — so a reader
     * only has to learn the idea once and the checkout maths matches across both flows.
     *
     * Always rounded to paise here, because the Razorpay order is built from this number:
     * a lingering float would charge a different amount than the summary displayed.
     */
    public function convenienceFeeFor(float $subtotal): float
    {
        return round(array_sum(array_column($this->feeLinesFor($subtotal), 'amount')), 2);
    }

    /**
     * Every fee this venue charges, as rules: the convenience fee first (when set), then the
     * admin's own named fees from /control ("Floodlight charge" ...). One list so the checkout
     * maths, the review page's breakup and the API all read the same thing.
     *
     * @return list<array{label: string, type: string, value: float}>
     */
    public function feeRules(): array
    {
        $rules = [];

        if (in_array($this->convenience_fee_type, ['flat', 'percent'], true) && (float) $this->convenience_fee_value > 0) {
            $rules[] = ['label' => 'Convenience fee', 'type' => $this->convenience_fee_type, 'value' => (float) $this->convenience_fee_value];
        }

        foreach ((array) ($this->fees ?? []) as $fee) {
            $type = $fee['type'] ?? null;
            $value = (float) ($fee['value'] ?? 0);
            $label = trim((string) ($fee['label'] ?? ''));
            if (! in_array($type, ['flat', 'percent'], true) || $value <= 0 || $label === '') {
                continue;
            }
            $rules[] = ['label' => mb_substr($label, 0, 40), 'type' => $type, 'value' => $value];
        }

        return $rules;
    }

    /**
     * The fees on a slot subtotal, one display line each, rounded to paise (the Razorpay order
     * is built from these). Nothing on a free order; zero-amount lines are dropped.
     *
     * @return list<array{label: string, amount: float}>
     */
    public function feeLinesFor(float $subtotal): array
    {
        if ($subtotal <= 0) {
            return [];
        }

        $lines = [];
        foreach ($this->feeRules() as $rule) {
            $amount = $rule['type'] === 'flat'
                ? round($rule['value'], 2)
                : round($subtotal * $rule['value'] / 100, 2);
            if ($amount > 0) {
                $lines[] = ['label' => $rule['label'], 'amount' => $amount];
            }
        }

        return $lines;
    }

    /**
     * Platform tax on an ONLINE court order (/control → Platform rules → Fees → Pulse tax):
     * none | flat per order | percent of (subtotal − discount), mirroring Event::taxFor().
     * Stored in `tax_amount`, outside `total_amount`, so it is never paid out to the venue.
     * Desk walk-ins are not taxed here — that money never passes through Haraan.
     */
    public static function taxFor(float $subtotal, float $discount = 0.0): float
    {
        $base = max(0.0, $subtotal - $discount);
        if ($subtotal <= 0 || $base <= 0) {
            return 0.0;
        }

        $value = max(0.0, PlatformRules::float('fees.venue_tax_value'));

        return match (PlatformRules::string('fees.venue_tax_type')) {
            'flat'    => round($value, 2),
            'percent' => round($base * $value / 100, 2),
            default   => 0.0,
        };
    }

    /** The tax's name on the bill ("GST"). */
    public static function taxLabel(): string
    {
        return PlatformRules::string('fees.venue_tax_label') ?: 'GST';
    }

    /** Days ahead customers may book here: this venue's own window, else the admin's default (Membership settings). */
    public function bookingWindowDays(): int
    {
        return max(1, (int) ($this->booking_window_days ?? MembershipSettings::int('venue_booking_window_days')));
    }

    // -------------------------------------------------------------------------
    //  Lifecycle & Readiness Guards
    // -------------------------------------------------------------------------

    /**
     * Readiness verification: checks if a venue has completed its setup requirements.
     * Enforces: active courts >= 1, slots >= 1, price > 0, real images >= 1.
     *
     * @return array<string> List of missing requirements (empty if ready)
     */
    public function readinessErrors(): array
    {
        $errors = [];

        if (empty(trim((string) $this->name))) {
            $errors[] = 'Venue name is required.';
        }

        if (empty(trim((string) $this->location)) || empty(trim((string) $this->city))) {
            $errors[] = 'Location (area) and city are required.';
        }

        $activeCourtsCount = $this->courts()->where('is_active', true)->count();
        if ($activeCourtsCount === 0) {
            $errors[] = 'At least one active court/pitch must be added.';
        }

        $slotsCount = $this->slots()->count();
        if ($slotsCount === 0) {
            $errors[] = 'At least one bookable slot or structured operating hours must be configured.';
        }

        $hasCourtPrice = $this->courts()->where('is_active', true)->where('price', '>', 0)->exists();
        if ((int) $this->price <= 0 && ! $hasCourtPrice) {
            $errors[] = 'A base hourly price or court hourly price greater than ₹0 is required.';
        }

        $validImages = is_array($this->images) ? array_values(array_filter($this->images, fn ($img) => ! empty(trim((string) $img)))) : [];
        if (empty($validImages)) {
            $errors[] = 'At least one real photo must be uploaded.';
        }

        return $errors;
    }

    /** Whether the venue meets all readiness requirements to go live. */
    public function isReadyForPublish(): bool
    {
        return empty($this->readinessErrors());
    }

    /**
     * Whether this venue is officially published and live to users.
     * Requires status === 'published', is_active === true, and passing readiness checks.
     */
    public function isPublished(): bool
    {
        return strtolower((string) ($this->status ?? 'draft')) === 'published'
            && (bool) $this->is_active
            && $this->isReadyForPublish();
    }

    /** Publish and activate the venue if ready; throws DomainException if setup is incomplete. */
    public function publish(): void
    {
        $errors = $this->readinessErrors();
        if (! empty($errors)) {
            throw new \DomainException('Venue setup is incomplete: ' . implode(' ', $errors));
        }

        $this->update([
            'status'       => 'published',
            'is_active'    => true,
            'is_bookable'  => true,
            'published_at' => now(),
        ]);
    }

    /** Unpublish the venue and return it to draft. */
    public function unpublish(): void
    {
        $this->update([
            'status'      => 'draft',
            'is_active'   => false,
            'is_bookable' => false,
        ]);
    }

    public const STATE_DRAFT = 'draft';
    public const STATE_LIVE = 'live';
    public const STATE_PAUSED = 'paused';

    /** The one status the venue form shows, in place of the status / is_active / is_bookable trio. */
    public const STATES = [
        self::STATE_DRAFT  => 'Draft — hidden from customers',
        self::STATE_LIVE   => 'Live — visible and taking bookings',
        self::STATE_PAUSED => 'Live — visible, bookings paused',
    ];

    /**
     * Which of the three states the columns describe. Anything not published-and-active is a
     * draft: the customer side shows neither, so calling it anything else would mislead.
     */
    public function visibilityState(): string
    {
        if (strtolower((string) ($this->status ?? 'draft')) !== 'published' || ! $this->is_active) {
            return self::STATE_DRAFT;
        }

        return $this->is_bookable ? self::STATE_LIVE : self::STATE_PAUSED;
    }

    /**
     * Move the venue to one of the three states, writing all three columns together so they
     * can never drift apart. Going live (or paused, which is also visible) needs a venue that
     * is ready: the readiness errors come back, and nothing changes.
     *
     * @return list<string>  readiness errors; empty when the state was applied
     */
    public function applyVisibilityState(string $state): array
    {
        if ($state === self::STATE_DRAFT) {
            $this->unpublish();

            return [];
        }

        if (! in_array($state, [self::STATE_LIVE, self::STATE_PAUSED], true)) {
            throw new \InvalidArgumentException("Unknown venue state [{$state}].");
        }

        $errors = $this->readinessErrors();
        if ($errors !== []) {
            return $errors;
        }

        $this->update([
            'status'       => 'published',
            'is_active'    => true,
            'is_bookable'  => $state === self::STATE_LIVE,
            'published_at' => $this->published_at ?? now(),
        ]);

        return [];
    }

    /** Admin lifecycle status key: 'published' | 'ready' | 'incomplete'. */
    public function lifecycleStatus(): string
    {
        if (strtolower((string) ($this->status ?? 'draft')) === 'published' && (bool) $this->is_active) {
            return $this->isReadyForPublish() ? 'published' : 'incomplete';
        }

        return $this->isReadyForPublish() ? 'ready' : 'incomplete';
    }

    /** Human label for admin tables and badges. */
    public function lifecycleLabel(): string
    {
        return match ($this->lifecycleStatus()) {
            'published'  => 'Live / Published',
            'ready'      => 'Ready to Publish',
            'incomplete' => 'Setup Incomplete',
            default      => 'Draft',
        };
    }

    /**
     * Query scope for customer-facing APIs: returns only verified, ready, published venues.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('is_active', true)
            ->whereHas('courts', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('slots')
            ->where(function (Builder $q) {
                $q->where('price', '>', 0)
                  ->orWhereHas('courts', fn (Builder $cq) => $cq->where('is_active', true)->where('price', '>', 0));
            })
            ->whereNotNull('images')
            ->where('images', '!=', '[]')
            ->where('images', '!=', '""');
    }
}
