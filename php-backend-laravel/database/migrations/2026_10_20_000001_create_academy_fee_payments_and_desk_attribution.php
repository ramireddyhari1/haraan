<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academy fees and package sales become desk money.
 *
 * Until now a fee was a number added onto `batch_enrollments.amount_paid` and a
 * package sale lived only on its `customer_packages` row. Neither knew who took the
 * money or which shift it went into, so cash taken for either never reached the
 * drawer's expected total and every close-out read "over" by exactly that much.
 *
 * - `academy_fee_payments`: one row per fee collected (a ledger, like
 *   booking_payments), carrying the collector and the shift.
 * - `customer_packages` gains `collected_by` + `shift_session_id`: the sale row is
 *   already one row per payment, it just never said whose drawer it went into.
 *
 * Existing enrolments keep their history: whatever `amount_paid` held becomes one
 * opening row (method `opening`, no shift), so a student's fee history adds up to
 * what the desk was already showing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academy_fee_payments')) {
            Schema::create('academy_fee_payments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('batch_enrollment_id');
                $table->unsignedBigInteger('partner_id');
                // The branch whose desk took it (the batch's venue). Null for a batch
                // with no venue; such money joins no drawer.
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->unsignedInteger('amount');
                // cash | upi | card | opening (pre-ledger balance carried over)
                $table->string('method', 20)->default('cash');
                $table->unsignedSmallInteger('months')->default(1);
                // What this payment extended the student's cover to.
                $table->date('paid_until')->nullable();
                $table->unsignedBigInteger('collected_by')->nullable();
                $table->unsignedBigInteger('shift_session_id')->nullable();
                $table->timestamp('collected_at');
                $table->timestamps();

                $table->index(['batch_enrollment_id', 'collected_at']);
                $table->index(['partner_id', 'collected_at']);
                $table->index('shift_session_id');
                $table->index(['collected_by', 'collected_at']);
            });
        }

        Schema::table('customer_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_packages', 'collected_by')) {
                $table->unsignedBigInteger('collected_by')->nullable();
                $table->index('collected_by');
            }
            if (! Schema::hasColumn('customer_packages', 'shift_session_id')) {
                $table->unsignedBigInteger('shift_session_id')->nullable();
                $table->index('shift_session_id');
            }
        });

        // Carry existing balances over as one opening row each, once.
        $already = DB::table('academy_fee_payments')->where('method', 'opening')->pluck('batch_enrollment_id')->all();
        DB::table('batch_enrollments')
            ->join('venue_batches', 'venue_batches.id', '=', 'batch_enrollments.venue_batch_id')
            ->where('batch_enrollments.amount_paid', '>', 0)
            ->whereNotIn('batch_enrollments.id', $already)
            ->select([
                'batch_enrollments.id', 'batch_enrollments.partner_id', 'batch_enrollments.amount_paid',
                'batch_enrollments.paid_until', 'batch_enrollments.created_at', 'venue_batches.venue_id',
            ])
            ->orderBy('batch_enrollments.id')
            ->each(function ($e): void {
                DB::table('academy_fee_payments')->insert([
                    'batch_enrollment_id' => $e->id,
                    'partner_id' => $e->partner_id,
                    'venue_id' => $e->venue_id,
                    'amount' => $e->amount_paid,
                    'method' => 'opening',
                    'months' => 0,
                    'paid_until' => $e->paid_until,
                    'collected_at' => $e->created_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_fee_payments');

        Schema::table('customer_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_packages', 'shift_session_id')) {
                $table->dropIndex(['shift_session_id']);
                $table->dropColumn('shift_session_id');
            }
            if (Schema::hasColumn('customer_packages', 'collected_by')) {
                $table->dropIndex(['collected_by']);
                $table->dropColumn('collected_by');
            }
        });
    }
};
