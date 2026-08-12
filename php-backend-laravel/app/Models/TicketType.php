<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, priced ticket tier for an event (e.g. General, Group, VIP).
 *
 * @property int         $id
 * @property int         $event_id
 * @property string      $name
 * @property string      $kind      standard | addon | donation
 * @property float       $price
 * @property int         $admits    People admitted per ticket (bundles). 1 = normal.
 * @property float|null  $min_price Pay-what-you-want floor for donation tiers.
 * @property int|null    $capacity  Null = unlimited (bounded only by the event's slots).
 * @property int         $sold
 * @property int         $sort
 * @property \Carbon\Carbon|null $sales_start
 * @property \Carbon\Carbon|null $sales_end
 *
 * @property-read Event $event
 */
final class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_slot_id',
        'name',
        'description',
        'kind',
        'price',
        'admits',
        'min_price',
        'pricing_phases',
        'capacity',
        'sold',
        'sort',
        'visible',
        'release_phase',
        'bulk_booking',
        'min_per_order',
        'max_per_order',
        'sales_start',
        'sales_end',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price'          => 'float',
            'admits'         => 'integer',
            'min_price'      => 'float',
            'pricing_phases' => 'array',
            'capacity'       => 'integer',
            'sold'           => 'integer',
            'sort'           => 'integer',
            'visible'        => 'boolean',
            'release_phase'  => 'integer',
            'bulk_booking'   => 'boolean',
            'min_per_order'  => 'integer',
            'max_per_order'  => 'integer',
            'sales_start'    => 'datetime',
            'sales_end'      => 'datetime',
        ];
    }

    /**
     * How many of this tier a single order may buy: 1..N. Defaults to a broad cap
     * so ordinary tiers are unconstrained; a bulk-booking tier narrows it to the
     * host's min/max. `null` from either bound falls back to a sane default.
     *
     * @return array{min: int, max: int}
     */
    public function orderBounds(): array
    {
        $min = $this->bulk_booking ? max(1, (int) ($this->min_per_order ?? 1)) : 1;
        $max = $this->bulk_booking && $this->max_per_order !== null
            ? max($min, (int) $this->max_per_order)
            : 25;

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Whether the host shows this tier to buyers.
     *
     * Always read visibility through this — never `$tier->visible` — from inside
     * another Eloquent model. `visible` is also Eloquent's own protected property
     * (the serialization whitelist), and PHP resolves a protected member declared
     * on a shared ancestor directly, so code in Event/Booking/etc. silently reads
     * that empty array instead of the column and every tier looks hidden.
     */
    public function isVisible(): bool
    {
        return (bool) $this->getAttribute('visible');
    }

    /** Remaining tickets for this tier, or null when capacity is unlimited. */
    public function remaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max($this->capacity - $this->sold, 0);
    }

    /**
     * Cleaned, ordered list of pricing phases. Each phase is a positive-price
     * block with a capacity (how many tickets sell at that price). Malformed
     * entries are dropped so a half-authored tier can't corrupt checkout.
     *
     * @return list<array{label: string, price: float, capacity: int}>
     */
    public function phases(): array
    {
        $raw = $this->pricing_phases;

        if (! is_array($raw)) {
            return [];
        }

        $phases = [];

        foreach ($raw as $p) {
            if (! is_array($p)) {
                continue;
            }

            $price    = (float) ($p['price'] ?? 0);
            $capacity = (int) ($p['capacity'] ?? 0);

            if ($price <= 0 || $capacity <= 0) {
                continue;
            }

            $phases[] = [
                'label'    => trim((string) ($p['label'] ?? '')),
                'price'    => $price,
                'capacity' => $capacity,
            ];
        }

        return $phases;
    }

    /**
     * The phase that applies to the next sale: the first phase whose cumulative
     * capacity still exceeds what's been sold. Returns null when there are no
     * phases (flat pricing) or every phase is exhausted (fall back to base price).
     *
     * @return array{label: string, price: float, capacity: int}|null
     */
    public function currentPhase(): ?array
    {
        $cumulative = 0;

        foreach ($this->phases() as $phase) {
            $cumulative += $phase['capacity'];

            if ($this->sold < $cumulative) {
                return $phase;
            }
        }

        return null;
    }

    /** The live per-ticket price a buyer pays now — the current phase, else the flat price. */
    public function effectivePrice(): float
    {
        return $this->currentPhase()['price'] ?? (float) $this->price;
    }

    /**
     * Phase schedule for display (screenshot: "Early bird / Phase 1 / Phase 2"),
     * annotated with the cumulative spot range each covers and which one is live.
     *
     * @return list<array{label: string, price: float, from: int, to: int, current: bool, soldOut: bool}>
     */
    public function phaseSchedule(): array
    {
        $cumulative   = 0;
        $currentFound = false;
        $rows         = [];

        foreach ($this->phases() as $phase) {
            $from = $cumulative + 1;
            $cumulative += $phase['capacity'];

            $soldOut = $this->sold >= $cumulative;
            // The current phase is the first that isn't yet sold out.
            $current = ! $soldOut && ! $currentFound;
            if ($current) {
                $currentFound = true;
            }

            $rows[] = [
                'label'   => $phase['label'],
                'price'   => $phase['price'],
                'from'    => $from,
                'to'      => $cumulative,
                'current' => $current,
                'soldOut' => $soldOut,
            ];
        }

        return $rows;
    }

    /** True when the tier is inside its sales window (or has none). */
    public function isOnSale(): bool
    {
        $now = now();

        if ($this->sales_start !== null && $now->lt($this->sales_start)) {
            return false;
        }

        if ($this->sales_end !== null && $now->gt($this->sales_end)) {
            return false;
        }

        return true;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** The session this tier belongs to, or null when it applies to every slot. */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
