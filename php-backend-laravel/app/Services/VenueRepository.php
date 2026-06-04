<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * In-memory repository for sports venue data.
 *
 * Provides read-only access to the curated list of venues
 * until a database-backed implementation replaces it.
 */
final class VenueRepository
{
    /**
     * Return every venue in the catalogue.
     *
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return $this->venues();
    }

    /**
     * Find a single venue by its ID.
     */
    public function find(int $id): ?object
    {
        return $this->venues()->firstWhere('id', $id);
    }

    // ------------------------------------------------------------------
    // Data
    // ------------------------------------------------------------------

    /**
     * Build and return the full venue collection.
     *
     * @return Collection<int, object>
     */
    private function venues(): Collection
    {
        return collect([
            (object) [
                'id' => 1,
                'title' => 'Wankhede Premium Turf',
                'category' => 'Cricket',
                'sports' => ['Cricket', 'Football'],
                'courts' => [
                    'Cricket' => ['Pitch A (Full Turf)', 'Practice Cage 1', 'Practice Cage 2'],
                    'Football' => ['Turf East (7v7)', 'Turf West (5v5)'],
                ],
                'location' => 'Churchgate, Mumbai',
                'price' => 1800,
                'rating' => '4.9',
                'reviews' => 142,
                'image' => 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=800&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1599839619722-39751411ea63?auto=format&fit=crop&w=600&q=80',
                ],
                'badge' => 'Curated',
                'hours' => '06:00 AM - 11:00 PM',
                'description' => 'Experience the thrill of playing cricket at a world-class premium venue in the heart of Mumbai. Equipped with professional-grade artificial turf, high-intensity shadowless LED floodlights, full-sized batting cages, and a spacious players dugout. Perfect for club matches, practice sessions, and corporate tournaments.',
                'amenities' => ['Professional Floodlights', 'Covered Batting Nets', 'Drinking Water', 'Free Parking', 'Washrooms', 'First Aid Kit'],
                'reviews_list' => [
                    (object) ['user' => 'Rohan K.', 'rating' => 5, 'date' => 'May 12, 2026', 'comment' => 'Exceptional turf quality! The floodlights are bright and even, perfect for night sessions. Highly recommended!'],
                    (object) ['user' => 'Anjali M.', 'rating' => 4, 'date' => 'May 08, 2026', 'comment' => 'Very clean facility and polite staff. Parking was easy to find. The locker rooms were well maintained.'],
                    (object) ['user' => 'Vikram S.', 'rating' => 5, 'date' => 'Apr 28, 2026', 'comment' => 'Top-tier setup. Booking is seamless, courts are perfectly flat, and water is readily available. Will book weekly!'],
                ],
            ],
            (object) [
                'id' => 2,
                'title' => 'Bandra Football Arena',
                'category' => 'Football',
                'sports' => ['Football'],
                'courts' => [
                    'Football' => ['Arena Pitch A (7v7)', 'Arena Pitch B (5v5)'],
                ],
                'location' => 'Carter Road, Bandra West',
                'price' => 2200,
                'rating' => '4.8',
                'reviews' => 98,
                'image' => 'https://images.unsplash.com/photo-1579952363873-27f3bade9f55?auto=format&fit=crop&w=800&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1431324155629-1a6edd1dec1d?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1517649763962-0c623066013b?auto=format&fit=crop&w=600&q=80',
                ],
                'badge' => 'Floodlit',
                'hours' => '05:00 AM - 12:00 AM',
                'description' => 'A premier 7-a-side and 5-a-side football facility located right by the seaside. The arena uses premium FIFA-certified shock-absorbing synthetic grass to reduce knee strain. Offering a stunning ocean breeze, dynamic scoreboard, and spectator seating.',
                'amenities' => ['FIFA-Approved Turf', 'Changing Rooms', 'Showers', 'Spectator Seating', 'Refreshment Lounge', 'First Aid'],
                'reviews_list' => [
                    (object) ['user' => 'Karan J.', 'rating' => 5, 'date' => 'May 15, 2026', 'comment' => 'The best 7-a-side pitch in the city. The turf is extremely soft on the joints. Sunset games here are phenomenal.'],
                    (object) ['user' => 'Priya S.', 'rating' => 4, 'date' => 'May 02, 2026', 'comment' => 'Excellent venue. Shower rooms are neat and clean. The bookings fill up fast, so secure your slots in advance.'],
                ],
            ],
            (object) [
                'id' => 3,
                'title' => 'The Elite Badminton Club',
                'category' => 'Badminton',
                'sports' => ['Badminton'],
                'courts' => [
                    'Badminton' => ['Yonex Court 1', 'Yonex Court 2', 'Yonex Court 3', 'Practice Court 4'],
                ],
                'location' => 'Andheri East, Mumbai',
                'price' => 600,
                'rating' => '4.7',
                'reviews' => 210,
                'image' => 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1613918431208-67520e535948?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1521537634581-0dced2fee2ef?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1563861826100-9cb868fdcd1d?auto=format&fit=crop&w=600&q=80',
                ],
                'badge' => 'Indoor AC',
                'hours' => '06:00 AM - 10:00 PM',
                'description' => 'Featuring 6 premium Yonex-approved synthetic court mats laid over shock-absorbent wooden structures. The facility is fully air-conditioned with high ceiling clearances and anti-glare professional lighting designed for professional play.',
                'amenities' => ['Air Conditioning', 'Yonex Synthetic Mats', 'Locker Rooms', 'Racket Rental', 'Shuttle Shop', 'Valet Parking'],
                'reviews_list' => [
                    (object) ['user' => 'Amit H.', 'rating' => 5, 'date' => 'May 10, 2026', 'comment' => 'High quality courts. No glare from the lights, and temperature is perfectly regulated. Top class!'],
                    (object) ['user' => 'Neha V.', 'rating' => 4, 'date' => 'Apr 25, 2026', 'comment' => 'Great wooden-based courts. Racket restringing service is available on-site, which is super convenient.'],
                ],
            ],
            (object) [
                'id' => 4,
                'title' => 'Infinity Olympic Pool',
                'category' => 'Swimming',
                'sports' => ['Swimming'],
                'courts' => [
                    'Swimming' => ['Lane 1 (Pro-Fast)', 'Lane 2', 'Lane 3', 'Lane 4'],
                ],
                'location' => 'Worli Sea Face, Mumbai',
                'price' => 1200,
                'rating' => '4.9',
                'reviews' => 76,
                'image' => 'https://images.unsplash.com/photo-1519315901367-f34ff9154487?auto=format&fit=crop&w=800&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1576013551627-0cc20b96c2a7?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1476108621677-3c620901b5e7?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1530541930197-ff16ac917b0e?auto=format&fit=crop&w=600&q=80',
                ],
                'badge' => 'Olympic',
                'hours' => '06:00 AM - 09:00 PM',
                'description' => 'A standard 50-meter Olympic-sized swimming pool featuring temperature-controlled water filtration systems. Complete with anti-wave lane dividers, starting blocks, and qualified professional lifeguards on duty at all times.',
                'amenities' => ['Temperature Controlled', 'Olympic Lanes', 'Qualified Lifeguards', 'Separate Steam Rooms', 'Towels Provided', 'Cafe'],
                'reviews_list' => [
                    (object) ['user' => 'Siddharth S.', 'rating' => 5, 'date' => 'May 18, 2026', 'comment' => 'Beautiful sea view while swimming. The pool is remarkably clean, and water is warmed perfectly during mornings.'],
                    (object) ['user' => 'Riya G.', 'rating' => 5, 'date' => 'May 04, 2026', 'comment' => 'Professional lanes, clean locker rooms, and great steam room access included. Well worth the price.'],
                ],
            ],
            (object) [
                'id' => 5,
                'title' => 'Juhu Clay Court Club',
                'category' => 'Tennis',
                'sports' => ['Tennis'],
                'courts' => [
                    'Tennis' => ['Red Clay Court A', 'Red Clay Court B'],
                ],
                'location' => 'Juhu Scheme, Mumbai',
                'price' => 1500,
                'rating' => '4.8',
                'reviews' => 54,
                'image' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1560079007-a53227ef6901?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1622279457486-62dce4a435ae?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1551776235-dde6d482980b?auto=format&fit=crop&w=600&q=80',
                ],
                'badge' => 'Clay Court',
                'hours' => '06:00 AM - 10:00 PM',
                'description' => 'Immaculate imported red clay courts designed to replicate European professional standards. Offers excellent slide capability, standard ball bounce, and high-intensity lighting systems for premium tennis experiences.',
                'amenities' => ['Imported Red Clay', 'Floodlights', 'Ball Boy Service', 'Tennis Coach Access', 'Shower Rooms', 'Lounge'],
                'reviews_list' => [
                    (object) ['user' => 'Zain B.', 'rating' => 5, 'date' => 'May 06, 2026', 'comment' => 'Perfect clay surface! It is hard to find high-quality red clay in Mumbai. Maintenance is superb.'],
                    (object) ['user' => 'Meera D.', 'rating' => 4, 'date' => 'Apr 20, 2026', 'comment' => 'Wonderful place for clay-court practice. BALL BOY service was professional. Booking recommended during cooler hours.'],
                ],
            ],
            (object) [
                'id' => 6,
                'title' => 'BKC Basketball Cage',
                'category' => 'Basketball',
                'sports' => ['Basketball'],
                'courts' => [
                    'Basketball' => ['Full Cage Court', 'Half Cage Court'],
                ],
                'location' => 'Bandra Kurla Complex',
                'price' => 900,
                'rating' => '4.6',
                'reviews' => 88,
                'image' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=800&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1519766304817-4f37bda74a27?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=600&q=80',
                    'https://images.unsplash.com/photo-1505666287802-931dc83948e9?auto=format&fit=crop&w=600&q=80',
                ],
                'badge' => '24/7 Access',
                'hours' => 'Open 24 Hours',
                'description' => 'A vibrant, urban cage court featuring high-grip acrylic flooring, official height breakaway rims, heavy-duty chain nets, and premium surround-sound stadium lighting. Perfect for fast-paced 3v3 or full-court 5v5 basketball games.',
                'amenities' => ['Acrylic Court Finish', 'Official Flex Rims', 'Chain Nets', '24/7 Access', 'Drinking Water Station', 'Spectator Fence'],
                'reviews_list' => [
                    (object) ['user' => 'Rahul M.', 'rating' => 5, 'date' => 'May 19, 2026', 'comment' => 'Excellent grip on the court floor. Flex rims are great. Playing under the neon cage lights late at night is a vibe!'],
                    (object) ['user' => 'Kabir D.', 'rating' => 4, 'date' => 'May 11, 2026', 'comment' => 'Solid court. Very easy to access in BKC. Chain nets sound amazing when you sink a shot.'],
                ],
            ],
        ]);
    }
}
