<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only: four published, bookable venues so the website's Pulse → venue → slot →
 * review → Razorpay (test mode) path can be walked end to end.
 *
 *   php artisan db:seed --class=LocalVenueShowcaseSeeder
 *
 * buyer  : player@haraan.test  / player1234
 * owner  : venue-owner@haraan.test / partner1234
 *
 * Photos are hotlinked Unsplash URLs (the venue image helper passes http(s) through).
 * Idempotent: venues are matched by name and their courts/slots rebuilt.
 */
class LocalVenueShowcaseSeeder extends Seeder
{
    private const PHOTO = 'https://images.unsplash.com/%s?auto=format&fit=crop&w=1400&q=70';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalVenueShowcaseSeeder only runs locally.');

            return;
        }

        $owner = User::firstOrNew(['email' => 'venue-owner@haraan.test']);
        $owner->forceFill([
            'name' => 'Ravi Teja', 'role' => 'PARTNER', 'partner_type' => 'venue',
            'status' => 'active', 'password' => Hash::make('partner1234'),
        ])->save();

        $buyer = User::firstOrNew(['email' => 'player@haraan.test']);
        $buyer->forceFill([
            'name' => 'Sai Kiran', 'status' => 'active', 'password' => Hash::make('player1234'),
        ])->save();

        foreach ($this->venues() as $spec) {
            $courts = $spec['courts'];
            unset($spec['courts']);

            $venue = Venue::firstOrNew(['name' => $spec['name']]);
            $venue->fill($spec + [
                'partner_id' => $owner->id,
                'status' => 'published',
                'published_at' => now(),
                'is_active' => true,
                'is_bookable' => true,
                'slot_minutes' => 60,
                'booking_window_days' => 14,
            ]);
            $venue->images = array_map(fn (string $id) => sprintf(self::PHOTO, $id), $spec['images']);
            $venue->save();

            $venue->courts()->delete();
            foreach ($courts as $i => $court) {
                VenueCourt::create($court + ['venue_id' => $venue->id, 'is_active' => true, 'sort_order' => $i]);
            }

            $venue->slots()->delete();
            $venue->regenerateSlotsFromHours();
            $venue->slots()->update(['price' => $spec['price']]);

            $this->command?->info("#{$venue->id} {$venue->name}: {$venue->courts()->count()} courts, {$venue->slots()->count()} slots");
        }
    }

    /** @return list<array<string, mixed>> */
    private function venues(): array
    {
        $daily = fn (string $open, string $close) => array_fill_keys(
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            ['open' => $open, 'close' => $close],
        );

        return [
            [
                'name' => 'Kickoff Arena',
                'category' => 'Football',
                'kind' => 'turf',
                'location' => 'Benz Circle',
                'city' => 'Vijayawada',
                'address' => '40-1-52, MG Road, near Benz Circle, Vijayawada, Andhra Pradesh 520010',
                'latitude' => 16.4997, 'longitude' => 80.6560,
                'price' => 1200,
                'sports' => ['Football', 'Cricket'],
                'tagline' => 'FIFA-grade 5-a-side turf under LED floodlights',
                'about' => "Two full-size 5-a-side pitches with 50 mm FIFA-quality artificial grass, shock pad underneath and LED floodlights for night games. Box-cricket nets roll down on the second pitch.\n\nKit bags can be left at the front desk; bibs and match balls are free with every booking.",
                'images' => ['photo-1575361204480-aadea25e6e68', 'photo-1529900748604-07564a03e7a6', 'photo-1551958219-acbc608c6377'],
                'amenities' => ['Parking', 'Washroom', 'Drinking water', 'Floodlights', 'Changing room', 'Seating', 'Equipment rental'],
                'rules' => ['Non-marking shoes mandatory', 'No smoking', 'No alcohol', 'Advance booking recommended'],
                'hours_json' => $daily('06:00', '23:00'),
                'convenience_fee_type' => 'flat', 'convenience_fee_value' => 30,
                'cancel_free_hours' => 6, 'cancel_refund_percent' => 100,
                'courts' => [
                    ['name' => 'Pitch A', 'sports' => ['Football'], 'price' => 1200, 'peak_price' => 1500, 'peak_start' => '6:00 PM', 'peak_end' => '11:00 PM', 'peak_days' => []],
                    ['name' => 'Pitch B', 'sports' => ['Football', 'Cricket'], 'price' => 1000, 'peak_price' => 1300, 'peak_days' => ['Sat', 'Sun']],
                ],
            ],
            [
                'name' => 'Shuttle House Badminton',
                'category' => 'Badminton',
                'kind' => 'court',
                'location' => 'Labbipet',
                'city' => 'Vijayawada',
                'address' => '3rd Floor, Sri Plaza, Labbipet, Vijayawada, Andhra Pradesh 520010',
                'latitude' => 16.5062, 'longitude' => 80.6400,
                'price' => 400,
                'sports' => ['Badminton'],
                'tagline' => 'Four wooden BWF courts, fully air-conditioned',
                'about' => "Four indoor courts on sprung maple wood with Yonex mats, glare-free lighting and AC that actually keeps up in May.\n\nRackets on hire at the counter. Shuttles sold per tube.",
                'images' => ['photo-1626224583764-f87db24ac4ea', 'photo-1613918431703-aa50889e3be4', 'photo-1599474924187-334a4ae5bd3c'],
                'amenities' => ['Parking', 'Washroom', 'AC', 'Drinking water', 'Changing room', 'WiFi', 'Equipment rental'],
                'rules' => ['Non-marking shoes mandatory', 'Carry your own racket / gear', 'No outside food'],
                'hours_json' => $daily('05:00', '22:00'),
                'convenience_fee_type' => 'percent', 'convenience_fee_value' => 2,
                'cancel_free_hours' => 12, 'cancel_refund_percent' => 100,
                'courts' => [
                    ['name' => 'Court 1', 'sports' => ['Badminton'], 'price' => 400, 'peak_price' => 500, 'peak_start' => '6:00 PM', 'peak_end' => '10:00 PM', 'peak_days' => []],
                    ['name' => 'Court 2', 'sports' => ['Badminton'], 'price' => 400, 'peak_price' => 500, 'peak_start' => '6:00 PM', 'peak_end' => '10:00 PM', 'peak_days' => []],
                    ['name' => 'Court 3', 'sports' => ['Badminton'], 'price' => 350],
                    ['name' => 'Court 4', 'sports' => ['Badminton'], 'price' => 350],
                ],
            ],
            [
                'name' => 'Boundary Line Cricket Nets',
                'category' => 'Cricket',
                'kind' => 'turf',
                'location' => 'Gunadala',
                'city' => 'Vijayawada',
                'address' => 'Eluru Road, opp. Gunadala Church, Vijayawada, Andhra Pradesh 520004',
                'latitude' => 16.5245, 'longitude' => 80.6620,
                'price' => 600,
                'sports' => ['Cricket'],
                'tagline' => 'Astro nets, bowling machine and a box-cricket turf',
                'about' => "Three practice nets on astro turf plus a 70 × 40 ft box-cricket arena. The bowling machine (up to 140 km/h) can be added at the desk.\n\nSuits academies, office teams and Sunday regulars.",
                'images' => ['photo-1531415074968-036ba1b575da', 'photo-1540747913346-19e32dc3e97e', 'photo-1624526267942-ab0ff8a3e972'],
                'amenities' => ['Parking', 'Washroom', 'Drinking water', 'Floodlights', 'Seating', 'Café', 'CCTV / Security'],
                'rules' => ['No smoking', 'No alcohol', 'Carry your own racket / gear'],
                'hours_json' => $daily('06:00', '22:00'),
                'convenience_fee_type' => 'flat', 'convenience_fee_value' => 20,
                'fees' => [['label' => 'Floodlight charge', 'type' => 'flat', 'value' => 50]],
                'cancel_free_hours' => 24, 'cancel_refund_percent' => 50,
                'courts' => [
                    ['name' => 'Box Arena', 'sports' => ['Cricket'], 'price' => 1400, 'peak_price' => 1700, 'peak_days' => ['Sat', 'Sun']],
                    ['name' => 'Net 1', 'sports' => ['Cricket'], 'price' => 600],
                    ['name' => 'Net 2', 'sports' => ['Cricket'], 'price' => 600],
                ],
            ],
            [
                'name' => 'Rally Point Pickleball',
                'category' => 'Pickleball',
                'kind' => 'court',
                'location' => 'Moghalrajpuram',
                'city' => 'Vijayawada',
                'address' => '29-37-12, Siddhartha Nagar, Moghalrajpuram, Vijayawada 520010',
                'latitude' => 16.5105, 'longitude' => 80.6455,
                'price' => 500,
                'sports' => ['Pickleball', 'Tennis'],
                'tagline' => 'Cushioned outdoor courts with shade sails',
                'about' => "Three cushioned acrylic pickleball courts and one hard tennis court, all under shade sails for the afternoon sun.\n\nPaddles and balls on the house for first-timers.",
                'images' => ['photo-1612534847738-b3af9bc31f0c', 'photo-1595435934249-5df7ed86e1c0', 'photo-1622279457486-62dcc4a431d6'],
                'amenities' => ['Parking', 'Washroom', 'Drinking water', 'Floodlights', 'Equipment rental'],
                'rules' => ['Non-marking shoes mandatory', 'No pets'],
                'hours_json' => $daily('06:00', '21:00'),
                'cancel_free_hours' => 4, 'cancel_refund_percent' => 100,
                'courts' => [
                    ['name' => 'Pickle 1', 'sports' => ['Pickleball'], 'price' => 500],
                    ['name' => 'Pickle 2', 'sports' => ['Pickleball'], 'price' => 500],
                    ['name' => 'Pickle 3', 'sports' => ['Pickleball'], 'price' => 450],
                    ['name' => 'Tennis Court', 'sports' => ['Tennis'], 'price' => 700, 'peak_price' => 900, 'peak_start' => '5:00 PM', 'peak_end' => '9:00 PM', 'peak_days' => []],
                ],
            ],
        ];
    }
}
