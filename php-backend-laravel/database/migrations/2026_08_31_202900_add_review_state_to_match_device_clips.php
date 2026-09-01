<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a clip's review has got to.
 *
 * The review used to happen inside the HTTP request, so a clip was either reviewed or it
 * was not and there was nothing in between to record. Moving the Vertex call onto a queue
 * creates a middle: requested but not finished, and finished but failed. Both have to be
 * describable or the app can only show a spinner and hope.
 *
 * `review_error` holds a SHORT, USER-SAFE reason. Never an exception message, never a
 * stack trace, never anything from the credentials or the upstream response body — those
 * belong in the log, and this column is read straight out to a phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            // Null for every clip that has never been asked about, which is most of them.
            $table->string('review_status', 16)->nullable()->after('analysed_at');
            $table->string('review_error', 160)->nullable()->after('review_status');
            $table->timestamp('review_requested_at')->nullable()->after('review_error');
            // How long Vertex actually took, so latency is measurable in production
            // rather than guessed from one local run.
            $table->unsignedInteger('review_ms')->nullable()->after('review_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('match_device_clips', function (Blueprint $table): void {
            $table->dropColumn(['review_status', 'review_error', 'review_requested_at', 'review_ms']);
        });
    }
};
