<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\PlacePhotos;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves one photo from the venue's Google listing through our own domain.
 *
 * Proxying rather than pointing an <img> at Google buys three things:
 *
 *  - The API key stays on the server. A Places media URL carries its key in the
 *    query string, so putting one in the page hands every visitor a billable key.
 *  - Cost is bounded by our own data. The route is addressed by event and slot,
 *    so the only photos anyone can bill us for are ones we already decided to
 *    show — a raw reference proxy would let a stranger fetch anything on Maps.
 *  - Each photo is fetched once, not once per visitor. Bytes are cached on disk
 *    for 30 days, which is also the longest Google's terms allow us to hold them.
 *
 * Note the cache is keyed by the photo reference, so two events at the same
 * venue share the file, and it deliberately lives outside the event's own
 * gallery — these images are Google's to lend, not ours to keep.
 */
final class VenuePhotoController extends Controller
{
    public function show(int $id, int $index): SymfonyResponse
    {
        abort_unless($index >= 0 && $index < PlacePhotos::maxPhotos(), 404);

        $event = Event::query()->findOrFail($id);

        $photoName = PlacePhotos::photoName($event, $index);
        abort_if($photoName === null, 404);

        // No extension: the format varies per photo (Google serves PNG as often
        // as JPEG here) and is read back off the bytes when serving.
        $path = 'placephotos/' . sha1($photoName) . '.img';
        $disk = Storage::disk('local');

        $fresh = $disk->exists($path)
            && $disk->lastModified($path) > now()->subDays(PlacePhotos::ttlDays())->getTimestamp();

        if (! $fresh) {
            $bytes = PlacePhotos::fetchMedia($photoName);

            if ($bytes !== null) {
                $disk->put($path, $bytes);
            } elseif (! $disk->exists($path)) {
                abort(404);
            }
            // Otherwise the refresh failed but a copy is still on disk. Serving
            // it beats punching a hole in the strip.
        }

        $body = (string) $disk->get($path);

        return new Response($body, 200, [
            'Content-Type'   => PlacePhotos::mimeOf($body),
            'Content-Length' => (string) strlen($body),
            // A week in the browser — inside Google's 30-day cap, long enough
            // that a returning visitor never re-downloads the strip.
            'Cache-Control'  => 'public, max-age=604800',
        ]);
    }
}
