<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People who wanted a court-hour that was already sold.
 *
 * Cancellation is a venue's biggest single loss event — a 7pm Saturday that frees
 * up at 4pm is usually dead money. This table turns it into revenue: when a
 * booking is cancelled, everyone who wanted that window is matched and offered it,
 * first to pay keeps it.
 *
 * The offer is time-boxed on purpose. Without `offer_expires_at` the first person
 * on the list silently holds a slot they may never pay for, which is worse than
 * having no waitlist at all.
 *
 * @see \App\Services\WaitlistService
 * @see \App\Observers\BookingObserver  fires the match on cancellation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_waitlist', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();

            // Null court = "any court will do", which is the common case — people
            // want 7pm Saturday, not Turf B specifically.
            $table->foreignId('venue_court_id')->nullable()->constrained()->nullOnDelete();

            $table->date('wanted_on');
            // Null time = any slot that day. Stored as 24h "HH:MM" to match bookings.
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();

            // Either an app user, or a name+phone taken over WhatsApp/at the desk.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();

            // waiting | offered | converted | expired | cancelled
            $table->string('status', 12)->default('waiting');

            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('notified_at')->nullable();

            // Which cancellation freed the slot, and which booking they ended up with.
            $table->foreignId('freed_by_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('converted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // "Who is waiting for this venue on this date" — the matching query.
            $table->index(['venue_id', 'wanted_on', 'status']);
            $table->index(['status', 'offer_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_waitlist');
    }
};
