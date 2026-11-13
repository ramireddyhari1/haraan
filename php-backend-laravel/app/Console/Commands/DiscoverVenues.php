<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VenueDiscovery\DiscoverySettings;
use App\Services\VenueDiscovery\VenueDiscovery;
use Illuminate\Console\Command;
use Throwable;

/**
 * Finds sports venues on Google for the cities in /control → Venue discovery and files
 * them for review in Venues → Discovery. Nothing goes on the website until approved.
 *
 *   php artisan venues:discover --city=Vijayawada
 *   php artisan venues:discover --all
 *   php artisan venues:discover --city=Guntur --check-only
 */
class DiscoverVenues extends Command
{
    protected $signature = 'venues:discover
        {--city=* : City to search (repeatable)}
        {--all : Every city in the discovery settings}
        {--check-only : Skip Google; only re-run the Playo check}
        {--no-check : Skip the Playo check}';

    protected $description = 'Find sports venues on Google, cross-check them with Playo, and queue them for review';

    public function handle(VenueDiscovery $discovery): int
    {
        $cities = $this->option('all') ? DiscoverySettings::cities() : array_filter(array_map('trim', (array) $this->option('city')));
        if ($cities === []) {
            $this->error('Name a city with --city=… or pass --all.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($cities as $city) {
            try {
                if (! $this->option('check-only')) {
                    $s = $discovery->harvest($city);
                    $this->info("{$city}: {$s['searches']} Google searches, {$s['seen']} places seen — {$s['added']} new, {$s['updated']} already queued, {$s['duplicates']} already on Haraan, {$s['skipped']} not a venue.");
                }
                if (! $this->option('no-check') && VenueDiscovery::crossCheckEnabled()) {
                    $c = $discovery->crossCheck($city);
                    $this->line($c['playo'] === 0
                        ? "{$city}: Playo lists nothing here (or could not be read) — left unchecked."
                        : "{$city}: {$c['matched']} of {$c['checked']} also on Playo ({$c['playo']} Playo listings read).");
                }
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$city}: " . $e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
