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
        'name',
        'kind',
        'price',
        'admits',
        'min_price',
        'capacity',
        'sold',
        'sort',
        'sales_start',
        'sales_end',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price'       => 'float',
            'admits'      => 'integer',
            'min_price'   => 'float',
            'capacity'    => 'integer',
            'sold'        => 'integer',
            'sort'        => 'integer',
            'sales_start' => 'datetime',
            'sales_end'   => 'datetime',
        ];
    }

    /** Remaining tickets for this tier, or null when capacity is unlimited. */
    public function remaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max($this->capacity - $this->sold, 0);
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
