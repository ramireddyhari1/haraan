<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\VenuePhoto;
use Illuminate\Console\Command;

/**
 * Make the sized WebP copies of event posters and gallery photos uploaded
 * before copies existed. Saving an event does this by itself; this is the
 * backfill. Safe to run repeatedly: current copies are skipped.
 */
final class BuildEventPhotoVariants extends Command
{
    protected $signature = 'events:photo-variants {--event= : Only this event id} {--force : Rebuild copies that are already current}';

    protected $description = 'Make sized WebP copies of event posters and gallery photos';

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('This PHP has no GD WebP support (enable the gd extension).');

            return self::FAILURE;
        }

        $photos = 0;
        $written = 0;

        Event::query()
            ->when($this->option('event'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->select(['id', 'images', 'gallery'])
            ->chunkById(100, function ($events) use (&$photos, &$written): void {
                foreach ($events as $event) {
                    $all = array_merge((array) $event->images, (array) $event->gallery);
                    foreach ($all as $image) {
                        if (! is_string($image) || VenuePhoto::pathOf($image) === null) {
                            continue;
                        }
                        $photos++;
                        $written += VenuePhoto::generate($image, (bool) $this->option('force'));
                    }
                }
            });

        $this->info("{$photos} event photos, {$written} copies written.");

        return self::SUCCESS;
    }
}
