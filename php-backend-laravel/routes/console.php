<?php

use App\Models\User;
use App\Services\BookingService;
use App\Services\MatchVerificationService;
use App\Services\MessageJourneys;
use App\Services\Rewards\BadgeService;
use App\Services\Rewards\RewardMaintenance;
use App\Services\WaitlistService;
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

// Matches started and then left: still "Live" with nothing scored for the admin-set hours.
// Marked Abandoned (not finished), so they leave the Live tab without counting as results.
Artisan::command('matches:close-stale {--dry-run : List what would close, change nothing}', function (\App\Services\StaleMatchCloser $closer) {
    if (! \App\Services\StaleMatchCloser::enabled()) {
        $this->info('Auto-close is switched off in /control.');

        return;
    }
    $ids = $closer->close((bool) $this->option('dry-run'));
    $verb = $this->option('dry-run') ? 'Would abandon' : 'Abandoned';
    $this->info("{$verb} ".count($ids).' idle live match(es)'.($ids ? ': '.implode(', ', $ids) : '.'));
})->purpose('Abandon live matches with no scoring for the configured hours');

Schedule::command('matches:close-stale')->hourly()->withoutOverlapping();

// Release expired ticket locks (abandoned checkouts) so the seat returns to the
// pool for the next buyer, without waiting for someone to next book that event.
Artisan::command('bookings:release-expired', function (BookingService $bookings) {
    $count = $bookings->releaseAllExpired();
    $this->info("Released {$count} expired ticket lock(s).");
})->purpose('Release expired ticket reservation holds');

Schedule::command('bookings:release-expired')->everyMinute();

// WhatsApp Desk holds: a lapsed hold whose payment link was paid is confirmed, the rest
// expire and their chats go back to "active". Its own sweep because the money arrives by
// payment link, which only this one knows how to ask Razorpay about.
Artisan::command('whatsapp:expire-holds', function (\App\Services\WhatsAppReservationService $desk) {
    $r = $desk->expireLapsedHolds();
    $this->info("WhatsApp desk holds: {$r['expired']} expired, {$r['paid']} paid, {$r['unknown']} left for the next pass.");
})->purpose('Expire lapsed WhatsApp Desk holds (settling any that were paid)');

Schedule::command('whatsapp:expire-holds')->everyMinute()->withoutOverlapping();

// Tickets that were paid for but never confirmed — the buyer's client died between Razorpay
// capturing the money and our confirm call, so the order sits PENDING or was written off as
// EXPIRED while the charge stands. Asks Razorpay which of those orders actually holds a
// captured payment. Reports only; pass --apply to confirm them and send the tickets out.
Artisan::command('bookings:reconcile-payments {--days=30} {--apply}', function (BookingService $bookings) {
    $found = $bookings->reconcileUnconfirmedPayments((int) $this->option('days'), (bool) $this->option('apply'));

    if ($found === []) {
        $this->info('No paid-but-unconfirmed ticket orders found.');

        return;
    }

    $this->table(
        ['Razorpay order', 'Booking(s)', 'Payment', 'Action'],
        array_map(fn (array $r): array => [
            $r['order'], implode(', ', $r['bookings']), $r['payment'] ?? '—', $r['action'],
        ], $found),
    );

    $this->warn(count($found) . ' order(s) listed.' . ($this->option('apply') ? '' : ' Re-run with --apply to confirm them.'));
})->purpose('Find (and optionally fix) ticket orders Razorpay captured but that never confirmed');

// Bookings the customer paid for in the app before online money reached the ledger.
// They read 'unpaid' at the partner desk, so a court someone already paid for sits on
// the chase list and the venue's collected total is short. Reports only; --apply writes
// the ledger rows. Safe to re-run: a booking that already has one is skipped.
Artisan::command('bookings:backfill-online-payments {--apply}', function (BookingService $bookings) {
    $found = $bookings->backfillOnlinePayments((bool) $this->option('apply'));

    if ($found === []) {
        $this->info('No online-paid bookings are missing their ledger row.');

        return;
    }

    $this->table(
        ['Booking', 'Type', 'Amount', 'Razorpay payment'],
        array_map(fn (array $r): array => [$r['booking'], $r['type'], $r['amount'], $r['payment']], $found),
    );

    $total = array_sum(array_column($found, 'amount'));

    $this->warn(count($found) . ' booking(s), ₹' . number_format($total, 2) . '.'
        . ($this->option('apply') ? ' Recorded.' : ' Re-run with --apply to record them.'));
})->purpose('Record gateway money for online bookings confirmed before the ledger did');

// Waitlist offers on a freed court-hour are time-boxed. Without this they never
// lapse, so the first person offered silently holds a slot they may never pay for
// — which is worse than having no waitlist, because it looks sold and earns
// nothing. Lapsing returns them to the queue rather than dropping them.
Artisan::command('waitlist:release-lapsed', function (WaitlistService $waitlist) {
    $count = $waitlist->releaseLapsedOffers();
    $this->info("Returned {$count} lapsed waitlist offer(s) to the queue.");
})->purpose('Expire unanswered waitlist offers on freed slots');

Schedule::command('waitlist:release-lapsed')->everyFiveMinutes()->withoutOverlapping();

// Outbound message journeys (event reminders + the post-event review request).
// Two steps on purpose: enqueueing is idempotent bookkeeping that can run often
// and cheaply, while dispatch is the only thing that talks to a customer.
Artisan::command('messaging:enqueue-journeys', function (MessageJourneys $journeys) {
    $result = $journeys->enqueue();
    $this->info("Scanned {$result['scanned']} booking(s), queued {$result['queued']} new message(s).");
})->purpose('Queue reminders and review requests for upcoming bookings');

Artisan::command('messaging:dispatch-journeys', function (MessageJourneys $journeys) {
    $r = $journeys->dispatch();
    $this->info("Sent {$r['sent']}, skipped {$r['skipped']}, failed {$r['failed']}, held {$r['held']}.");
})->purpose('Deliver journey messages that are due');

Schedule::command('messaging:enqueue-journeys')->hourly()->withoutOverlapping();
// Every five minutes: fine-grained enough that a "2 hours before" reminder is
// actually about two hours before, without hammering the box.
Schedule::command('messaging:dispatch-journeys')->everyFiveMinutes()->withoutOverlapping();

// Member plans (Free / Pro / Hero): abandon dead checkouts, re-read Razorpay for renewals a
// webhook never reported, expire complimentary plans, and retry cancelling subscriptions an
// upgrade replaced. Dry run with --dry-run.
Artisan::command('membership:reconcile {--dry-run}', function (\App\Services\Membership\MemberSubscriptions $subscriptions) {
    $dry = (bool) $this->option('dry-run');
    $r = $subscriptions->reconcile($dry);
    $this->info(($dry ? '[dry run] ' : '')
        . "Abandoned {$r['abandoned']}, synced {$r['synced']} (failed {$r['sync_failed']}), "
        . "grants expired {$r['grants_expired']}, replaced cancelled {$r['replaced_cancelled']}.");
})->purpose('Reconcile member subscriptions with Razorpay');

Schedule::command('membership:reconcile')->everyFifteenMinutes()->withoutOverlapping();

// Post-match rewards housekeeping: expire rewards past their date (reserved sponsor codes go
// back to their pool), remind players before a reward expires, expire unverified ad sessions.
Artisan::command('rewards:maintain', function (RewardMaintenance $maintenance) {
    $r = $maintenance->run();
    $this->info("Expired {$r['expired']} reward(s), {$r['coupons_expired']} unused coupon(s); "
        ."warned about {$r['warned']}; expired {$r['sessions_expired']} ad session(s).");
})->purpose('Expire, remind and tidy post-match rewards');

Schedule::command('rewards:maintain')->everyFifteenMinutes()->withoutOverlapping();

// Record every badge players have already earned, without celebrating any of them. Run once at
// deploy (before the first match finishes) so launch day doesn't announce old badges.
Artisan::command('rewards:backfill-badges {--dry-run}', function (BadgeService $badges) {
    $dry = (bool) $this->option('dry-run');
    $users = 0;
    $unlocked = 0;
    User::query()->whereNotNull('player_id')->where('player_id', '!=', '')
        ->orderBy('id')->chunkById(200, function ($chunk) use ($badges, $dry, &$users, &$unlocked): void {
            foreach ($chunk as $user) {
                $users++;
                if ($dry) {
                    continue;
                }
                $unlocked += count($badges->sync($user, null, false));
                User::query()->whereKey($user->id)->whereNull('rewards_baselined_at')
                    ->update(['rewards_baselined_at' => now()]);
            }
        });
    $this->info(($dry ? '[dry run] ' : '')."Players: {$users}; badges recorded: {$unlocked}.");
})->purpose('Record already-earned badges without celebrating them');
