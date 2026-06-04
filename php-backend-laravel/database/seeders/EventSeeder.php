<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $partner = User::first();
        if (! $partner) {
            $partner = User::factory()->create([
                'name' => 'Demo Partner',
                'email' => 'partner@example.com',
            ]);
        }

        $now = Carbon::now();

        $events = [
            [
                'title' => 'Mumbai Indians vs Royal Challengers',
                'description' => 'An exciting T20 night under lights.',
                'category' => 'Cricket',
                'date' => $now->copy()->addDays(7),
                'time' => '19:30',
                'location' => 'Wankhede Stadium, Mumbai',
                'venue' => 'Wankhede',
                'price' => 499.00,
                'total_slots' => 5000,
                'available_slots' => 4200,
                'images' => [
                    'https://images.unsplash.com/photo-1521412644187-c49fa049e84d?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'published',
            ],
            [
                'title' => 'City Football Cup Finals',
                'description' => 'Local clubs compete for the city cup.',
                'category' => 'Football',
                'date' => $now->copy()->addDays(14),
                'time' => '18:00',
                'location' => 'MMA Stadium, Mumbai',
                'venue' => 'MMA Stadium',
                'price' => 299.00,
                'total_slots' => 8000,
                'available_slots' => 7600,
                'images' => [
                    'https://images.unsplash.com/photo-1505678261036-a3fcc5e884ee?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'published',
            ],
            [
                'title' => 'Badminton Open Tournament',
                'description' => 'Singles and doubles open tournament.',
                'category' => 'Badminton',
                'date' => $now->copy()->addDays(21),
                'time' => '09:00',
                'location' => 'City Sports Complex',
                'venue' => 'Hall A',
                'price' => 199.00,
                'total_slots' => 200,
                'available_slots' => 120,
                'images' => [
                    'https://images.unsplash.com/photo-1517649763962-0c623066013b?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'published',
            ],
            [
                'title' => 'Community Swimming Gala',
                'description' => 'Heats for all age groups.',
                'category' => 'Swimming',
                'date' => $now->copy()->addDays(30),
                'time' => '08:00',
                'location' => 'Aquatic Centre',
                'venue' => 'Main Pool',
                'price' => 99.00,
                'total_slots' => 300,
                'available_slots' => 300,
                'images' => [
                    'https://images.unsplash.com/photo-1508609349937-5ec4ae374ebf?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'published',
            ],
            [
                'title' => 'Indie Music Night',
                'description' => 'Local bands and open mic.',
                'category' => 'Music',
                'date' => $now->copy()->addDays(5),
                'time' => '20:00',
                'location' => 'The Loft',
                'venue' => 'The Loft',
                'price' => 149.00,
                'total_slots' => 250,
                'available_slots' => 200,
                'images' => [
                    'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'published',
            ],
            [
                'title' => 'Bollywood Retro Night',
                'description' => 'A nostalgic evening of classic Bollywood hits and dance.',
                'category' => 'Music',
                'date' => $now->copy()->addDays(3),
                'time' => '19:00',
                'location' => 'Kala Rang',
                'venue' => 'Kala Rang Auditorium',
                'price' => 249.00,
                'total_slots' => 400,
                'available_slots' => 350,
                'images' => [
                    'https://images.unsplash.com/photo-1529257414778-1965b43b2c19?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'PUBLISHED',
            ],
            [
                'title' => 'Stand-up Comedy Night',
                'description' => 'Laugh out loud with top local comedians sharing fresh sets.',
                'category' => 'Comedy',
                'date' => $now->copy()->addDays(9),
                'time' => '21:00',
                'location' => 'The Laugh Hub',
                'venue' => 'Studio 2',
                'price' => 199.00,
                'total_slots' => 180,
                'available_slots' => 120,
                'images' => [
                    'https://images.unsplash.com/photo-1520975911961-7f8b5e3d5c3a?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'PUBLISHED',
            ],
            [
                'title' => 'Art & Crafts Fair',
                'description' => 'Handmade goods from local artisans — shopping, workshops and more.',
                'category' => 'Lifestyle',
                'date' => $now->copy()->addDays(12),
                'time' => '10:00',
                'location' => 'Open Grounds',
                'venue' => 'Exhibition Hall',
                'price' => 0.00,
                'total_slots' => 1000,
                'available_slots' => 1000,
                'images' => [
                    'https://images.unsplash.com/photo-1509228627159-645183b6e3d7?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'PUBLISHED',
            ],
            [
                'title' => 'Sunrise Yoga Session',
                'description' => 'Gentle morning yoga suitable for all levels in the park.',
                'category' => 'Wellness',
                'date' => $now->copy()->addDays(2),
                'time' => '06:30',
                'location' => 'City Park Lawn',
                'venue' => 'North Lawn',
                'price' => 99.00,
                'total_slots' => 60,
                'available_slots' => 60,
                'images' => [
                    'https://images.unsplash.com/photo-1508873699372-7ae6f4b7d4b6?auto=format&fit=crop&w=1200&q=80'
                ],
                'status' => 'PUBLISHED',
            ],
        ];

        foreach ($events as $e) {
            Event::create(array_merge($e, ['partner_id' => $partner->id]));
        }
    }
}
