<?php

use App\Services\BookingService;
use App\Services\MatchVerificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Settle ActionBoard matches whose 72h verification window has lapsed → Low trust.
Artisan::command('actionboard:expire-verifications', function () {
    $count = MatchVerificationService::expireOverdue();
    $this->info("Expired {$count} unverified match(es) to low trust.");
})->purpose('Expire overdue match verifications');

Schedule::command('actionboard:expire-verifications')->hourly();

// Release expired ticket locks (abandoned checkouts) so the seat returns to the
// pool for the next buyer, without waiting for someone to next book that event.
Artisan::command('bookings:release-expired', function (BookingService $bookings) {
    $count = $bookings->releaseAllExpired();
    $this->info("Released {$count} expired ticket lock(s).");
})->purpose('Release expired ticket reservation holds');

Schedule::command('bookings:release-expired')->everyMinute();
