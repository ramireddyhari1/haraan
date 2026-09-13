<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\PricingRule;
use App\Models\PricingRuleLog;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PricingMatrixService
{
    /**
     * Resolves the effective rate for a court on a given date and time, returning audit breakdown.
     *
     * @return array{amount: int, base_rate: int, rule_id: ?int, rule_name: ?string, mode: string, is_peak: bool}
     */
    public function resolveRate(VenueCourt $court, Carbon $date, ?string $time, int $venuePrice): array
    {
        $baseRate = (float) ($court->price ?? $venuePrice);

        $rules = PricingRule::where('venue_id', $court->venue_id)
            ->where(function ($q) use ($court) {
                $q->where('venue_court_id', $court->id)
                  ->orWhereNull('venue_court_id');
            })
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderByRaw('CASE WHEN venue_court_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matches($date, $time)) {
                $effective = (int) round($rule->applyTo($baseRate));
                return [
                    'amount'    => $effective,
                    'base_rate' => (int) $baseRate,
                    'rule_id'   => $rule->id,
                    'rule_name' => $rule->name,
                    'mode'      => $rule->pricing_mode,
                    'is_peak'   => $effective > $baseRate,
                ];
            }
        }

        $isPeak = $court->isPeak($date, $time);
        $effective = $isPeak ? (int) $court->peak_price : (int) $baseRate;

        return [
            'amount'    => $effective,
            'base_rate' => (int) $baseRate,
            'rule_id'   => null,
            'rule_name' => $isPeak ? 'Default Peak Pricing' : null,
            'mode'      => 'absolute',
            'is_peak'   => $isPeak,
        ];
    }

    /**
     * Generates a 7-day (Mon-Sun) by 24-hour rate matrix grid.
     *
     * @return array<string, mixed>
     */
    public function getWeeklyMatrix(Venue $venue, ?int $courtId = null): array
    {
        /** @var VenueCourt|null $court */
        $court = null;
        if ($courtId !== null) {
            $court = VenueCourt::where('venue_id', $venue->id)->where('id', $courtId)->first();
        }
        if (! $court) {
            $court = VenueCourt::where('venue_id', $venue->id)->where('is_active', true)->first();
        }

        $baseRate = $court ? (int) ($court->price ?? 1000) : 1000;
        $venuePrice = (int) ($venue->price_per_hour ?? 1000);

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $matrix = [];
        $totalRates = 0;
        $slotCount = 0;
        $minRate = PHP_INT_MAX;
        $maxRate = 0;

        // Use current week dates
        $startOfWeek = Carbon::now()->startOfWeek();

        for ($d = 0; $d < 7; $d++) {
            $currentDate = (clone $startOfWeek)->addDays($d);
            $dayKey = strtolower($currentDate->format('l'));
            $daySlots = [];

            for ($hour = 6; $hour <= 23; $hour++) {
                $timeLabel = sprintf('%02d:00', $hour);
                $res = $court
                    ? $this->resolveRate($court, $currentDate, $timeLabel, $venuePrice)
                    : ['amount' => $baseRate, 'base_rate' => $baseRate, 'rule_id' => null, 'rule_name' => null, 'mode' => 'absolute', 'is_peak' => false];

                $amt = $res['amount'];
                $totalRates += $amt;
                $slotCount++;
                if ($amt < $minRate) $minRate = $amt;
                if ($amt > $maxRate) $maxRate = $amt;

                $daySlots[] = [
                    'hour'       => $hour,
                    'time_label' => $timeLabel,
                    'rate'       => $amt,
                    'base_rate'  => $res['base_rate'],
                    'rule_id'    => $res['rule_id'],
                    'rule_name'  => $res['rule_name'],
                    'mode'       => $res['mode'],
                    'is_peak'    => $res['is_peak'],
                    'tag'        => $res['is_peak'] ? 'peak' : ($amt < $res['base_rate'] ? 'discount' : 'standard'),
                ];
            }

            $matrix[$dayKey] = [
                'day_name' => $currentDate->format('l'),
                'date'     => $currentDate->format('Y-m-d'),
                'slots'    => $daySlots,
            ];
        }

        return [
            'court_id'     => $court?->id,
            'court_name'   => $court?->name ?? 'Default Court',
            'base_rate'    => $baseRate,
            'min_rate'     => $minRate === PHP_INT_MAX ? $baseRate : $minRate,
            'max_rate'     => $maxRate === 0 ? $baseRate : $maxRate,
            'average_rate' => $slotCount > 0 ? (int) round($totalRates / $slotCount) : $baseRate,
            'matrix'       => $matrix,
        ];
    }

    /**
     * List all pricing rules for a venue.
     *
     * @return array<int, mixed>
     */
    public function getRules(Venue $venue, ?int $courtId = null, ?bool $activeOnly = null): array
    {
        $q = PricingRule::with('venueCourt')
            ->where('venue_id', $venue->id);

        if ($courtId !== null) {
            $q->where(function ($sub) use ($courtId) {
                $sub->where('venue_court_id', $courtId)
                    ->orWhereNull('venue_court_id');
            });
        }

        if ($activeOnly !== null) {
            $q->where('is_active', $activeOnly);
        }

        return $q->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(static fn (PricingRule $r) => [
                'id'              => $r->id,
                'venue_id'        => $r->venue_id,
                'venue_court_id'  => $r->venue_court_id,
                'court_name'      => $r->venueCourt?->name ?? 'All Courts',
                'name'            => $r->name,
                'rule_type'       => $r->rule_type,
                'weekdays'        => $r->weekdays ?? [],
                'start_time'      => $r->start_time,
                'end_time'        => $r->end_time,
                'date_from'       => $r->date_from?->format('Y-m-d'),
                'date_to'         => $r->date_to?->format('Y-m-d'),
                'pricing_mode'    => $r->pricing_mode,
                'amount'          => $r->amount,
                'min_price'       => $r->min_price,
                'max_price'       => $r->max_price,
                'priority'        => $r->priority,
                'is_active'       => $r->is_active,
                'created_at'      => $r->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Create a new pricing rule.
     */
    public function createRule(Venue $venue, array $data, ?User $actor = null, ?string $ip = null): PricingRule
    {
        return DB::transaction(function () use ($venue, $data, $actor, $ip) {
            $rule = PricingRule::create([
                'venue_id'        => $venue->id,
                'venue_court_id'  => $data['venue_court_id'] ?? null,
                'name'            => $data['name'],
                'rule_type'       => $data['rule_type'] ?? 'time_of_day',
                'weekdays'        => $data['weekdays'] ?? ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
                'start_time'      => $data['start_time'],
                'end_time'        => $data['end_time'],
                'date_from'       => $data['date_from'] ?? null,
                'date_to'         => $data['date_to'] ?? null,
                'pricing_mode'    => $data['pricing_mode'] ?? 'absolute',
                'amount'          => (float) $data['amount'],
                'min_price'       => isset($data['min_price']) ? (float) $data['min_price'] : null,
                'max_price'       => isset($data['max_price']) ? (float) $data['max_price'] : null,
                'priority'        => isset($data['priority']) ? (int) $data['priority'] : 10,
                'is_active'       => $data['is_active'] ?? true,
            ]);

            PricingRuleLog::create([
                'venue_id'        => $venue->id,
                'pricing_rule_id' => $rule->id,
                'actor_id'        => $actor?->id,
                'action'          => 'created',
                'previous_state'  => null,
                'new_state'       => $rule->toArray(),
                'ip_address'      => $ip,
            ]);

            return $rule;
        });
    }

    /**
     * Update an existing pricing rule.
     */
    public function updateRule(Venue $venue, int $ruleId, array $data, ?User $actor = null, ?string $ip = null): PricingRule
    {
        /** @var PricingRule|null $rule */
        $rule = PricingRule::where('venue_id', $venue->id)->where('id', $ruleId)->first();
        if (! $rule) {
            throw ValidationException::withMessages(['rule_id' => 'Pricing rule not found.']);
        }

        return DB::transaction(function () use ($venue, $rule, $data, $actor, $ip) {
            $prevState = $rule->toArray();

            $rule->update([
                'venue_court_id'  => array_key_exists('venue_court_id', $data) ? $data['venue_court_id'] : $rule->venue_court_id,
                'name'            => $data['name'] ?? $rule->name,
                'rule_type'       => $data['rule_type'] ?? $rule->rule_type,
                'weekdays'        => $data['weekdays'] ?? $rule->weekdays,
                'start_time'      => $data['start_time'] ?? $rule->start_time,
                'end_time'        => $data['end_time'] ?? $rule->end_time,
                'date_from'       => array_key_exists('date_from', $data) ? $data['date_from'] : $rule->date_from,
                'date_to'         => array_key_exists('date_to', $data) ? $data['date_to'] : $rule->date_to,
                'pricing_mode'    => $data['pricing_mode'] ?? $rule->pricing_mode,
                'amount'          => isset($data['amount']) ? (float) $data['amount'] : $rule->amount,
                'min_price'       => array_key_exists('min_price', $data) ? (float) $data['min_price'] : $rule->min_price,
                'max_price'       => array_key_exists('max_price', $data) ? (float) $data['max_price'] : $rule->max_price,
                'priority'        => isset($data['priority']) ? (int) $data['priority'] : $rule->priority,
                'is_active'       => isset($data['is_active']) ? (bool) $data['is_active'] : $rule->is_active,
            ]);

            PricingRuleLog::create([
                'venue_id'        => $venue->id,
                'pricing_rule_id' => $rule->id,
                'actor_id'        => $actor?->id,
                'action'          => 'updated',
                'previous_state'  => $prevState,
                'new_state'       => $rule->fresh()->toArray(),
                'ip_address'      => $ip,
            ]);

            return $rule->fresh();
        });
    }

    /**
     * Toggle active state of a pricing rule.
     */
    public function toggleRule(Venue $venue, int $ruleId, ?User $actor = null, ?string $ip = null): PricingRule
    {
        /** @var PricingRule|null $rule */
        $rule = PricingRule::where('venue_id', $venue->id)->where('id', $ruleId)->first();
        if (! $rule) {
            throw ValidationException::withMessages(['rule_id' => 'Pricing rule not found.']);
        }

        $newState = ! $rule->is_active;
        $rule->update(['is_active' => $newState]);

        PricingRuleLog::create([
            'venue_id'        => $venue->id,
            'pricing_rule_id' => $rule->id,
            'actor_id'        => $actor?->id,
            'action'          => $newState ? 'activated' : 'paused',
            'previous_state'  => ['is_active' => ! $newState],
            'new_state'       => ['is_active' => $newState],
            'ip_address'      => $ip,
        ]);

        return $rule;
    }

    /**
     * Delete a pricing rule.
     */
    public function deleteRule(Venue $venue, int $ruleId, ?User $actor = null, ?string $ip = null): void
    {
        /** @var PricingRule|null $rule */
        $rule = PricingRule::where('venue_id', $venue->id)->where('id', $ruleId)->first();
        if (! $rule) {
            throw ValidationException::withMessages(['rule_id' => 'Pricing rule not found.']);
        }

        DB::transaction(function () use ($venue, $rule, $actor, $ip) {
            $prevState = $rule->toArray();
            $ruleId = $rule->id;

            PricingRuleLog::create([
                'venue_id'        => $venue->id,
                'pricing_rule_id' => null,
                'actor_id'        => $actor?->id,
                'action'          => 'deleted',
                'previous_state'  => $prevState,
                'new_state'       => ['deleted_rule_id' => $ruleId],
                'ip_address'      => $ip,
            ]);

            $rule->delete();
        });
    }

    /**
     * Detect low-occupancy time slots and generate concrete rupee yield opportunities.
     *
     * @return array<int, mixed>
     */
    public function generateYieldRecommendations(Venue $venue): array
    {
        $since = Carbon::now()->subDays(30)->format('Y-m-d');
        $bookings = Booking::where('venue_id', $venue->id)
            ->where('date', '>=', $since)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->get();

        $courtCount = max(1, VenueCourt::where('venue_id', $venue->id)->where('is_active', true)->count());

        // Aggregate bookings by day-of-week and hour bucket
        $counts = [];
        foreach ($bookings as $b) {
            try {
                $d = Carbon::parse($b->date);
                $day = strtolower($d->format('l'));
                $hour = (int) explode(':', $b->start_time)[0];
                $counts[$day][$hour] = ($counts[$day][$hour] ?? 0) + 1;
            } catch (\Exception) {
                // Ignore parsing errors
            }
        }

        $recommendations = [];

        // Rule Candidate 1: Weekday Afternoon Slump (Mon-Thu 11:00 to 16:00)
        $weekdayAfternoonBookings = 0;
        $possibleSlots = 4 * 5 * $courtCount * 4; // 4 weeks, 4 weekdays (Mon-Thu), 5 hours, N courts
        foreach (['monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            for ($h = 11; $h <= 15; $h++) {
                $weekdayAfternoonBookings += $counts[$day][$h] ?? 0;
            }
        }
        $weekdayOccRate = $possibleSlots > 0 ? round(($weekdayAfternoonBookings / $possibleSlots) * 100, 1) : 0.0;

        if ($weekdayOccRate < 35.0) {
            $baseRate = (int) ($venue->price_per_hour ?? 1200);
            $suggestedRate = max(500, (int) round($baseRate * 0.75));
            $estUplift = 16 * $suggestedRate; // 16 additional hours captured per month
            $recommendations[] = [
                'id'                      => 'rec_weekday_afternoon_discount',
                'title'                   => 'Weekday Afternoon Happy Hours',
                'day_of_week'             => 'Mon - Thu',
                'time_window'             => '11:00 - 16:00',
                'current_occupancy_rate'  => $weekdayOccRate,
                'suggested_mode'          => 'absolute',
                'suggested_rate'          => $suggestedRate,
                'projected_monthly_uplift'=> (float) $estUplift,
                'rationale'               => "Weekday afternoons are running at only {$weekdayOccRate}% occupancy. A ₹{$suggestedRate}/hr off-peak rate captures price-sensitive casual students and recovers ~₹" . number_format($estUplift) . "/month.",
                'rule_payload'            => [
                    'name'         => 'Weekday Afternoon Saver',
                    'rule_type'    => 'time_of_day',
                    'weekdays'     => ['monday', 'tuesday', 'wednesday', 'thursday'],
                    'start_time'   => '11:00',
                    'end_time'     => '16:00',
                    'pricing_mode' => 'absolute',
                    'amount'       => $suggestedRate,
                    'priority'     => 15,
                ],
            ];
        }

        // Rule Candidate 2: Weekend Prime Floodlights Surge (Fri-Sun 18:00 to 22:00)
        $weekendPrimeBookings = 0;
        $possiblePrimeSlots = 4 * 3 * $courtCount * 4; // 4 weeks, 3 days, 4 hours
        foreach (['friday', 'saturday', 'sunday'] as $day) {
            for ($h = 18; $h <= 21; $h++) {
                $weekendPrimeBookings += $counts[$day][$h] ?? 0;
            }
        }
        $primeOccRate = $possiblePrimeSlots > 0 ? round(($weekendPrimeBookings / $possiblePrimeSlots) * 100, 1) : 0.0;

        if ($primeOccRate > 60.0) {
            $surgeAmount = 250.0;
            $estSurgeUplift = (float) ($weekendPrimeBookings * $surgeAmount);
            $recommendations[] = [
                'id'                      => 'rec_weekend_prime_surge',
                'title'                   => 'Weekend Prime Floodlights Surge',
                'day_of_week'             => 'Fri, Sat, Sun',
                'time_window'             => '18:00 - 22:00',
                'current_occupancy_rate'  => $primeOccRate,
                'suggested_mode'          => 'delta',
                'suggested_rate'          => $surgeAmount,
                'projected_monthly_uplift'=> $estSurgeUplift,
                'rationale'               => "Weekend evening prime hours have {$primeOccRate}% utilization. Adding a ₹{$surgeAmount} peak floodlight modifier directly captures ₹" . number_format($estSurgeUplift) . "/month in incremental pure profit.",
                'rule_payload'            => [
                    'name'         => 'Weekend Prime Floodlights',
                    'rule_type'    => 'time_of_day',
                    'weekdays'     => ['friday', 'saturday', 'sunday'],
                    'start_time'   => '18:00',
                    'end_time'     => '22:00',
                    'pricing_mode' => 'delta',
                    'amount'       => $surgeAmount,
                    'priority'     => 20,
                ],
            ];
        }

        return $recommendations;
    }
}
