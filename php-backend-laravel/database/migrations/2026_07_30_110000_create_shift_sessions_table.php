<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash reconciliation — the quiet epidemic this exists to stop.
 *
 * The desk takes ₹1,400 in cash, marks the booking "paid at venue", and pockets
 * some of it. Owners feel the gap and cannot prove it, because nothing in the
 * system ever claimed how much cash *should* be in the drawer.
 *
 * A shift makes that claim. It opens with a float, accumulates the cash payments
 * taken during it (via booking_payments.shift_session_id), and closes with a
 * physical count. The difference is `variance`, logged against a named person.
 *
 * Only CASH creates variance — UPI and card land in the venue's account and on
 * the PDQ statement, so they are totalled for reconciliation but never counted in
 * a drawer. Gateway money never belongs to a shift at all.
 *
 * @see \App\Services\ShiftService
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();

            // The staff member on duty. A manager may open a shift for someone
            // else, so who opened it is tracked separately.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('opened_at');
            $table->decimal('opening_float', 10, 2)->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            // What was physically counted, and how far off the system's claim it was.
            // Stored rather than derived so a historical close-out cannot be
            // retro-changed by a later edit to the ledger.
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->decimal('variance', 10, 2)->nullable();

            $table->string('note')->nullable();
            $table->timestamps();

            // "Is a shift open for this person at this venue" — the hot query.
            $table->index(['venue_id', 'user_id', 'closed_at']);
            $table->index(['venue_id', 'opened_at']);
        });

        Schema::table('booking_payments', function (Blueprint $table): void {
            // Null = money that belongs to no drawer: gateway payments, or cash
            // taken while no shift was open (itself a signal worth surfacing).
            $table->foreignId('shift_session_id')->nullable()->after('collected_by')
                ->constrained('shift_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_session_id');
        });

        Schema::dropIfExists('shift_sessions');
    }
};
