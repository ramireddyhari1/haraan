<?php

use App\Services\BookingService;
use App\Services\MatchVerificationService;
use App\Services\MessageJourneys;
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

// Release expired ticket locks (abandoned checkouts) so the seat returns to the
// pool for the next buyer, without waiting for someone to next book that event.
Artisan::command('bookings:release-expired', function (BookingService $bookings) {
    $count = $bookings->releaseAllExpired();
    $this->info("Released {$count} expired ticket lock(s).");
})->purpose('Release expired ticket reservation holds');

Schedule::command('bookings:release-expired')->everyMinute();

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
