<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\PricingRuleLog;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CourtHierarchyService
{
    /**
     * Get complete court hierarchy for a venue, separating composite parents and standalone units.
     *
     * @return array<string, mixed>
     */
    public function getHierarchy(Venue $venue): array
    {
        $allCourts = VenueCourt::where('venue_id', $venue->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $parents = [];
        $standalone = [];

        $courtsById = $allCourts->keyBy('id');

        // Group children by parent_court_id
        $childrenByParent = [];
        foreach ($allCourts as $court) {
            if ($court->parent_court_id !== null) {
                $childrenByParent[$court->parent_court_id][] = $court;
            }
        }

        foreach ($allCourts as $court) {
            if ($court->parent_court_id !== null) {
                // Skip child courts at top-level iteration
                continue;
            }

            $children = $childrenByParent[$court->id] ?? [];
            if ($court->is_composite || ! empty($children)) {
                $parents[] = [
                    'id'                        => $court->id,
                    'name'                      => $court->name,
                    'kind'                      => $court->kind,
                    'is_composite'              => true,
                    'split_type'                => $court->split_type,
                    'price'                     => $court->price,
                    'sports'                    => $court->sportsList(),
                    'allow_simultaneous_booking' => $court->allow_simultaneous_booking,
                    'children'                  => array_map(static fn (VenueCourt $c) => [
                        'id'              => $c->id,
                        'name'            => $c->name,
                        'partition_label' => $c->partition_label,
                        'price'           => $c->price,
                        'seats'           => $c->seats,
                        'sports'          => $c->sportsList(),
                        'is_active'       => $c->is_active,
                    ], $children),
                ];
            } else {
                $standalone[] = [
                    'id'           => $court->id,
                    'name'         => $court->name,
                    'kind'         => $court->kind,
                    'is_composite' => false,
                    'price'        => $court->price,
                    'sports'       => $court->sportsList(),
                ];
            }
        }

        return [
            'composite_courts'  => $parents,
            'standalone_courts' => $standalone,
            'total_composite'   => count($parents),
            'total_standalone'  => count($standalone),
        ];
    }

    /**
     * Split a parent court into sub-courts (partitions).
     *
     * @param array<int, array{name: string, label?: string, price?: int|float}> $partitions
     * @return array<string, mixed>
     */
    public function splitCourt(
        Venue $venue,
        int $parentCourtId,
        string $splitType,
        array $partitions,
        ?User $actor = null,
        ?string $ip = null
    ): array {
        /** @var VenueCourt|null $parentCourt */
        $parentCourt = VenueCourt::where('venue_id', $venue->id)->where('id', $parentCourtId)->first();
        if (! $parentCourt) {
            throw ValidationException::withMessages(['court_id' => 'Court not found for this venue.']);
        }

        if (count($partitions) < 2) {
            throw ValidationException::withMessages(['partitions' => 'A court split must define at least 2 partitions.']);
        }

        return DB::transaction(function () use ($venue, $parentCourt, $splitType, $partitions, $actor, $ip) {
            $prevChildren = VenueCourt::where('parent_court_id', $parentCourt->id)->get();
            $prevState = [
                'is_composite' => $parentCourt->is_composite,
                'split_type'   => $parentCourt->split_type,
                'children'     => $prevChildren->pluck('name', 'id')->all(),
            ];

            // Mark parent as composite
            $parentCourt->update([
                'is_composite' => true,
                'split_type'   => $splitType,
            ]);

            $createdChildren = [];
            $order = 1;
            foreach ($partitions as $p) {
                $child = VenueCourt::create([
                    'venue_id'        => $venue->id,
                    'parent_court_id' => $parentCourt->id,
                    'is_composite'    => false,
                    'split_type'      => 'none',
                    'partition_label' => $p['label'] ?? ($p['name'] ?? "Partition {$order}"),
                    'name'            => $p['name'],
                    'kind'            => $parentCourt->kind,
                    'sports'          => $parentCourt->sports,
                    'price'           => isset($p['price']) ? (int) $p['price'] : (int) round(($parentCourt->price ?? 1000) / count($partitions)),
                    'sort_order'      => ($parentCourt->sort_order * 10) + $order,
                    'is_active'       => true,
                ]);
                $createdChildren[] = $child;
                $order++;
            }

            PricingRuleLog::create([
                'venue_id'        => $venue->id,
                'pricing_rule_id' => null,
                'actor_id'        => $actor?->id,
                'action'          => 'court_split',
                'previous_state'  => $prevState,
                'new_state'       => [
                    'parent_court_id' => $parentCourt->id,
                    'split_type'      => $splitType,
                    'created_courts'  => array_map(static fn ($c) => ['id' => $c->id, 'name' => $c->name, 'price' => $c->price], $createdChildren),
                ],
                'ip_address'      => $ip,
            ]);

            return [
                'parent'   => $parentCourt->fresh(),
                'children' => $createdChildren,
            ];
        });
    }

    /**
     * Merge partitions back into the composite parent court.
     */
    public function mergeCourts(Venue $venue, int $parentCourtId, ?User $actor = null, ?string $ip = null): void
    {
        /** @var VenueCourt|null $parentCourt */
        $parentCourt = VenueCourt::where('venue_id', $venue->id)->where('id', $parentCourtId)->first();
        if (! $parentCourt) {
            throw ValidationException::withMessages(['court_id' => 'Court not found for this venue.']);
        }

        $children = VenueCourt::where('parent_court_id', $parentCourt->id)->get();
        if ($children->isEmpty()) {
            throw ValidationException::withMessages(['court_id' => 'This court is not currently split.']);
        }

        // Check for active future bookings on any of the children
        $childIds = $children->pluck('id')->all();
        $hasFutureBookings = Booking::whereIn('venue_court_id', $childIds)
            ->where('date', '>=', now()->format('Y-m-d'))
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->exists();

        if ($hasFutureBookings) {
            throw ValidationException::withMessages([
                'court_id' => 'Cannot merge courts: One or more sub-courts have upcoming confirmed bookings. Reschedule or cancel them first.',
            ]);
        }

        DB::transaction(function () use ($venue, $parentCourt, $children, $actor, $ip) {
            $prevState = [
                'parent_id'  => $parentCourt->id,
                'split_type' => $parentCourt->split_type,
                'children'   => $children->pluck('name', 'id')->all(),
            ];

            // Delete or deactivate children
            VenueCourt::whereIn('id', $children->pluck('id')->all())->delete();

            // Revert parent
            $parentCourt->update([
                'is_composite' => false,
                'split_type'   => 'none',
            ]);

            PricingRuleLog::create([
                'venue_id'        => $venue->id,
                'pricing_rule_id' => null,
                'actor_id'        => $actor?->id,
                'action'          => 'court_merged',
                'previous_state'  => $prevState,
                'new_state'       => ['parent_id' => $parentCourt->id, 'merged' => true],
                'ip_address'      => $ip,
            ]);
        });
    }

    /**
     * Cross-hierarchy collision check enforcing mutual exclusion between parent and child courts.
     *
     * @throws ValidationException
     */
    public function assertHierarchyNoConflict(
        VenueCourt $court,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): void {
        // Case 1: If booking a Child court -> ensure Parent court is not booked
        if ($court->parent_court_id !== null) {
            $parent = VenueCourt::find($court->parent_court_id);
            if ($parent) {
                $overlapQuery = Booking::where('venue_court_id', $parent->id)
                    ->where('date', $date)
                    ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                    ->where(function ($q) use ($startTime, $endTime) {
                        $q->where(function ($sub) use ($startTime, $endTime) {
                            $sub->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        });
                    });

                if ($excludeBookingId !== null) {
                    $overlapQuery->where('id', '!=', $excludeBookingId);
                }

                $parentOverlap = $overlapQuery->first();
                if ($parentOverlap) {
                    throw ValidationException::withMessages([
                        'court_id' => "Court clash: The full arena '{$parent->name}' is already booked on {$date} ({$parentOverlap->start_time}-{$parentOverlap->end_time}).",
                    ]);
                }
            }
        }

        // Case 2: If booking a Composite Parent court -> ensure NONE of its child courts are booked
        if ($court->is_composite || $court->childCourts()->exists()) {
            $childIds = $court->childCourts()->pluck('id')->all();
            if (! empty($childIds)) {
                $overlapQuery = Booking::whereIn('venue_court_id', $childIds)
                    ->where('date', $date)
                    ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
                    ->where(function ($q) use ($startTime, $endTime) {
                        $q->where(function ($sub) use ($startTime, $endTime) {
                            $sub->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        });
                    });

                if ($excludeBookingId !== null) {
                    $overlapQuery->where('id', '!=', $excludeBookingId);
                }

                $childOverlap = $overlapQuery->first();
                if ($childOverlap) {
                    /** @var VenueCourt|null $bookedChild */
                    $bookedChild = VenueCourt::find($childOverlap->venue_court_id);
                    $childName = $bookedChild ? $bookedChild->name : "Sub-court #{$childOverlap->venue_court_id}";
                    throw ValidationException::withMessages([
                        'court_id' => "Court clash: Cannot book composite '{$court->name}' because partition '{$childName}' is already booked on {$date} ({$childOverlap->start_time}-{$childOverlap->end_time}).",
                    ]);
                }
            }
        }
    }
}
