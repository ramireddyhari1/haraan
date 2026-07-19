<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Domain service for event-related business logic.
 *
 * Queries the {@see Event} model for published data and gracefully
 * falls back to a small set of demo events when the database is empty.
 */
final class EventService
{
    /**
     * Return a feed of published events, newest first.
     *
     * When no published events exist in the database the service returns
     * a curated set of fallback demo events so the front-end always has
     * something to render.
     *
     * @return Collection<int, object>
     */
    public function getFeed(int $limit = 6): Collection
    {
        $events = Event::query()
            ->where('status', 'published')
            ->orderByDesc('date')
            ->take($limit)
            ->get();

        if ($events->isNotEmpty()) {
            return $events;
        }

        return $this->fallbackEvents()->take($limit);
    }

    /**
     * Return a collection of premium banner events formatted for the carousel.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getBannerEvents(): Collection
    {
        // Real published events only — the banner carousel never invents demo
        // events. It also requires a real poster: an image-less event (e.g. a bare
        // test listing) would otherwise headline the hero with the /events.png brand
        // placeholder, which reads as broken. Filtered in PHP so the JSON images
        // column is inspected the same way the map below reads it. When nothing
        // qualifies this returns empty and the view hides the whole carousel.
        $events = Event::query()
            ->where('status', 'published')
            ->orderByDesc('date')
            ->get()
            ->filter(function (object $event): bool {
                $images = is_string($event->images)
                    ? json_decode($event->images, true)
                    : (array) ($event->images ?? []);

                return ! empty($images);
            })
            ->take(3)
            ->values();

        return $events->map(function (object $event): array {
            $date = $event->date;
            $metaStr = 'Upcoming';
            if ($date instanceof \Carbon\Carbon) {
                $metaStr = $date->format('D, j M • g:i A');
            } elseif (is_string($date)) {
                try {
                    $metaStr = \Illuminate\Support\Carbon::parse($date)->format('D, j M • g:i A');
                } catch (\Throwable) {
                    $metaStr = $date;
                }
            }

            // Normalized, browser-loadable URL: resolves an uploaded storage path to a
            // /storage/… URL and leaves a pasted external URL untouched (via MediaUrl,
            // the same resolver the event cards use). The raw images[0] was a bare
            // "events/xxx.png" that the browser resolved against /events → 404.
            $poster = $event->heroImageUrl() ?? '/events.png';

            return [
                'id'     => $event->id,
                'title'  => $event->title,
                'venue'  => $event->venue,
                'price'  => '₹' . number_format((float) $event->price) . ' onwards',
                'poster' => $poster,
                'meta'   => $metaStr,
            ];
        });
    }

    /**
     * Search events and venues by keyword.
     *
     * @return array{events?: list<array<string, mixed>>, venues?: list<array<string, mixed>>}
     */
    public function search(string $query, string $type = 'all'): array
    {
        $results = [];

        if ($query === '') {
            return $results;
        }

        if ($type === 'all' || $type === 'events') {
            $results['events'] = [
                ['id' => 1, 'title' => 'Zomato Feeding India ft. Dua Lipa', 'category' => 'Music', 'venue' => 'MMRDA Grounds, BKC'],
                ['id' => 2, 'title' => 'Tech Conference 2026', 'category' => 'Workshops', 'venue' => 'Taj Lands End'],
            ];
        }

        if ($type === 'all' || $type === 'venues') {
            $results['venues'] = [
                ['id' => 1, 'title' => 'Cricket Ground Mumbai', 'sport' => 'Cricket', 'location' => 'Bombay Gymkhana'],
                ['id' => 2, 'title' => 'Football Arena', 'sport' => 'Football', 'location' => 'Cooperage Ground'],
            ];
        }

        return $results;
    }

    /**
     * Find a single event by its ID or abort with 404.
     *
     * Checks the database first, then falls back to the demo catalogue.
     * Adds sensible default values for artist, tags and images when they
     * are missing from the persisted record.
     */
    public function findOrFail(string $id): object
    {
        $event = Event::find($id);

        if ($event !== null) {
            return $this->hydrateDefaults($event);
        }

        $event = $this->fallbackDetailEvents()->firstWhere('id', (int) $id);

        if ($event === null) {
            abort(404);
        }

        return $event;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Ensure an Eloquent event has sensible defaults for optional fields.
     */
    private function hydrateDefaults(Event $event): Event
    {
        if (!isset($event->artist)) {
            $event->artist = (object) [
                'name' => 'Featured Host',
                'description' => 'Event organizer/artist description.',
                'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80',
            ];
        }

        if (!isset($event->tags)) {
            $event->tags = ['Featured'];
        }

        if (!isset($event->images) || empty($event->images)) {
            $event->images = ['/events.png'];
        }

        return $event;
    }

    /**
     * Demo events shown in the feed when the database is empty.
     *
     * @return Collection<int, object>
     */
    private function fallbackEvents(): Collection
    {
        $now = now();

        return collect([
            (object) [
                'id' => 1,
                'title' => 'Zomato Feeding India ft. Dua Lipa',
                'category' => 'Music',
                'venue' => 'MMRDA Grounds, BKC',
                'date' => $now->copy()->addDays(10),
                'price' => 4500,
                'images' => ['/events.png'],
                'status' => 'published',
            ],
            (object) [
                'id' => 3,
                'title' => 'Stand-up Open Mic Night',
                'category' => 'Comedy',
                'venue' => 'South Bombay Studio',
                'date' => $now->copy()->addDays(6),
                'price' => 799,
                'images' => ['/events.png'],
                'status' => 'published',
            ],
            (object) [
                'id' => 4,
                'title' => 'Creator Workshop Series',
                'category' => 'Workshops',
                'venue' => 'Taj Lands End',
                'date' => $now->copy()->addDays(12),
                'price' => 1200,
                'images' => ['/events.png'],
                'status' => 'published',
            ],
        ]);
    }

    /**
     * Extended fallback events with full detail fields (artist, tags, description).
     *
     * @return Collection<int, object>
     */
    private function fallbackDetailEvents(): Collection
    {
        return collect([
            (object) [
                'id' => 1,
                'title' => 'Zomato Feeding India ft. Dua Lipa',
                'category' => 'Music',
                'venue' => 'MMRDA Grounds, BKC',
                'date' => now()->addDays(10),
                'price' => 4500,
                'images' => ['/events.png'],
                'description' => 'Zomato Feeding India Concert is back! This year, we are thrilled to host the global pop sensation Dua Lipa. Join us for a night of music, energy, and a noble cause as we fight hunger across the nation.',
                'artist' => (object) [
                    'name' => 'Dua Lipa',
                    'description' => 'Global pop icon with multiple Grammy awards and chart-topping hits.',
                    'image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=150&q=80',
                ],
                'tags' => ['Live Concert', 'Pop', 'Noble Cause'],
            ],
            (object) [
                'id' => 3,
                'title' => 'Stand-Up Open Mic Night',
                'category' => 'Comedy',
                'venue' => 'South Bombay Studio',
                'date' => now()->addDays(6),
                'price' => 799,
                'images' => ['/events.png'],
                'description' => 'Get ready for a sensational night of laughter and pure entertainment! India\'s finest up-and-coming stand-up comedians take the stage to perform their absolute best live material.',
                'artist' => (object) [
                    'name' => 'Local Comics',
                    'description' => 'A curated selection of the region\'s most talented comedians, ready to keep you laughing all night.',
                    'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80',
                ],
                'tags' => ['Comedy', 'Open Mic', 'Nightlife'],
            ],
            (object) [
                'id' => 4,
                'title' => 'Creator Workshop Series',
                'category' => 'Workshops',
                'venue' => 'Taj Lands End',
                'date' => now()->addDays(12),
                'price' => 1200,
                'images' => ['/events.png'],
                'description' => 'Learn from the best creators in the industry. This workshop series covers content creation, personal branding, and audience growth.',
                'artist' => (object) [
                    'name' => 'Expert Creators',
                    'description' => 'Top industry experts and successful content creators sharing their insights.',
                    'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&q=80',
                ],
                'tags' => ['Workshop', 'Learning', 'Creator Economy'],
            ],
        ]);
    }
}
