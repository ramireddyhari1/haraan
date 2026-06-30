<?php

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
