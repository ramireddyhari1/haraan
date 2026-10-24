<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Venue;
use App\Support\VenuePhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Make the sized WebP copies for photos uploaded before copies existed.
 *
 * Saving a venue does this by itself; this is the backfill, and the way to
 * rebuild everything after the widths or quality change (--force). Safe to run
 * repeatedly: a photo whose copies are already current is skipped.
 */
final class BuildVenuePhotoVariants extends Command
{
    protected $signature = 'venues:photo-variants {--venue= : Only this venue id} {--force : Rebuild copies that are already current}';

    protected $description = 'Make sized WebP copies of venue photos for the website and app';

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('This PHP has no GD WebP support (enable the gd extension).');

            return self::FAILURE;
        }

        $venues = Venue::query()
            ->when($this->option('venue'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->get(['id', 'name', 'images']);

        $disk = Storage::disk('public');
        $photos = 0;
        $written = 0;
        $rows = [];

        foreach ($venues as $venue) {
            foreach ((array) $venue->images as $image) {
                if (! is_string($image) || ($path = VenuePhoto::pathOf($image)) === null) {
                    continue;
                }

                $photos++;
                $made = VenuePhoto::generate($path, (bool) $this->option('force'));
                $written += $made;

                $dims = $disk->exists($path) ? VenuePhoto::dimensions($disk->path($path)) : null;
                $sizes = array_map(
                    fn (string $p): string => round($disk->size($p) / 1024).'K',
                    VenuePhoto::variantsOf($path),
                );
                $rows[] = [
                    $venue->id,
                    basename($path),
                    $dims ? $dims[0].'×'.$dims[1] : 'missing',
                    $disk->exists($path) ? round($disk->size($path) / 1024).'K' : '-',
                    $sizes ? implode(' ', array_map(fn ($w, $s) => "{$w}:{$s}", array_keys($sizes), $sizes)) : '-',
                    $made > 0 ? 'made' : 'current',
                ];
            }
        }

        $this->table(['Venue', 'Photo', 'Size', 'Original', 'Copies (width:weight)', ''], $rows);
        $this->info("{$photos} photos, {$written} copies written.");

        return self::SUCCESS;
    }
}
