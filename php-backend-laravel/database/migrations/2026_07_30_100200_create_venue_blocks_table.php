<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything that occupies a court-hour but is NOT a booking.
 *
 * Maintenance, holidays, academy batches, tournament holds and private hires all
 * consume the same physical court-hour as a paying booking, but none of them were
 * reservations, so none of them blocked anything — that is the double-booking that
 * costs more trust than ten features buy.
 *
 * Deliberately NOT a generic polymorphic `court_reservations` table that bookings
 * would also write to: that would mean migrating every existing booking into a
 * parallel table, keeping the two in sync forever, and rewriting the one code path
 * (BookingService::reserveVenue) that already works correctly. All risk, no payoff.
 * Bookings keep occupying court-hours as they do; everything else lives here, and
 * BookingService::assertCourtHourFree() checks both.
 *
 * One shape covers all four calendar block types in the brief:
 *   maintenance  — one court, a date range, a time window
 *   holiday      — no court (whole venue), no time (whole day)
 *   academy      — one court, a weekday, a long date range
 *   tournament   — one or all courts, a weekend, a time window
 *
 * NB `venue_blocked_dates` still exists and is still read by the app and the
 * partner API (blockDate/unblockDate). The engine reads BOTH; new UI writes only
 * here. Folding the legacy table in is a deliberate, time-boxed follow-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();

            // Null court = every court at this venue is blocked.
            $table->foreignId('venue_court_id')->nullable()->constrained()->cascadeOnDelete();

            // maintenance | holiday | academy | tournament | private
            $table->string('kind', 20)->default('maintenance');
            $table->string('title')->nullable();

            $table->date('starts_on');
            $table->date('ends_on');

            // Null = every day in the range. 0=Sunday … 6=Saturday (Carbon dayOfWeek).
            $table->unsignedTinyInteger('weekday')->nullable();

            // Null start = the whole day is blocked. Stored as 24h "HH:MM".
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_blocks');
    }
};
