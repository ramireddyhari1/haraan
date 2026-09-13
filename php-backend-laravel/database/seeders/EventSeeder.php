<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Event;
use App\Models\HostProfile;
use App\Models\TicketType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class EventSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $partner = User::query()->where('role', 'partner')->first();
        if (! $partner) {
            $partner = User::first();
        }
        if (! $partner) {
            $partner = User::create([
                'name' => 'Haraan Live Experiences',
                'email' => 'partner@haraan.com',
                'password' => bcrypt('password'),
                'role' => 'partner',
            ]);
        }

        // Ensure partner has a verified public host profile
        HostProfile::updateOrCreate(
            ['user_id' => $partner->id],
            [
                'slug' => 'haraan-live-experiences',
                'display_name' => 'Haraan Live Experiences',
                'tagline' => 'Premier concerts, sports tournaments & cultural festivals',
                'about' => 'Official curator of top-tier concerts, live comedy tours, sports tournaments and cultural festivals across Mumbai, Bengaluru, Delhi NCR, and Hyderabad.',
                'city' => 'Mumbai',
                'is_public' => true,
                'verified_at' => now(),
            ]
        );

        $now = Carbon::now();

        $demoEvents = [
            // 1. Concerts - Mumbai
            [
                'title' => 'Sunburn Arena ft. Alan Walker - Walkerworld Tour 2026',
                'description' => 'Experience the biggest EDM spectacle of the year as global electronic sensation Alan Walker brings the Walkerworld Tour to Mumbai! Enjoy electrifying beats, hypnotic LED visuals, stadium lasers, and unforgettable anthems live under the open sky.',
                'category' => 'Concerts',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(5)->setTime(18, 0),
                'time' => '06:00 PM',
                'duration' => '5 Hours',
                'location' => 'Jio World Garden, BKC, Bandra East, Mumbai',
                'venue' => 'Jio World Garden',
                'city' => 'Mumbai',
                'price' => 1499.00,
                'total_slots' => 5000,
                'available_slots' => 3840,
                'rating' => 4.9,
                'ratings_count' => 428,
                'age_limit' => '16+',
                'languages' => ['English', 'Hindi'],
                'status' => 'published',
                'placements' => ['for_you', 'trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Early Bird General', 'price' => 1499.00, 'capacity' => 1500, 'description' => 'General entry to lawn area with access to food & beverage zones.'],
                    ['name' => 'Phase 1 Standard Arena', 'price' => 1999.00, 'capacity' => 2500, 'description' => 'Entry to the main arena with prime acoustic sound view.'],
                    ['name' => 'VIP Front Row Lounge', 'price' => 3999.00, 'capacity' => 1000, 'description' => 'Dedicated elevated platform, express festival entry & complimentary beverage voucher.'],
                ],
            ],

            // 2. Concerts - Bengaluru
            [
                'title' => 'Arijit Singh Symphony Live in Concert',
                'description' => "India's beloved voice, Arijit Singh, performs live accompanied by a full 45-piece European symphony orchestra. A magical 3-hour journey through his timeless romantic hits, soulful melodies, and acoustic classics.",
                'category' => 'Concerts',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(12)->setTime(19, 0),
                'time' => '07:00 PM',
                'duration' => '3.5 Hours',
                'location' => 'Manpho Convention Centre Grounds, Outer Ring Road, Bengaluru',
                'venue' => 'Manpho Convention Grounds',
                'city' => 'Bengaluru',
                'price' => 1999.00,
                'total_slots' => 8000,
                'available_slots' => 4200,
                'rating' => 4.9,
                'ratings_count' => 612,
                'age_limit' => 'All Ages',
                'languages' => ['Hindi', 'Bengali'],
                'status' => 'published',
                'placements' => ['for_you', 'trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Silver Tier (Seated)', 'price' => 1999.00, 'capacity' => 3000, 'description' => 'Tiered seating in the rear acoustic zone.'],
                    ['name' => 'Gold Arena (Prime)', 'price' => 3499.00, 'capacity' => 3500, 'description' => 'Central seating section with great sightlines to stage.'],
                    ['name' => 'Platinum Fan Pit', 'price' => 5999.00, 'capacity' => 1500, 'description' => 'Closest seats directly in front of the symphony orchestra.'],
                ],
            ],

            // 3. Sports - Mumbai
            [
                'title' => 'Mumbai Floodlight T20 Super Cup - Quarter Finals',
                'description' => 'High-octane club cricket under brilliant stadium floodlights! Top city franchises go head-to-head in a do-or-die knockout match. Enjoy stadium commentary, cheer zones, live music, and food street stalls.',
                'category' => 'Sports',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(4)->setTime(19, 30),
                'time' => '07:30 PM',
                'duration' => '4 Hours',
                'location' => 'Wankhede Stadium, Churchgate, Marine Drive, Mumbai',
                'venue' => 'Wankhede Stadium',
                'city' => 'Mumbai',
                'price' => 299.00,
                'total_slots' => 3500,
                'available_slots' => 2100,
                'rating' => 4.8,
                'ratings_count' => 234,
                'age_limit' => 'All Ages',
                'languages' => ['English', 'Hindi', 'Marathi'],
                'status' => 'published',
                'placements' => ['for_you', 'trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'East Stand Pass', 'price' => 299.00, 'capacity' => 2000, 'description' => 'General entry to East Stand with full pitch view.'],
                    ['name' => 'North Stand Premium', 'price' => 599.00, 'capacity' => 1000, 'description' => 'Shaded stand directly behind bowler arm.'],
                    ['name' => 'Pavilion Club Box', 'price' => 1299.00, 'capacity' => 500, 'description' => 'Air-conditioned corporate lounge with high-tea included.'],
                ],
            ],

            // 4. Sports - Bengaluru
            [
                'title' => 'Bangalore Premier 5v5 Futsal Midnight League',
                'description' => 'Fast, fluid rink football under state-of-the-art turf lighting! 16 registered corporate and university clubs compete in round-robin and playoffs for the city championship trophy and cash prizes.',
                'category' => 'Sports',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(7)->setTime(20, 0),
                'time' => '08:00 PM',
                'duration' => '3.5 Hours',
                'location' => 'South United Football Arena, Ulsoor, Bengaluru',
                'venue' => 'South United Football Ground',
                'city' => 'Bengaluru',
                'price' => 199.00,
                'total_slots' => 500,
                'available_slots' => 310,
                'rating' => 4.7,
                'ratings_count' => 88,
                'age_limit' => 'All Ages',
                'languages' => ['English', 'Kannada'],
                'status' => 'published',
                'placements' => ['for_you'],
                'images' => [
                    'https://images.unsplash.com/photo-1522778526097-ce0a22ceb253?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Spectator Entry', 'price' => 199.00, 'capacity' => 400, 'description' => 'Rinkside grandstand spectator access + energy drink.'],
                    ['name' => 'Dugout VIP Pass', 'price' => 399.00, 'capacity' => 100, 'description' => 'Pitch-level dugout viewing + tournament merchandise.'],
                ],
            ],

            // 5. Sports - Hyderabad
            [
                'title' => 'Hyderabad Smashers Open Badminton Tournament',
                'description' => 'Official state-level open singles & doubles tournament on professional BWF synthetic courts. Witness fiery smashes, deceptive drops, and rising national champions battling for ranking points.',
                'category' => 'Sports',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(10)->setTime(9, 0),
                'time' => '09:00 AM',
                'duration' => '8 Hours',
                'location' => 'Gachibowli Indoor Stadium, Gachibowli, Hyderabad',
                'venue' => 'Gachibowli Indoor Stadium',
                'city' => 'Hyderabad',
                'price' => 249.00,
                'total_slots' => 800,
                'available_slots' => 550,
                'rating' => 4.8,
                'ratings_count' => 124,
                'age_limit' => 'All Ages',
                'languages' => ['English', 'Telugu'],
                'status' => 'published',
                'placements' => ['trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Day Pass (All Courts)', 'price' => 249.00, 'capacity' => 600, 'description' => 'Full access to watch heats and semifinals across 8 courts.'],
                    ['name' => 'Center Court Finals Seat', 'price' => 499.00, 'capacity' => 200, 'description' => 'Reserved seating for the evening championship matches.'],
                ],
            ],

            // 6. Comedy - Mumbai
            [
                'title' => 'Zakir Khan - Tathastu & Beyond Live Standup Tour',
                'description' => "The iconic Sakht Launda returns with an all-new hour of heart-warming storytelling, hilarious family dynamics, and classic Kanpur-Delhi humor. Guaranteed non-stop laughs from the first minute to the curtain call.",
                'category' => 'Comedy',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(3)->setTime(20, 0),
                'time' => '08:00 PM',
                'duration' => '100 Mins',
                'location' => 'NCPA Tata Theatre, Nariman Point, Mumbai',
                'venue' => 'NCPA Tata Theatre',
                'city' => 'Mumbai',
                'price' => 799.00,
                'total_slots' => 1100,
                'available_slots' => 195,
                'rating' => 4.9,
                'ratings_count' => 740,
                'age_limit' => '16+',
                'languages' => ['Hindi'],
                'status' => 'published',
                'placements' => ['for_you', 'trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Balcony Seating', 'price' => 799.00, 'capacity' => 400, 'description' => 'Comfortable tiered balcony seating.'],
                    ['name' => 'Orchestra Stalls', 'price' => 1299.00, 'capacity' => 500, 'description' => 'Mid-auditorium stalls with direct eye-level stage view.'],
                    ['name' => 'Prime Front Rows', 'price' => 1999.00, 'capacity' => 200, 'description' => 'First 5 rows right next to the stage.'],
                ],
            ],

            // 7. Comedy - Delhi NCR
            [
                'title' => 'Abhishek Upmanyu - Jealous of Sabziwala Special',
                'description' => "Rapid-fire observations, sarcastic relatable rants, and quirky life reflections delivered in Abhishek's signature deadpan energy. Catch his newest solo material live in Delhi before it hits streaming platforms.",
                'category' => 'Comedy',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(9)->setTime(19, 30),
                'time' => '07:30 PM',
                'duration' => '90 Mins',
                'location' => 'Siri Fort Auditorium, August Kranti Marg, Siri Fort, New Delhi',
                'venue' => 'Siri Fort Auditorium',
                'city' => 'Delhi NCR',
                'price' => 699.00,
                'total_slots' => 1800,
                'available_slots' => 420,
                'rating' => 4.8,
                'ratings_count' => 510,
                'age_limit' => '16+',
                'languages' => ['Hindi', 'English'],
                'status' => 'published',
                'placements' => ['trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1527224857830-43a7acc85260?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'General Tier 2', 'price' => 699.00, 'capacity' => 800, 'description' => 'Standard auditorium seat.'],
                    ['name' => 'Prime Tier 1', 'price' => 1199.00, 'capacity' => 700, 'description' => 'Front central seating.'],
                    ['name' => 'VIP Meet & Greet Pass', 'price' => 1999.00, 'capacity' => 300, 'description' => 'Front row seating + backstage photo opportunity.'],
                ],
            ],

            // 8. Workshops - Mumbai
            [
                'title' => 'Electric Wheel Pottery & Terracotta Clay Workshop',
                'description' => "Learn centering, coning, wheel-throwing, and shaping ceramic bowls, mugs, and vases from experienced pottery masters. Complete hands-on creative session where all clay, tools, glazing, and kiln firing for your creations are included.",
                'category' => 'Workshops',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(2)->setTime(11, 0),
                'time' => '11:00 AM',
                'duration' => '2.5 Hours',
                'location' => 'The Clay Studio, Pali Hill, Bandra West, Mumbai',
                'venue' => 'The Clay Studio Bandra',
                'city' => 'Mumbai',
                'price' => 1299.00,
                'total_slots' => 30,
                'available_slots' => 9,
                'rating' => 4.9,
                'ratings_count' => 95,
                'age_limit' => '12+',
                'languages' => ['English', 'Hindi'],
                'status' => 'published',
                'placements' => ['for_you'],
                'images' => [
                    'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Solo Creator Pass', 'price' => 1299.00, 'capacity' => 20, 'description' => 'Includes 1 dedicated electric wheel, 3kg clay, and 2 fired pieces.'],
                    ['name' => 'Duo Pass (2 Persons)', 'price' => 2299.00, 'capacity' => 10, 'description' => 'Pair package with shared wheel & take-home ceramic set.'],
                ],
            ],

            // 9. Workshops - Bengaluru
            [
                'title' => 'Artisanal Coffee Sensory & Latte Art Masterclass',
                'description' => 'Explore the journey from specialty green coffee beans to the perfect espresso extraction. Learn sensory tasting, palate calibration, silky micro-foam milk steaming, and pour stunning latte art rosettas.',
                'category' => 'Workshops',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(8)->setTime(15, 0),
                'time' => '03:00 PM',
                'duration' => '2 Hours',
                'location' => 'Third Wave Coffee Roastery, 100ft Road, Indiranagar, Bengaluru',
                'venue' => 'Third Wave Roastery',
                'city' => 'Bengaluru',
                'price' => 899.00,
                'total_slots' => 35,
                'available_slots' => 15,
                'rating' => 4.8,
                'ratings_count' => 82,
                'age_limit' => '16+',
                'languages' => ['English'],
                'status' => 'published',
                'placements' => ['for_you'],
                'images' => [
                    'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Workshop Entry + Tasting', 'price' => 899.00, 'capacity' => 25, 'description' => 'Includes tasting flight of 4 single origins & live latte art demo.'],
                    ['name' => 'Barista Bundle (with 250g Beans)', 'price' => 1399.00, 'capacity' => 10, 'description' => 'Entry + 250g freshly roasted whole beans + brew guide.'],
                ],
            ],

            // 10. Nightlife - Mumbai
            [
                'title' => 'Neon Sundowner: Melodic Deep House Rooftop Session',
                'description' => 'Catch the golden hour with panoramic 360-degree skyline and sea views from South Mumbai. Featuring top electronic and melodic house resident DJs, craft cocktails, tapas, and neon ambient lighting.',
                'category' => 'Nightlife',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(2)->setTime(17, 30),
                'time' => '05:30 PM',
                'duration' => '6 Hours',
                'location' => 'AER Lounge, Four Seasons Hotel, Dr. E. Moses Road, Worli, Mumbai',
                'venue' => 'AER Lounge Rooftop',
                'city' => 'Mumbai',
                'price' => 999.00,
                'total_slots' => 300,
                'available_slots' => 75,
                'rating' => 4.7,
                'ratings_count' => 180,
                'age_limit' => '21+',
                'languages' => ['English'],
                'status' => 'published',
                'placements' => ['for_you', 'trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Couple Entry (Full Cover)', 'price' => 999.00, 'capacity' => 150, 'description' => 'Full cover redeemable on food & cocktails.'],
                    ['name' => 'Stag Entry (with Drink)', 'price' => 1499.00, 'capacity' => 100, 'description' => 'Entry includes 1 complimentary premium drink.'],
                    ['name' => 'VIP Cabana Table (6 Guests)', 'price' => 7999.00, 'capacity' => 50, 'description' => 'Private reserved rooftop lounge cabana with dedicated butler service.'],
                ],
            ],

            // 11. Nightlife - Goa
            [
                'title' => 'Underground Techno Pulse: Boiler Room Warehouse Experience',
                'description' => 'An authentic 360-degree raw warehouse rave under the Goan night sky. Featuring driving hypnotic basslines, analog laser mapping, and an electric crowd dancing until sunrise.',
                'category' => 'Nightlife',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(14)->setTime(22, 0),
                'time' => '10:00 PM',
                'duration' => '7 Hours',
                'location' => 'HillTop Arena, Ozran Beach Road, Small Vagator, Goa',
                'venue' => 'HillTop Arena',
                'city' => 'Goa',
                'price' => 1199.00,
                'total_slots' => 900,
                'available_slots' => 340,
                'rating' => 4.9,
                'ratings_count' => 310,
                'age_limit' => '21+',
                'languages' => ['English'],
                'status' => 'published',
                'placements' => ['trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1545128485-c400e7702796?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Early Bird Techno Pass', 'price' => 1199.00, 'capacity' => 400, 'description' => 'Entry before 11:30 PM.'],
                    ['name' => 'General Phase 1 Pass', 'price' => 1699.00, 'capacity' => 500, 'description' => 'Anytime entry through the night.'],
                ],
            ],

            // 12. Festivals - Mumbai
            [
                'title' => 'Haraan Gourmet Food Truck & Street Flavors Carnival 2026',
                'description' => 'The largest open-air culinary carnival in Mumbai! Explore 60+ artisan food trucks, gourmet bakeries, specialty street grills, live indie acoustic bands, retro flea pop-ups, and fun games for the entire family.',
                'category' => 'Festivals',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(16)->setTime(12, 0),
                'time' => '12:00 PM',
                'duration' => '10 Hours',
                'location' => 'MMRDA Grounds, BKC, Bandra East, Mumbai',
                'venue' => 'MMRDA Grounds BKC',
                'city' => 'Mumbai',
                'price' => 199.00,
                'total_slots' => 6000,
                'available_slots' => 4600,
                'rating' => 4.8,
                'ratings_count' => 410,
                'age_limit' => 'All Ages',
                'languages' => ['English', 'Hindi', 'Marathi'],
                'status' => 'published',
                'placements' => ['for_you', 'trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1555244162-803834f70033?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1509228627159-645183b6e3d7?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Single Day Pass', 'price' => 199.00, 'capacity' => 4500, 'description' => 'Full day carnival entry to food zone, flea market, and music stage.'],
                    ['name' => 'Family Festival Pass (4 Admits)', 'price' => 699.00, 'capacity' => 1500, 'description' => 'Admits 4 people + includes ₹200 food truck coupon.'],
                ],
            ],

            // 13. Festivals - Pune
            [
                'title' => 'Kala Rang Monsoon Arts, Craft & Folk Music Festival',
                'description' => 'Celebrate the magic of indie music, pottery exhibitions, handloom artisanal bazaars, and traditional folk dances. A lively family-friendly cultural gathering celebrating homegrown talent.',
                'category' => 'Festivals',
                'booking_format' => 'single',
                'visibility' => 'public',
                'date' => $now->copy()->addDays(21)->setTime(16, 0),
                'time' => '04:00 PM',
                'duration' => '6 Hours',
                'location' => 'Phoenix Marketcity Courtyard, Viman Nagar, Pune',
                'venue' => 'Phoenix Marketcity Courtyard',
                'city' => 'Pune',
                'price' => 399.00,
                'total_slots' => 2500,
                'available_slots' => 1850,
                'rating' => 4.7,
                'ratings_count' => 155,
                'age_limit' => 'All Ages',
                'languages' => ['Hindi', 'English', 'Marathi'],
                'status' => 'published',
                'placements' => ['trending'],
                'images' => [
                    'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=1200&q=80',
                ],
                'tiers' => [
                    ['name' => 'Festival General Pass', 'price' => 399.00, 'capacity' => 2000, 'description' => 'Lawn seating, craft bazaar access, and main stage concert entry.'],
                    ['name' => 'All-Access Art Pass', 'price' => 799.00, 'capacity' => 500, 'description' => 'Front stage access + complimentary folk art workshop session.'],
                ],
            ],
        ];

        foreach ($demoEvents as $data) {
            $tiers = $data['tiers'] ?? [];
            unset($data['tiers']);

            $event = Event::create(array_merge($data, [
                'partner_id' => $partner->id,
            ]));

            foreach ($tiers as $index => $tier) {
                TicketType::create([
                    'event_id' => $event->id,
                    'name' => $tier['name'],
                    'price' => $tier['price'],
                    'capacity' => $tier['capacity'],
                    'description' => $tier['description'] ?? null,
                    'sold' => 0,
                    'sort' => $index,
                    'kind' => 'standard',
                    'admits' => 1,
                    'visible' => true,
                ]);
            }
        }
    }
}

