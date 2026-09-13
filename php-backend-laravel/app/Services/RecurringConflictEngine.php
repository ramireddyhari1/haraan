<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\StandingContract;
use App\Models\VenueBlock;
use App\Models\VenueCourt;
use Illuminate\Support\Carbon;

/**
 * Smart conflict detection engine for recurring reservations and standing slots.
 *
 * Verifies that a regular recurring time window across N future weeks will not
 * collide with existing bookings, maintenance blocks, holiday closures, tournaments,
 * parent/child split courts, or another standing contract.
 */
class RecurringConflictEngine
{
    /**
     * Check conflicts for a prospective recurring contract across a date range.
     *
     * @param  int  $weeksToCheck  Number of weeks to simulate ahead (default 12)
     * @return array{
     *     is_clear: bool,
     *     total_sessions_checked: int,
     *     conflicting_sessions_count: int,
     *     clear_sessions_count: int,
     *     conflicts: list<array<string, mixed>>,
     *     schedule_preview: list<array<string, mixed>>
     * }
     */
    public function checkConflicts(
        int $venueId,
        int $courtId,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        string $startDate,
        ?string $endDate = null,
        int $weeksToCheck = 12,
        ?int $excludeContractId = null,
    ): array {
        $court = VenueCourt::findOrFail($courtId);
        $startDay = Carbon::parse($startDate);
        $endDay = $endDate ? Carbon::parse($endDate) : $startDay->copy()->addWeeks($weeksToCheck);

        $startMin = $this->timeToMinutes($startTime);
        $endMin = $this->timeToMinutes($endTime);

        $targetWeekday = strtolower(trim($dayOfWeek));

        $conflicts = [];
        $schedulePreview = [];

        $current = $startDay->copy();
        $totalChecked = 0;

        while ($current->lte($endDay) && $totalChecked < 52) {
            $currentWeekday = strtolower($current->format('l'));

            if ($currentWeekday === $targetWeekday && $current->gte(today())) {
                $totalChecked++;
                $dateStr = $current->toDateString();

                $conflictFound = null;

                // 1. Check existing booking overlap on this court
                $bookingOverlap = $this->findBookingOverlap($venueId, $courtId, $dateStr, $startMin, $endMin);
                if ($bookingOverlap !== null) {
                    $conflictFound = [
                        'date' => $dateStr,
                        'weekday' => ucfirst($targetWeekday),
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'conflict_type' => 'booking',
                        'conflicting_id' => $bookingOverlap->id,
                        'reason' => "Existing booking #{$bookingOverlap->id} (" . ($bookingOverlap->guest_name ?: 'Online Booking') . ") already reserves {$bookingOverlap->start_time}-{$bookingOverlap->end_time}.",
                        'suggested_action' => 'skip_date',
                    ];
                }

                // 2. Check venue blocks (maintenance, tournament, holiday)
                if ($conflictFound === null) {
                    $blockOverlap = $this->findBlockOverlap($venueId, $courtId, $current, $startMin, $endMin);
                    if ($blockOverlap !== null) {
                        $conflictFound = [
                            'date' => $dateStr,
                            'weekday' => ucfirst($targetWeekday),
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'conflict_type' => 'venue_block',
                            'conflicting_id' => $blockOverlap->id,
                            'reason' => "Venue block ({$blockOverlap->label()}): {$blockOverlap->title}.",
                            'suggested_action' => 'skip_date',
                        ];
                    }
                }

                // 3. Check colliding active standing contracts
                if ($conflictFound === null) {
                    $contractOverlap = $this->findContractOverlap($venueId, $courtId, $targetWeekday, $dateStr, $startMin, $endMin, $excludeContractId);
                    if ($contractOverlap !== null) {
                        $conflictFound = [
                            'date' => $dateStr,
                            'weekday' => ucfirst($targetWeekday),
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'conflict_type' => 'standing_contract',
                            'conflicting_id' => $contractOverlap->id,
                            'reason' => "Contract #{$contractOverlap->id} ({$contractOverlap->customer_name}) holds this slot ({$contractOverlap->start_time}-{$contractOverlap->end_time}).",
                            'suggested_action' => 'change_slot_or_court',
                        ];
                    }
                }

                if ($conflictFound !== null) {
                    $conflicts[] = $conflictFound;
                    $schedulePreview[] = [
                        'date' => $dateStr,
                        'weekday' => ucfirst($targetWeekday),
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'status' => 'conflict',
                        'conflict' => $conflictFound,
                    ];
                } else {
                    $schedulePreview[] = [
                        'date' => $dateStr,
                        'weekday' => ucfirst($targetWeekday),
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'status' => 'available',
                        'conflict' => null,
                    ];
                }
            }

            $current->addDay();
        }

        $conflictingCount = count($conflicts);
        $clearCount = $totalChecked - $conflictingCount;

        return [
            'is_clear' => $conflictingCount === 0,
            'total_sessions_checked' => $totalChecked,
            'conflicting_sessions_count' => $conflictingCount,
            'clear_sessions_count' => $clearCount,
            'conflicts' => $conflicts,
            'schedule_preview' => $schedulePreview,
        ];
    }

    private function findBookingOverlap(int $venueId, int $courtId, string $date, ?int $startMin, ?int $endMin): ?Booking
    {
        $court = VenueCourt::find($courtId);
        $courtIds = $court ? $court->allRelatedCourtIds() : [$courtId];

        $existing = Booking::query()
            ->where('booking_type', 'venue')
            ->where('venue_id', $venueId)
            ->whereIn('venue_court_id', $courtIds)
            ->whereDate('slot_date', $date)
            ->whereIn('status', ['PAID', 'CONFIRMED', 'PENDING'])
            ->get();

        foreach ($existing as $b) {
            $es = $this->timeToMinutes($b->start_time);
            $ee = $this->timeToMinutes($b->end_time);

            if ($es === null || $ee === null) {
                return $b;
            }

            if ($startMin !== null && $endMin !== null && $startMin < $ee && $endMin > $es) {
                return $b;
            }
        }

        return null;
    }

    private function findBlockOverlap(int $venueId, int $courtId, Carbon $day, ?int $startMin, ?int $endMin): ?VenueBlock
    {
        $blocks = VenueBlock::query()->applyingOn($venueId, $day)->get();

        foreach ($blocks as $block) {
            if (! $block->coversCourt($courtId)) {
                continue;
            }

            if ($block->isAllDay() || $startMin === null || $endMin === null) {
                return $block;
            }

            $bs = $this->timeToMinutes($block->start_time);
            $be = $this->timeToMinutes($block->end_time);

            if ($bs === null || $be === null) {
                return $block;
            }

            if ($startMin < $be && $endMin > $bs) {
                return $block;
            }
        }

        return null;
    }

    private function findContractOverlap(
        int $venueId,
        int $courtId,
        string $weekday,
        string $date,
        ?int $startMin,
        ?int $endMin,
        ?int $excludeContractId,
    ): ?StandingContract {
        $court = VenueCourt::find($courtId);
        $courtIds = $court ? $court->allRelatedCourtIds() : [$courtId];

        $contracts = StandingContract::query()
            ->where('venue_id', $venueId)
            ->whereIn('venue_court_id', $courtIds)
            ->where('day_of_week', $weekday)
            ->whereIn('status', [StandingContract::STATUS_ACTIVE, StandingContract::STATUS_AT_RISK])
            ->whereDate('active_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('active_until')->orWhereDate('active_until', '>=', $date);
            })
            ->when($excludeContractId !== null, fn ($q) => $q->where('id', '!=', $excludeContractId))
            ->get();

        foreach ($contracts as $c) {
            $cs = $this->timeToMinutes($c->start_time);
            $ce = $this->timeToMinutes($c->end_time);

            if ($cs === null || $ce === null) {
                return $c;
            }

            if ($startMin !== null && $endMin !== null && $startMin < $ce && $endMin > $cs) {
                return $c;
            }
        }

        return null;
    }

    private function timeToMinutes(?string $time): ?int
    {
        if (! $time) {
            return null;
        }

        $parts = explode(':', trim($time));
        if (count($parts) < 2) {
            return null;
        }

        return ((int) $parts[0]) * 60 + ((int) $parts[1]);
    }
}
