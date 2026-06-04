<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use App\Models\Payout;
use App\Models\Booking;

final class AdminDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create a demo coupon if none exist
        if (Coupon::count() === 0) {
            Coupon::create([ 'code' => 'WELCOME10', 'discount' => 10.0, 'max_uses' => 100, 'uses' => 0, 'active' => true ]);
        }

        // If there's a booking, create a pending payout for it
        $booking = Booking::query()->first();
        if ($booking && Payout::where('booking_id', $booking->id)->count() === 0) {
            Payout::create([ 'booking_id' => $booking->id, 'amount' => $booking->total_amount, 'status' => 'PENDING' ]);
        }
    }
}
