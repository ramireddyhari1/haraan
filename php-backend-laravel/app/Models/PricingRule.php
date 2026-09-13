<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

final class PricingRule extends Model
{
    public const RULE_TYPES = [
        'time_of_day',
        'day_of_week',
        'seasonal_date_range',
        'occupancy_surge',
        'last_minute',
    ];

    public const PRICING_MODES = [
        'absolute',   // Set exact price (e.g. ₹1800)
        'delta',      // Add or subtract fixed ₹ (e.g. +₹300 or -₹200)
        'percentage', // Add or subtract % (e.g. +20% or -15%)
    ];

    protected $fillable = [
        'venue_id',
        'venue_court_id',
        'name',
        'rule_type',
        'weekdays',
        'start_time',
        'end_time',
        'date_from',
        'date_to',
        'pricing_mode',
        'amount',
        'min_price',
        'max_price',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'weekdays'     => 'array',
        'amount'       => 'float',
        'min_price'    => 'float',
        'max_price'    => 'float',
        'priority'     => 'integer',
        'is_active'    => 'boolean',
        'date_from'    => 'date:Y-m-d',
        'date_to'      => 'date:Y-m-d',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function venueCourt(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class, 'venue_court_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PricingRuleLog::class, 'pricing_rule_id');
    }

    /**
     * Checks whether this rule matches a specific date and time window.
     */
    public function matches(Carbon $date, ?string $time): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // 1. Date range bounds check
        $dateStr = $date->format('Y-m-d');
        if ($this->date_from !== null && $dateStr < $this->date_from->format('Y-m-d')) {
            return false;
        }
        if ($this->date_to !== null && $dateStr > $this->date_to->format('Y-m-d')) {
            return false;
        }

        // 2. Weekday check
        if (! empty($this->weekdays) && is_array($this->weekdays)) {
            $dayShort = strtolower($date->format('D'));     // mon, tue...
            $dayFull = strtolower($date->format('l'));      // monday, tuesday...
            $normalizedWeekdays = array_map('strtolower', $this->weekdays);

            $matchedDay = false;
            foreach ($normalizedWeekdays as $wd) {
                if (str_starts_with($wd, $dayShort) || str_starts_with($dayFull, $wd)) {
                    $matchedDay = true;
                    break;
                }
            }
            if (! $matchedDay) {
                return false;
            }
        }

        // 3. Time window check
        if ($time !== null && trim($time) !== '') {
            $t = self::timeToMinutes($time);
            $s = self::timeToMinutes($this->start_time);
            $e = self::timeToMinutes($this->end_time);

            if ($t !== null && $s !== null && $e !== null) {
                // Handle normal window or midnight crossover
                if ($s <= $e) {
                    if ($t < $s || $t >= $e) {
                        return false;
                    }
                } else {
                    // Crossover midnight (e.g. 22:00 to 02:00)
                    if ($t < $s && $t >= $e) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Calculate adjusted rate according to pricing mode and guards.
     */
    public function applyTo(float $baseRate): float
    {
        $calculated = match ($this->pricing_mode) {
            'absolute'   => $this->amount,
            'delta'      => $baseRate + $this->amount,
            'percentage' => $baseRate * (1.0 + ($this->amount / 100.0)),
            default      => $this->amount,
        };

        if ($this->min_price !== null && $calculated < $this->min_price) {
            $calculated = $this->min_price;
        }
        if ($this->max_price !== null && $calculated > $this->max_price) {
            $calculated = $this->max_price;
        }

        return max(0.0, round($calculated, 2));
    }

    private static function timeToMinutes(?string $label): ?int
    {
        if ($label === null || trim($label) === '') {
            return null;
        }
        $ts = strtotime(trim($label));
        return $ts === false ? null : ((int) date('G', $ts) * 60 + (int) date('i', $ts));
    }
}
