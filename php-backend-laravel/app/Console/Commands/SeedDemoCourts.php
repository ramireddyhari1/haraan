<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Console\Command;

/**
 * Adds a handful of demo courts to venues that have none, so the partner app's
 * multi-court day grid has real columns to render. Idempotent — a venue that
 * already has courts is left untouched.
 */
final class SeedDemoCourts extends Command
{
    protected $signature = 'demo:seed-courts';

    protected $description = 'Add demo courts to venues that have none (idempotent).';

    public function handle(): int
    {
        // venueId => [ [name, sports[]], … ]
        $plan = [
            1 => [['Court 1', ['Badminton']], ['Court 2', ['Badminton']], ['Court 3', ['Badminton']]],
            2 => [['Pitch A', ['Football']], ['Pitch B', ['Football']]],
        ];

        foreach ($plan as $venueId => $courts) {
            $venue = Venue::query()->find($venueId);
            if ($venue === null) {
                $this->warn("venue {$venueId} not found — skipped");
                continue;
            }
            if ($venue->courts()->exists()) {
                $this->info("venue {$venueId} ({$venue->name}) already has courts — skipped");
                continue;
            }
            foreach ($courts as $i => [$name, $sports]) {
                VenueCourt::query()->create([
                    'venue_id'   => $venueId,
                    'name'       => $name,
                    'sports'     => $sports,
                    'is_active'  => true,
                    'sort_order' => $i,
                ]);
            }
            $this->info("venue {$venueId} ({$venue->name}): added ".count($courts).' courts');
        }

        return self::SUCCESS;
    }
}
