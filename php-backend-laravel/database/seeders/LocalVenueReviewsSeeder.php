<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueReview;
use App\Services\VenueReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only: a spread of real-shaped reviews on the showcase venues, posted through
 * VenueReviewService (the app/website write path) so names, sources and the venue
 * rating are exactly what a player's rating produces. One is hidden and one deleted,
 * so /control's Hidden and Deleted tabs have something to show.
 *
 *   php artisan db:seed --class=LocalVenueReviewsSeeder
 */
class LocalVenueReviewsSeeder extends Seeder
{
    private const PLAYERS = [
        'Teja Varma', 'Anusha Reddy', 'Kiran Kumar', 'Meghana Rao', 'Pavan Kalyan', 'Divya Sri', 'Naveen Chowdary', 'Harsha Vardhan',
    ];

    /** [venue name, player index, stars, text|null, source] */
    private const REVIEWS = [
        ['Shuttle House Badminton', 0, 5, 'Best wooden courts in Vijayawada. AC actually works in the afternoon.', 'app'],
        ['Shuttle House Badminton', 1, 4, 'Good courts and lighting. Parking is tight on weekends.', 'web'],
        ['Shuttle House Badminton', 2, 5, null, 'app'],
        ['Shuttle House Badminton', 3, 3, 'Court 3 and 4 mats are a bit worn. Staff were helpful.', 'app'],
        ['Kickoff Arena', 4, 5, 'Turf is great, floodlights are bright enough for night games.', 'app'],
        ['Kickoff Arena', 5, 4, 'Nice place. Washrooms could be cleaner.', 'web'],
        ['Kickoff Arena', 6, 2, 'Booked 7 PM, pitch was still occupied for 10 minutes.', 'app'],
        ['Boundary Line Cricket Nets', 7, 4, 'Bowling machine is worth it. Nets are well maintained.', 'app'],
        ['Rally Point Pickleball', 0, 5, 'Free paddles for first-timers — brought the whole office.', 'web'],
        ['Rally Point Pickleball', 2, 1, 'call me on 98xxxxxx for cheap bookings!!!', 'web'], // spam → hidden
        ['Boundary Line Cricket Nets', 3, 1, 'test test', 'app'],                                 // junk → deleted
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalVenueReviewsSeeder only runs locally.');

            return;
        }

        $players = array_map(function (string $name): User {
            $email = strtolower(str_replace(' ', '.', $name)) . '@haraan.test';
            $u = User::firstOrNew(['email' => $email]);
            $u->forceFill(['name' => $name, 'status' => 'active', 'password' => Hash::make('player1234')])->save();

            return $u;
        }, self::PLAYERS);

        VenueReview::withTrashed()->whereIn('user_id', array_map(fn (User $u) => $u->id, $players))->forceDelete();

        $service = app(VenueReviewService::class);
        foreach (self::REVIEWS as $i => [$venueName, $p, $stars, $text, $source]) {
            $venue = Venue::query()->where('name', $venueName)->first();
            if ($venue === null) {
                continue;
            }
            $review = $service->submit($players[$p], $venue, $stars, $text, $source);
            // Spread them over the last few weeks so "ago" and "this month" read like life.
            $review->forceFill(['created_at' => now()->subHours(($i + 1) * 31)])->saveQuietly();
        }

        $spam = VenueReview::query()->where('text', 'like', 'call me on%')->first();
        $spam?->forceFill(['is_active' => false, 'hidden_reason' => 'Spam — phone number for off-platform bookings'])->save();
        VenueReview::query()->where('text', 'test test')->first()?->delete();

        Venue::query()->get()->each->refreshRating();
        $this->command?->info('Reviews: ' . VenueReview::query()->count() . ' live/hidden, ' . VenueReview::onlyTrashed()->count() . ' deleted');
    }
}
